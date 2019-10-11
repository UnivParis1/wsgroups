<?php

require_once ('lib/common.inc.php');
require_once ('gen/tables.inc.php');
require_once ('config/config-groups.inc.php'); // in case groups.inc.php is used (php files setting global variables must be required outside a function!)

global $USER_KEY_FIELD, $USER_ALLOWED_ATTRS;
$USER_KEY_FIELD = 'uid';
$attrs_by_kind = [
  "MONO -1" => [
    'uid', 'mail', 'displayName', 'cn', 'eduPersonPrimaryAffiliation', 
	'postalAddress', 'eduPersonPrincipalName',
	'sn', 'givenName',
    'supannEntiteAffectationPrincipale', 'supannEntiteAffectationPrincipale-all',
	'supannCivilite', 
	'supannListeRouge',
	'supannAliasLogin',
	'uidNumber', 'gidNumber',
	'accountStatus', 
  ],
  "MONO 1" => [
    'supannEmpId', 'supannEtuId', 'supannCodeINE',
    'shadowFlag', 'shadowExpire', 'shadowLastChange',    
	'homeDirectory', 'gecos',
    'sambaAcctFlags', 'sambaSID', 'sambaHomePath',

    // from up1Profile
    'up1Source', 'up1Priority', 'up1StartDate', 'up1EndDate',
  ],
  "MONO 2" => [
	'supannEmpCorps',    
	'employeeNumber', // (NB: search allowed in level 1)
    
	'createTimestamp', 'modifyTimestamp',

	'up1BirthName',
	'up1BirthDay',

    'homePhone', 'homePostalAddress', 'pager',
	'supannMailPerso',
  ],
  "MULTI -1" => [
    'supannEntiteAffectation', 'supannEntiteAffectation-ou', 'supannEntiteAffectation-all',
    'eduPersonAffiliation', 
    'buildingName', 'description', 'info',
	'supannEtablissement', 'supannActivite', 'supannActivite-all',
	'supannParrainDN', 'supannParrainDN-ou', 'supannParrainDN-all',
	'supannRoleEntite', 'supannRoleEntite-all',
	'supannEtuInscription', 'supannEtuInscription-all',
	'memberOf', 'memberOf-all',
	'supannRoleGenerique',
    'eduPersonEntitlement',

    'up1AltGivenName',
	'up1KrbPrincipal',
	'roomNumber', 'up1FloorNumber', 'up1RoomAccess',

	'telephoneNumber', 
	'facsimileTelephoneNumber', 
	'supannAutreTelephone', 'mobile',

	'objectClass',
	'labeledURI',
    'seeAlso', 'seeAlso-all',
    
    'up1Profile', // will be filtered
  ],
  "MULTI 2" => [
    'employeeType', 'departmentNumber',
  ],
  "MULTI 1" => [
	// below are restricted or internal attributes.
	'mailForwardingAddress', 'mailDeliveryOption', 'mailAlternateAddress',
    'up1TermsOfUse',
  ],
];
$USER_ALLOWED_ATTRS = [];
foreach ($attrs_by_kind as $kind => $attrs) {
    $kind_ = explode(' ', $kind);
    $tags = [ "MULTI" => $kind_[0] === 'MULTI', "LEVEL" => (int) $kind_[1] ];
    foreach ($attrs as $attr) $USER_ALLOWED_ATTRS[$attr] = $tags;
}
global $UP1_ROLES_DN;
if (@$UP1_ROLES_DN) {
    $USER_ALLOWED_ATTRS['up1Roles'] = [ "MULTI" => true, "LEVEL" => -1 ]; // computed
}

