<?php

require_once ('./common.inc.php');
require_once ('./tables.inc.php');
require_once ('./config-groups.inc.php'); // in case groups.inc.php is used (php files setting global variables must be required outside a function!)

function people_filters($token, $restriction = [], $allowInvalidAccounts = false) {
    if (!$allowInvalidAccounts) $restriction[] = '(eduPersonAffiliation=*)';

    $l = array();
    if ($token === '') {
        $l[] = '(supannRoleGenerique={SUPANN}D*)'; // important people first!
        $l[] = '(supannRoleGenerique={SUPANN}*)'; // then other important people
        $l[] = ''; // then the rest
    } else if (preg_match('/(.*?)@(.*)/', $token, $matches)) {
        $l[] = "(|(mail=$token)(&(uid=$matches[1])(mail=*@$matches[2])))";
    } else if (preg_match('/^\d+$/', $token, $matches)) {
        $l[] = "(|(supannEmpId=$token)(supannEtuId=$token))";
    } else {
        $l[] = "(uid=$token)";
        $l[] = "(sn=$token)";

        if (strlen($token) > 3) 
            // too short strings are useless
            $l[] = "(|(displayName=*$token*)(cn=*$token*)(up1BirthName=*$token*))";
    }

    $r = array();
    foreach ($l as $cond)
      $r[] = ldapAnd(array_merge([$cond], $restriction));    
    return $r;
}
function staffFaculty_filter() {
    return "(|(eduPersonAffiliation=staff)(eduPersonAffiliation=faculty))";
}

function GET_extra_people_filter_from_params() {
  $filters = array();
  $filters_not = array();
  foreach (array("eduPersonAffiliation", "supannEntiteAffectation", "description") as $attr) {
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

function isStaffOrFaculty($uid) {
    return isPersonMatchingFilter($uid, staffFaculty_filter());
}

function searchPeopleRaw($filter, $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows) {
    global $PEOPLE_DN, $SEARCH_TIMELIMIT;
    if (!$allowListeRouge) {
	// we need the attr to anonymize people having supannListeRouge=TRUE
	$wanted_attrs['supannListeRouge'] = 'supannListeRouge';
    }
    $r = getLdapInfoMultiFilters($PEOPLE_DN, $filter, $wanted_attrs, $KEY_FIELD, $maxRows, $SEARCH_TIMELIMIT);
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
	$r[$attr_raw] = $v;
    }
    return $r;
}

function attrRestrictions($allowExtendedInfo = 0) {
    global $isTrustedIp;
    return
        array('allowListeRouge' => $allowExtendedInfo > 0 || @$isTrustedIp || GET_uid() && isStaffOrFaculty(GET_uid()),
        'allowMailForwardingAddress' => $allowExtendedInfo > 1,
        'allowEmployeeType' => $allowExtendedInfo > 1,
        );
}

function searchPeople($filter, $attrRestrictions, $wanted_attrs, $KEY_FIELD, $maxRows) {
    $allowListeRouge = @$attrRestrictions['allowListeRouge'];
    $wanted_attrs_raw = wanted_attrs_raw($wanted_attrs);
    $r = searchPeopleRaw($filter, $allowListeRouge, $wanted_attrs_raw, $KEY_FIELD, $maxRows);
    foreach ($r as &$user) {
      if (!@$attrRestrictions['allowEmployeeType'])
	  userHandleSpecialAttributePrivacy($user);
      if (!@$attrRestrictions['allowMailForwardingAddress'])
	  anonymizeUserMailForwardingAddress($user);
      userAttributesKeyToText($user, $wanted_attrs);
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

function supannEtuInscriptionAll($supannEtuInscription) {
  $r = parse_supannEtuInscription($supannEtuInscription);
  if (@$r['etape']) {
    $localEtape = removePrefix($r['etape'], '{UAI:0751717J}');
    require_once 'groups.inc.php';
    $diploma = getGroupsFromDiplomaDn(array("(ou=$localEtape)"), 1);
    if ($diploma) $r['etape'] = $diploma[0]["description"];
  }
  if (@$r['etab'] === '{UAI}0751717J') {
    unset($r['etab']);
  }
  if (@$r['cursusann']) {
    $r['cursusann'] = removePrefix($r['cursusann'], '{SUPANN}');
  }
  if (@$r['typedip']) {
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
    global $roleGeneriqueKeyToShortname;
    $r['role'] = $roleGeneriqueKeyToShortname[$r['role']];
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
  global $BASE_DN;
  $attrs = array("cn" => "key", "ou" => "name", "description" => "description");

  $or = [];
  foreach ($l as $dn) $or[] = "(entryDN=$dn)";
  return getLdapInfo($BASE_DN, ldapOr($or), $attrs);
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
  if (isset($user['supannParrainDN'])) {
      if (isset($wanted_attrs['supannParrainDN-all']))
	$user['supannParrainDN-all'] = structureAll(rdnToSupannCodeEntites($user['supannParrainDN']));
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
  global $UP1_ROLES_DN, $PEOPLE_DN;
  
  $roles = array();
  $rdn = "uid=" . $user['uid'] . ",$PEOPLE_DN";
  foreach (array('manager', 'roleOccupant', 'secretary') as $role) {
    $filter = "(&(objectClass=up1Role)($role=$rdn))";
    foreach (getLdapInfo($UP1_ROLES_DN, $filter, array("mail" => "mail", "seeAlso" => "seeAlso")) as $e) {
      $e['role'] = $role;
      $roles[] = $e;
    }
  }
  return $roles;
}

?>