function people_attrs($attrs, $allowExtendedInfo = 0) {
    global $USER_ALLOWED_ATTRS;
    if (!$attrs) $attrs = implode(',', array_keys($USER_ALLOWED_ATTRS));
    $wanted_attrs = array();
    foreach (explode(',', $attrs) as $attr) {
        $attr_kinds = @$USER_ALLOWED_ATTRS[$attr];
        if (!$attr_kinds) {
            error("unknown attribute $attr. allowed attributes: " . join(",", array_keys($USER_ALLOWED_ATTRS)));
            exit;
        }
        $wanted_attrs[$attr] = $attr_kinds['MULTI'] ? 'MULTI' : $attr;
    }
    global $USER_KEY_FIELD;
    if (!isset($wanted_attrs[$USER_KEY_FIELD]))
        $wanted_attrs[$USER_KEY_FIELD] = $USER_KEY_FIELD;

    // employeeType is only allowed on some eduPersonPrimaryAffiliation
    // departmentNumber is only useful for some eduPersonPrimaryAffiliation
    if (isset($wanted_attrs['employeeType']) || isset($wanted_attrs['departmentNumber']))
        $wanted_attrs['eduPersonPrimaryAffiliation'] = 'eduPersonPrimaryAffiliation';

    // we want to hide 'mail' when accountStatus is unset
    if (isset($wanted_attrs['mail']))
        $wanted_attrs['accountStatus'] = 'accountStatus';

    // most attributes visibility are enforced using ACLs on LDAP bind
    // here are a few special cases
    if ($allowExtendedInfo < 1) {
        foreach (array('memberOf', 'memberOf-all') as $attr) {
            unset($wanted_attrs[$attr]);
        }
    }
    if ($allowExtendedInfo < 0) {
        unset($wanted_attrs['mobile']);
    }
    
    return $wanted_attrs;
}

function roomNumber_filter($normalized_token, $ext) {
    $or = [];
    foreach ($ext ? [ ' ' . trim($ext) ] : [ '', ' bis', ' ter' ] as $ext) {
        $or[] = "(roomNumber=$normalized_token$ext)";
    }
    return ldapOr($or);
}

function people_filters($token, $restriction = [], $allowInvalidAccounts = false, $allowNoAffiliationAccounts = false) {
    if ($allowInvalidAccounts !== 'all') {
        $restriction[] = $allowInvalidAccounts ? '(&(objectClass=inetOrgPerson)(!(shadowFlag=2))(!(shadowFlag=8)))' : // ignore dupes/deceased
                     ($allowNoAffiliationAccounts ? '(|(accountStatus=active)(!(accountStatus=*)))' : '(eduPersonAffiliation=*)');
    }

    $l = array();

    // MIFARE?
    if (preg_match('/^[0-9A-F]{14}$/', $token) || // DESFire
        preg_match('/^[0-9A-F]{8}$/', $token)) { // Classic
        $l[] = "(supannRefId={MIFARE}$token)";
    }
    
    if ($token === '') {
        $l[] = '(supannRoleGenerique={SUPANN}D*)'; // important people first!
        $l[] = '(supannRoleGenerique=*)'; // then other important people
        $l[] = ''; // then the rest
    } else if (preg_match('/(.*?)@(.*)/', $token, $matches)) {
        $l[] = "(|(mail=$token)(&(uid=$matches[1])(mail=*@$matches[2])))";
    } else if (preg_match('/^\d+$/', $token, $matches)) {
        $l[] = "(|(supannEmpId=$token)(supannEtuId=$token))";

        if (strlen($token) <= 4) {
            $l[] = roomNumber_filter($token, null);
        }
        // barcode?
        if (strlen($token) === 12) { // codification unique UNPIdF, used at Paris1
            $l[] = "(employeeNumber=$token)";
        }
    } else {
        $l[] = "(uid=$token)";
        $l[] = "(sn=$token)";

        if (preg_match('/^([A-Z])\.? ?([0-9]+)( ?bis| ?ter)?$/i', $token, $matches) ||
            preg_match('/^([0-9]{1,4}) ?\.?([A-Z])?( ?bis| ?ter)?$/i', $token, $matches)) {
            list($_ignore, $a, $b, $ext) = $matches;
            $l[] = roomNumber_filter($a . ($b ? " " . trim($b) : ''), $ext);
        }

        if (mb_strlen($token) > 3) {
            // too short strings are useless
            $l[] = "(|(displayName=*$token*)(cn=*" . lowercase_and_stripAccents($token) . "*)(up1BirthName=*$token*))";
            $tokens = preg_split("/[\s']+/", $token);
            if (sizeof($tokens) === 2) {
                $tokens = array($tokens[1], $tokens[0]);
                $search = implode('*', $tokens);
                $short_tokens = array_filter($tokens, function ($s) { return mb_strlen($s) <= 3; });
                $x = sizeof($short_tokens) === 0 ? '*' : '';
                $l[] = "(|(displayName=$x$search$x)(cn=$x" . lowercase_and_stripAccents($search) . "$x))";
            }
        }
    }

    return apply_restrictions_to_filters($l, $restriction);
}

function GET_extra_people_filter_from_params() {
  $filters = array();
  $filters_not = array();
  foreach (array("eduPersonAffiliation", "eduPersonPrimaryAffiliation", "supannEntiteAffectation", "description", "employeeType", "supannRoleGenerique") as $attr) {
    $filters[$attr] = GET_ldapFilterSafe_or_NULL("filter_$attr");
    $filters_not[$attr] = GET_ldapFilterSafe_or_NULL("filter_not_$attr");
  }
  foreach (array("student") as $attr) {
    $val = GET_ldapFilterSafe_or_NULL("filter_$attr");
    if ($val === null) continue;
    else if ($val === "no") $filters_not["eduPersonAffiliation"] = $attr;
    else if ($val === "only") $filters["eduPersonAffiliation"] = $attr;
    else exit("invalid filter_$attr value $val");
  }
  foreach (array("mail", "labeledURI", 'supannEmpId') as $attr) {
    $val = GET_or_NULL("filter_$attr");
    if ($val === null) continue;
    else if ($val === "*") $filters[$attr] = "*";
    else exit("invalid filter_$attr value $val");
  }
  return array_merge(
      computeFilter($filters, false),
      computeFilter($filters_not, true),
      GET_filter_member_of_group());
}

function GET_filter_member_of_group() {
  $keys = GET_or_NULL("filter_member_of_group");
  if (!$keys) return [];

  return [ldapOr(array_map('groupKey2filter', array_map('ldap_escape_string', explode('|', $keys))))];
}

function groupKey2filter($key) {
  global $GROUPS_DN, $DIPLOMA_DN, $DIPLOMA_PREV_DN;

  if ($cn = removePrefixOrNULL($key, "groups-")) {
    return "(memberOf=cn=$cn,$GROUPS_DN)";
  } else if ($supannCodeEntite = removePrefixOrNULL($key, "structures-")) {

    // handle key like structures-U05-affiliation-student:
    if (preg_match('/(.*)-affiliation-(.*)/', $supannCodeEntite, $matches)) {
      $supannCodeEntite = $matches[1];
      $affiliation = $matches[2];
    } else {
      $affiliation = null;
    }

    $filter = "(supannEntiteAffectation=$supannCodeEntite)";
    if ($affiliation)
      $filter = "(&$filter(eduPersonAffiliation=$affiliation))";

    return $filter;
  } else if ($diploma = removePrefixOrNULL($key, "diploma-")) {
    $ou = "ou=$diploma," . $DIPLOMA_DN;
    return "(eduPersonOrgUnitDN=$ou)";
  } else if ($diploma = removePrefixOrNULL($key, "diplomaPrev-")) {
    $ou = "ou=$diploma," . $DIPLOMA_PREV_DN;
    return "(eduPersonOrgUnitDN=$ou)";
  } else if ($affiliation = removePrefixOrNULL($key, "affiliation-")) {
    return "(eduPersonAffiliation=$affiliation)";
  } else {
    exit("invalid group key $key");
  }
}

function isPersonMatchingFilter($uid, $filter) {
    global $PEOPLE_DN;
    return existsLdap($PEOPLE_DN, "(&(uid=$uid)" . $filter . ")");
}

function loggedUserAllowedLevel() {
    global $LEVEL1_FILTER, $LEVEL2_FILTER;
    return isPersonMatchingFilter(GET_uid(), $LEVEL1_FILTER) ?
        (isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER) ? 2 : 1) : 0;
}

function allowListeRouge($allowExtendedInfo) {
    global $isTrustedIp;
    if ($allowExtendedInfo > 0 || @$isTrustedIp) {
        return true;
    } else {
        $uid = GET_uid();
        return $uid && isPersonMatchingFilter($uid, "(|(eduPersonAffiliation=staff)(eduPersonAffiliation=faculty)(eduPersonAffiliation=teacher))");
    }
}

function searchPeopleRaw($filter, $allowListeRouge, $allowRoles, $wanted_attrs, $KEY_FIELD, $maxRows) {
    global $BASE_DN, $PEOPLE_DN, $SEARCH_TIMELIMIT;
    if (!$allowListeRouge) {
	// we need the attr to anonymize people having supannListeRouge=TRUE
	$wanted_attrs['supannListeRouge'] = 'supannListeRouge';
    }
    if ($allowRoles) $wanted_attrs['dn'] = 'dn';
    $r = getLdapInfoMultiFilters($allowRoles ? $BASE_DN : $PEOPLE_DN, $filter, $wanted_attrs, $KEY_FIELD, $maxRows, $SEARCH_TIMELIMIT);
    if (!$allowListeRouge) {
      foreach ($r as &$e) {
	if (!isset($e["supannListeRouge"])) continue;
	$supannListeRouge = getAndUnset($e, "supannListeRouge");
	if ($supannListeRouge == "TRUE") {
	  if (sizeof($r) == 1) {
	    // hum, the search is precise enough to return only one result.
	    // if we return the anonymized result, someone can know a user exists, even if anonymized
	    // better return nothing! 
	    $r = array();
	  } else {
	    anonymizeUser($e, $wanted_attrs);
	  }
	}
      }
    }
    return $r;
}

function wanted_attrs_raw($wanted_attrs) {
    $r = array();
    foreach ($wanted_attrs as $attr => $v) {
	$attr_raw = preg_replace('/-.*/', '', $attr);
	$r[$attr_raw] = preg_replace('/-.*/', '', $v);
    }
    return $r;
}

function attrRestrictions($allowExtendedInfo = 0) {
    global $isTrustedIp;
    return
        array('allowListeRouge' => allowListeRouge($allowExtendedInfo),
        'allowAccountStatus' => GET_uid(),
        'allowMailForwardingAddress' => $allowExtendedInfo > 1,
        'allowEmployeeType' => $allowExtendedInfo > 1,
        'allowExtendedInfo' => $allowExtendedInfo,
        );
}

function searchPeople($filter, $attrRestrictions, $wanted_attrs, $KEY_FIELD, $maxRows) {
    $allowListeRouge = @$attrRestrictions['allowListeRouge'];
    $wanted_attrs_raw = wanted_attrs_raw($wanted_attrs);
    $r = searchPeopleRaw($filter, $allowListeRouge, @$attrRestrictions['allowRoles'], $wanted_attrs_raw, $KEY_FIELD, $maxRows);
    foreach ($r as &$user) {
      // we want to hide 'mail' when accountStatus is unset
      if (@$user['mail'] && !@$user['accountStatus'])
        unset($user['mail']);
      if (!@$attrRestrictions['allowAccountStatus'])
	     unset($user['accountStatus']);
      if (!@$attrRestrictions['allowEmployeeType'])
	  userHandleSpecialAttributePrivacy($user);
      if (!@$attrRestrictions['allowMailForwardingAddress'])
	  anonymizeUserMailForwardingAddress($user);
      userAttributesKeyToText($user, $wanted_attrs);
      if (isset($user['up1Profile']))
        $user['up1Profile'] = parse_up1Profile($user['up1Profile'], $attrRestrictions['allowExtendedInfo'], $wanted_attrs);
      userHandle_postalAddress($user);
      if (@$wanted_attrs['up1Roles']) get_up1Roles($user);
    }
    return $r;
}

function userHandle_postalAddress(&$e) {
    if (@$e['postalAddress']) {
	$e['postalAddress'] =
	    str_replace("\\\n", '\$', str_replace('$', "\n", $e['postalAddress']));
    }
}

function anonymizeUser(&$e, $attributes_map) {
    global $PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS;
    $allowed = array();
    foreach ($PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS as $attr) {
	if (isset($attributes_map[$attr])) 
	    $allowed[$attributes_map[$attr] == "MULTI" ? $attr : $attributes_map[$attr]] = 1;
    }

    foreach ($e as $k => $v) {
	if (!isset($allowed[$k])) {
	    $e[$k] = $attributes_map[$k] == "MULTI" ? array() : 'supannListeRouge';
	}
    }
}

function anonymizeUserMailForwardingAddress(&$e) {
  if (!isset($e['mailForwardingAddress'])) return;
  foreach ($e['mailForwardingAddress'] as &$mail) {
    if (preg_match("/@/", $mail)) $mail = 'supannListeRouge';
  }
}

function structureShortnames($keys) {
    $all = structureAll($keys);
    $r = array();
    foreach ($all as $e) {
      $r[] = @$e['name'];
    }
    return empty($r) ? NULL : $r;
}
function structureAll($keys) {
    GLOBAL $structureKeyToAll, $showErrors;
    $r = array();
    foreach ($keys as $key) {
      $e = array("key" => $key);
      if (isset($structureKeyToAll[$key]))
	$e = array_merge($e, $structureKeyToAll[$key]);
      else if ($showErrors)
	$e["name"] = "invalid structure $key";

      $r[] = $e;
    }
    return empty($r) ? NULL : $r;
}

function activiteUP1All($descriptions) {
  global $descriptionToActivityKey;
  $r = [];
  foreach ($descriptions as $description) {
      $key = @$descriptionToActivityKey[$description];
      if ($key) $r[] = ['key' => $key, 'name' => $description];
  }
  return $r;
}

function supannActiviteAll($keys) {
  global $activiteKeyToShortname;
  $r = array();
  foreach ($keys as $key) {
    $e = array('key' => $key);
    $name = @$activiteKeyToShortname[$key];
    if ($name) $e['name'] = $name;
    $r[] = $e;
  }
  return empty($r) ? NULL : $r;
}

function supannActiviteShortnames($keys) {
    $all = supannActiviteAll($keys);
    $r = array();
    foreach ($all as $e) {
      $r[] = @$e['name'];
    }
    return empty($r) ? NULL : $r;
}

function parse_composite_value($s) {
  preg_match_all('/\[(.*?)\]/', $s, $m);
  $r = array();
  foreach ($m[1] as $e) {
    list($k,$v) = explode('=', $e, 2);
    $r[$k] = $v;
  }
  return $r;
}

function parse_supannEtuInscription($s) {
  return parse_composite_value($s);
}

# inverse échappement les caractères spéciaux d'attributs composites pour une liste de valeurs
function unescape_sharpFF($attr_value) {
    return preg_replace_callback('/#([0-9A-F]{2})/', function ($xx) { return chr(hexdec($xx)); }, $attr_value);
}

function parse_up1Profile_one($up1Profile, $allowExtendedInfo, $wanted_attrs) {
    global $USER_ALLOWED_ATTRS;
    $r = [];
    while (preg_match('/^\[([^\[\]=]+)=((?:[^\[\]]|\[[^\[\]]*\])*)\](.*)/', $up1Profile, $m)) {
        $key = $m[1]; $val = $m[2]; $up1Profile = $m[3];
        $key = unescape_sharpFF($key);
        $attr_kinds = @$USER_ALLOWED_ATTRS[$key];
        if (!$attr_kinds || $allowExtendedInfo < $attr_kinds['LEVEL']) {
            // ignore
        } else if ($attr_kinds['MULTI']) {
            $r[$key] = array_map('unescape_sharpFF', explode(';', $val));
        } else {
            $r[$key] = unescape_sharpFF($val);
        }
    }
    if ($up1Profile !== '') error_log("bad up1Profile, remaining $up1Profile");
    userAttributesKeyToText($r, $wanted_attrs);
    return $r;
}

function parse_up1Profile($up1Profile_s, $allowExtendedInfo, $wanted_attrs) {
    $r = [];
    foreach ($up1Profile_s as $profile) {
       $r[] = parse_up1Profile_one($profile, $allowExtendedInfo, $wanted_attrs);
    }
    return $r;
}

function supannEtuInscriptionAll($supannEtuInscription) {
  $r = parse_supannEtuInscription($supannEtuInscription);
  if (@$r['etape']) {
    $localEtape = removePrefix($r['etape'], '{UAI:0751717J}');
    require_once 'lib/groups.inc.php';
    $diploma = getGroupsFromDiplomaDn(array("(ou=$localEtape)"), 1);
    if ($diploma) $r['etape'] = $diploma[0]["description"];
  }
  if (@$r['etab'] === '{UAI}0751717J') {
    unset($r['etab']);
  }
  if (@$r['cursusann']) {
    $r['cursusann'] = removePrefix($r['cursusann'], '{SUPANN}');
  }
  if (@$r['typedip'] && $r['typedip'] !== '{INCONNU}') {
    // http://infocentre.pleiade.education.fr/bcn/workspace/viewTable/n/N_TYPE_DIPLOME_SISE
    $to_name = array(
		     '01' => "DIPLOME UNIVERSITE GENERIQUE",
		     '03' => "HABILITATION A DIRIGER DES RECHERCHES",
		     '05' => "DIPLOME INTERNATIONAL",
		     'AC' => "CAPACITE EN DROIT",
		     'DP' => "LICENCE PROFESSIONNELLE",
		     'EZ' => "PREPARATION AGREGATION",
		     'FE' => "MAGISTERE",
		     'NA' => "AUTRES DIPL. NATIONAUX NIV. FORM. BAC",
		     'UE' => "DIPLOME UNIV OU ETAB NIVEAU BAC + 4",
		     'UF' => "DIPLOME UNIV OU ETAB NIVEAU BAC + 5",
		     'XA' => "LICENCE (LMD)",
		     'XB' => "MASTER (LMD)",
		     'YA' => "DOCTORAT D'UNIVERSITE",
		     'YB' => "DOCTORAT D'UNIVERSITE (GENERIQUE)",
		     'ZA' => "DIPLOME PREP AUX ETUDES COMPTABLES",
		     );
    $r['typedip'] = $to_name[removePrefix($r['typedip'], '{SISE}')];
  }
  if (@$r['regimeinsc']) {
    // http://infocentre.pleiade.education.fr/bcn/workspace/viewTable/n/N_REGIME_INSCRIPTION
    $to_name = array('10' => 'Formation initiale',
		     '11' => 'Reprise études',
		     '12' => 'Formation initiale apprentissage', 
		     '21' => 'Formation continue');
    $r['regimeinsc'] = $to_name[removePrefix($r['regimeinsc'], '{SISE}')];
  }
  return $r;
}

function supannRoleEntiteAll($e) {
  $r = parse_composite_value($e);
  if (@$r['role']) {
    global $roleGeneriqueKeyToAll;
    if ($role = $roleGeneriqueKeyToAll[$r['role']]) {
        $r['role'] = $role['name'];
        if (isset($role['weight'])) $r['role_weight'] = $role['weight'];
    }
  }
  if (@$r['code']) {
    $r['structure'] = array_shift(structureAll(array($r['code'])));
    unset($r['code']);
  }
  return $r;
}

function supannEtuInscriptionsAll($l) {
  $r = array();
  foreach ($l as $supannEtuInscription) {
    $r[] = supannEtuInscriptionAll($supannEtuInscription);
  }
  return empty($r) ? NULL : $r;
}

function supannRoleEntitesAll($l) {
  $r = array();
  foreach ($l as $e) {
    $r[] = supannRoleEntiteAll($e);
  }
  return empty($r) ? NULL : $r;
}

function memberOfAll($l) {
  $attrs = array("cn" => "key", "ou" => "name", "description" => "description", "objectClass" => "MULTI");

  $r = [];
  foreach ($l as $dn) $r[] = getLdapDN($dn, $attrs);
  return $r;
}

function getDNs($l) {
  $attrs = array("ou" => "name", "displayName" => "name", "description" => "description", "labeledURI" => "labeledURI");

  $r = [];
  foreach ($l as $dn) $r[] = getLdapDN_with_DN_as_key($dn, $attrs);
  return $r;
}

function replace_old_structures_DN($l) {
    global $ALT_STRUCTURES_DN, $STRUCTURES_DN;
    $r = [];
    foreach ($l as $dn) {
        $r[] = preg_replace('/^ou=(.*?),' . preg_quote($ALT_STRUCTURES_DN, '/') . '/', "supannCodeEntite=$1,$STRUCTURES_DN", $dn);
    }
    return $r;
}

function rdnToSupannCodeEntites($l) {
  $codes = array();
  foreach ($l as $rdn) {
    if (preg_match('/^supannCodeEntite=(.*?),ou=structures/', $rdn, $match)) {
      $codes[] = $match[1];
    } else if (preg_match('/^ou=(.*?),ou=structures/', $rdn, $match)) {
      $codes[] = $match[1]; // for local branch
    }
  }
  return $codes;
}

function userHandleSpecialAttributePrivacy(&$user) {
  if (isset($user['employeeType']) || isset($user['departmentNumber']))
    if (!in_array($user['eduPersonPrimaryAffiliation'], array('teacher', 'emeritus', 'researcher'))) {
      unset($user['employeeType']); // employeeType is private for staff & student
      unset($user['departmentNumber']); // departmentNumber is not interesting for staff & student
    }
}

function userAttributesKeyToText(&$user, $wanted_attrs) {
  $supannEntiteAffectation = @$user['supannEntiteAffectation'];
  if ($supannEntiteAffectation) {
      if (isset($wanted_attrs['supannEntiteAffectation-all']))
	  $user['supannEntiteAffectation-all'] = structureAll($supannEntiteAffectation);
      else if (isset($wanted_attrs['supannEntiteAffectation-ou']))
	  $user['supannEntiteAffectation-ou'] = structureShortnames($supannEntiteAffectation);
      else if (isset($wanted_attrs['supannEntiteAffectation']))
	  // deprecated
	  $user['supannEntiteAffectation'] = structureShortnames($supannEntiteAffectation);
  }
  if (isset($user['supannEntiteAffectationPrincipale'])) {
    if (isset($wanted_attrs['supannEntiteAffectationPrincipale-all'])) {
        $user['supannEntiteAffectationPrincipale-all'] = structureAll([$user['supannEntiteAffectationPrincipale']])[0];
    }
    if (!isset($wanted_attrs['supannEntiteAffectationPrincipale']))
        unset($user['supannEntiteAffectationPrincipale']);
}
  if (isset($user['seeAlso'])) {
      if (isset($wanted_attrs['seeAlso-all']))
        $user['seeAlso-all'] = getDNs(replace_old_structures_DN($user['seeAlso']));
  }
  if (isset($user['supannParrainDN'])) {
      if (isset($wanted_attrs['supannParrainDN-all']))
	$user['supannParrainDN-all'] = getDNs(replace_old_structures_DN($user['supannParrainDN']));
      else if (isset($wanted_attrs['supannParrainDN-ou']))
	$user['supannParrainDN-ou'] = structureShortnames(rdnToSupannCodeEntites($user['supannParrainDN']));
      if (!isset($wanted_attrs['supannParrainDN']))
	  unset($user['supannParrainDN']);
  }
  if (isset($user['supannEtuInscription'])) {
      if (isset($wanted_attrs['supannEtuInscription-all']))
	$user['supannEtuInscription-all'] = supannEtuInscriptionsAll($user['supannEtuInscription']);
      if (!isset($wanted_attrs['supannEtuInscription']))
	  unset($user['supannEtuInscription']);
  }
  if (isset($user['supannRoleEntite'])) {
      if (isset($wanted_attrs['supannRoleEntite-all']))
	$user['supannRoleEntite-all'] = supannRoleEntitesAll($user['supannRoleEntite']);
      if (!isset($wanted_attrs['supannRoleEntite']))
	  unset($user['supannRoleEntite']);
  }
  if (isset($user['memberOf'])) {
      if (isset($wanted_attrs['memberOf-all']))
	$user['memberOf-all'] = memberOfAll($user['memberOf']);
      if (!isset($wanted_attrs['memberOf']))
	  unset($user['memberOf']);
  }
  if (isset($user['supannRoleGenerique'])) {
    global $roleGeneriqueKeyToShortname;
    foreach ($user['supannRoleGenerique'] as &$e) {
      $e = $roleGeneriqueKeyToShortname[$e];
    }
  }
  if (isset($user['supannActivite'])) {
    if (isset($wanted_attrs['supannActivite-all']))
	$user['supannActivite-all'] = supannActiviteAll($user['supannActivite']);
    if (isset($wanted_attrs['supannActivite']))
        $user['supannActivite'] = supannActiviteShortnames($user['supannActivite']);
    else
        unset($user['supannActivite']);
  }
  if (isset($user['description']) && isset($wanted_attrs['supannActivite-all'])) {
	$user['supannActivite-all'] = array_merge((array) $user['supannActivite-all'], activiteUP1All($user['description']));
  }
  if (isset($user['supannEtablissement'])) {
    // only return interesting supannEtablissement (ie not Paris1)
    $user['supannEtablissement'] = array_values(array_diff($user['supannEtablissement'], array('{UAI}0751717J', "{autre}")));
    if (!$user['supannEtablissement']) {
      unset($user['supannEtablissement']);
    } else {
      global $etablissementKeyToShortname;
      foreach ($user['supannEtablissement'] as &$e) {
	$usefulKey = removePrefixOrNULL($e, "{AUTRE}");
	$name = @$etablissementKeyToShortname[$e];
	if ($name) $e = $usefulKey ? "$name [$usefulKey]" : $name;
      }
    }
  }
}

function get_up1Roles(&$user) {
  $roles = get_up1Roles_raw($user);
  if ($roles) $user['up1Roles'] = $roles;
}

function get_up1Roles_raw($user) {
  global $BASE_DN, $PEOPLE_DN;

  $roles = array();
  $rdn = "uid=" . $user['uid'] . ",$PEOPLE_DN";
  foreach (array('manager', 'roleOccupant', 'secretary', 'member') as $role) {
    $filter = "($role=$rdn)";
    if ($role === 'manager') $filter = "(|$filter(supannGroupeLecteurDN=$rdn))";
    $filter = "(&(objectClass=up1Role)$filter)";
    foreach (getLdapInfo($BASE_DN, $filter, array("mail" => "mail", "seeAlso" => "seeAlso", "description" => "description")) as $e) {
      $e['role'] = $role;
      $roles[] = $e;
    }
  }
  return $roles;
}

?>
