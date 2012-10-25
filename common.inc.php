<?php

require ('./config.inc.php');
require ('./tables.inc.php');

function GET_ldapFilterSafe($name) {
    return ldap_escape_string($_GET[$name]);
}
function GET_ldapFilterSafe_or_NULL($name) {
    return isset($_GET[$name]) ? ldap_escape_string($_GET[$name]) : NULL;
}
function GET_or_NULL($name) {
  return isset($_GET[$name]) ? $_GET[$name] : NULL;
}

function GET_uid() {
  return isset($_SERVER["HTTP_CAS_USER"]) ? $_SERVER["HTTP_CAS_USER"] : ''; // CAS-User
}

function people_filters($token, $restriction = '') {
    $exactOr = "(uid=$token)(sn=$token)";
    if (preg_match('/(.*?)@(.*)/', $token, $matches)) {
	$exactOr .= "(mail=$token)";
	$exactOr .= "(&(uid=$matches[1])(mail=*@$matches[2]))";
    }
    $r = array("(&(|$exactOr)(eduPersonAffiliation=*)$restriction)");
    if (strlen($token) > 3) 
	// too short strings are useless
	$r[] = "(&(eduPersonAffiliation=*)(|(displayName=*$token*)(cn=*$token*))$restriction)";
    return $r;
}
function groups_filters($token) {
  return array("(cn=$token)", "(&(|(description=*$token*)(ou=*$token*))(cn=*))");
}
function structures_filters($token) {
  return array("(supannCodeEntite=$token)", "(&(|(description=*$token*)(ou=*$token*))(supannCodeEntite=*))");
}
function diploma_filters($token) {
  return array("(ou=$token)", "(description=*$token*)");
}
function member_filter($uid) {
  global $PEOPLE_DN;
  return "member=uid=$uid,$PEOPLE_DN";
}
function responsable_filter($uid) {
  global $PEOPLE_DN;
  return "(|(supannGroupeAdminDN=uid=$uid,$PEOPLE_DN)(supannGroupeLecteurDN=uid=$uid,$PEOPLE_DN))";
}
function seeAlso_filter($cn) {
  global $GROUPS_DN;
  return "seeAlso=cn=$cn,$GROUPS_DN";
}
function staffFaculty_filter() {
    return "(|(eduPersonAffiliation=staff)(eduPersonAffiliation=faculty))";
}

function isStaffOrFaculty($uid) {
    global $PEOPLE_DN;
    return existsLdap($PEOPLE_DN, "(&(uid=$uid)" . staffFaculty_filter() . ")");
}

function searchPeopleRaw($filter, $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows) {
    global $PEOPLE_DN, $SEARCH_TIMELIMIT;
    if (!$allowListeRouge) {
	// we need the attr to anonymize people having supannListeRouge=TRUE
	$wanted_attrs['supannListeRouge'] = 'supannListeRouge';
    }
    $r = getLdapInfoMultiFilters($PEOPLE_DN, $filter, $wanted_attrs, $KEY_FIELD, $maxRows, $SEARCH_TIMELIMIT);
    foreach ($r as &$e) {
	if (!isset($e["supannListeRouge"])) continue;
	$supannListeRouge = $e["supannListeRouge"];
	unset($e["supannListeRouge"]);
	if ($supannListeRouge == "TRUE") anonymizeUser($e, $wanted_attrs);
    }
    return $r;
}

function searchPeople($filter, $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows) {
    $r = searchPeopleRaw($filter, $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows);
    foreach ($r as &$user) {
      userHandleSpecialAttributePrivacy($user);
      userAttributesKeyToText($user);
    }
    return $r;
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

function structureShortnames($keys) {
    GLOBAL $structureKeyToShortname, $showErrors;
    $shortnames = array();
    foreach ($keys as &$key) {
      if (isset($structureKeyToShortname[$key]))
	$shortnames[] = $structureKeyToShortname[$key];
      else if ($showErrors)
	$shortnames[] = "invalid structure $key";
    }
    return empty($shortnames) ? NULL : $shortnames;
}

function userHandleSpecialAttributePrivacy(&$user) {
  if (isset($user['employeeType']) || isset($user['departmentNumber']))
    if (!in_array($user['eduPersonPrimaryAffiliation'], array('teacher', 'emeritus', 'researcher'))) {
      unset($user['employeeType']); // employeeType is private for staff & student
      unset($user['departmentNumber']); // departmentNumber is not interesting for staff & student
    }
}

function userAttributesKeyToText(&$user) {
  if (isset($user['supannEntiteAffectation'])) {
    $user['supannEntiteAffectation'] = structureShortnames($user['supannEntiteAffectation']);
  }
  if (isset($user['supannRoleGenerique'])) {
    global $roleGeneriqueKeyToShortname;
    $user['supannRoleGenerique'] = $roleGeneriqueKeyToShortname[$user['supannRoleGenerique']];
  }
  if (isset($user['supannEtablissement'])) {
    if (in_array($user['supannEtablissement'], array('{UAI}0751717J', "{autre}"))) {
      unset($user['supannEtablissement']); // only return interesting supannEtablissement (ie not Paris1)
    } else {
      global $etablissementKeyToShortname;
      $user['supannEtablissement'] = mayRemap($etablissementKeyToShortname, $user['supannEtablissement']);
    }
  }
}

function getUserGroups($uid) {
    $groups = getGroupsFromGroupsDn(array(member_filter($uid)));

    global $PEOPLE_DN;
    $attrs = identiqueMap(array("supannEntiteAffectation"));
    $attrs["eduPersonAffiliation"] = "MULTI";
    $attrs["eduPersonOrgUnitDN"] = "MULTI";
    $user = getFirstLdapInfo($PEOPLE_DN, "(uid=$uid)", $attrs);
    if (!$user) return $groups;

    if (isset($user["eduPersonOrgUnitDN"])) {	
	$groups_ = getGroupsFromDiplomaEntryDn($user["eduPersonOrgUnitDN"]);
	$groups = array_merge($groups, $groups_);
    }
    if (isset($user["supannEntiteAffectation"])) {
	$key = $user["supannEntiteAffectation"];
	$groupsStructures = getGroupsFromStructuresDn(array("(supannCodeEntite=$key)"), 1);
	$groups = array_merge($groups, remove_businessCategory($groupsStructures));
    } else {
        $groupsStructures = array();
    }
    if (isset($user["eduPersonAffiliation"])) {
      $groups_ = getGroupsFromAffiliations($user["eduPersonAffiliation"], $groupsStructures);
      $groups = array_merge($groups, $groups_);
    }

    return $groups;
}

function groupsNotCreatedByGrouper($map) {
    return !startsWith($map["key"], "structures:");
}

function getGroupsFromGroupsDn($filters, $sizelimit = 0) {
  global $GROUPS_DN, $GROUPS_ATTRS;
  $r = getLdapInfoMultiFilters($GROUPS_DN, $filters, $GROUPS_ATTRS, "key", $sizelimit);
  $r = array_filter($r, 'groupsNotCreatedByGrouper');
  foreach ($r as &$map) {
      $map["rawKey"] = $map["key"];
      $map["key"] = "groups-" . $map["key"];
      if (!isset($map["name"])) $map["name"] = $map["rawKey"];
  }
  return $r;
}

function getGroupsFromStructuresDn($filters, $sizelimit = 0) {
    global $STRUCTURES_DN, $STRUCTURES_ATTRS;
    $r = getLdapInfoMultiFilters($STRUCTURES_DN, $filters, $STRUCTURES_ATTRS, "key", $sizelimit);
    foreach ($r as &$map) {
      $map["rawKey"] = $map["key"];
      $map["key"] = "structures-" . $map["key"];
    }
    return $r;
}

function getGroupsFromDiplomaEntryDn($eduPersonOrgUnitDNs) {
    global $DIPLOMA_DN, $DIPLOMA_PREV_DN;
    $r = array();
    foreach ($eduPersonOrgUnitDNs as $key) {
	  if (contains($key, $DIPLOMA_DN))
	      $is_prev = false;
	  else if (contains($key, $DIPLOMA_PREV_DN))
	      $is_prev = true;
	  else
	      continue;

	  $groups_ = getGroupsFromDiplomaDnOrPrev(array("(entryDN=$key)"), $is_prev, 1);
	  $r = array_merge($r, $groups_);
    }
    return $r;
}

function getGroupsFromDiplomaDn($filters, $sizelimit = 0) {
    return getGroupsFromDiplomaDnOrPrev($filters, false, $sizelimit);
}

function getGroupsFromDiplomaDnOrPrev($filters, $want_prev, $sizelimit = 0) {
    global $ANNEE_PREV, $DIPLOMA_DN, $DIPLOMA_PREV_DN, $DIPLOMA_ATTRS;
    $dn = $want_prev ? $DIPLOMA_PREV_DN : $DIPLOMA_DN;
    $r = getLdapInfoMultiFilters($dn, $filters, $DIPLOMA_ATTRS, "key", $sizelimit);
    foreach ($r as &$map) {
	$map["rawKey"] = $map["key"];
	$map["key"] = ($want_prev ? "diplomaPrev" : "diploma") . "-" . $map["key"];

	if ($want_prev) $map["description"] = '[' . $ANNEE_PREV . '] ' . $map["description"];
	$map["name"] = $map["description"]; // removePrefix($map["description"], $map["rawKey"] . " - ");
    }
    return $r;
}

function getGroupsFromSeeAlso($seeAlso) {
    $diploma = getGroupsFromDiplomaDn(array("(seeAlso=$seeAlso)"));
    $groups = getGroupsFromGroupsDn(array("(seeAlso=$seeAlso)"));
    return array_merge($diploma, $groups);
}

function groupKeyToCategory($key) {
    if (preg_match('/^(structures|affiliation|diploma)-/', $key, $matches) ||
	preg_match('/^groups-(gpelp|gpetp)\./', $key, $matches))
	return $matches[1];
    else if (startsWith($key, 'groups-mati'))
	return 'elp';
    else if (startsWith($key, 'groups-'))
	return 'local';
    else
	return null;
}

function add_group_category(&$groups) {
    foreach ($groups as &$g) {
	$g["category"] = groupKeyToCategory($g["key"]);
    }
}

function getGroupsFromAffiliations($affiliations, $groupsStructures) {
  $r = array();
  foreach ($affiliations as $affiliation) {
    global $AFFILIATION2TEXT;
    if (isset($AFFILIATION2TEXT[$affiliation])) {
      $r = array_merge($r, getGroupsFromAffiliationAndStructures($affiliation, $groupsStructures));

      $text = $AFFILIATION2TEXT[$affiliation];
      $name = "Tous les " . $text;
      $r[] = array("key" => "affiliation-" . $affiliation, 
		   "name" => $name, "description" => $name);
    }
  }
  return $r;
}

function getGroupsFromAffiliationAndStructures($affiliation, $groupsStructures) {
  $r = array();
  if ($groupsStructures && ($affiliation == "student" || $affiliation == "faculty")) {
    global $AFFILIATION2TEXT;
    $text = $AFFILIATION2TEXT[$affiliation];
    $suffix = " (" . $text . ")";
    foreach ($groupsStructures as $group) {
	if ($group["businessCategory"] == "pedagogy")
	    $r[] = array("key" => $group["key"] . "-affiliation-" . $affiliation, 
			 "name" => $group["name"] . $suffix, 
			 "description" => $group["description"] . $suffix);
    }
  }
  return $r;
}

function getLdapInfoMultiFilters($base, $filters, $attributes_map, $uniqueField, $sizelimit = 0, $timelimit = 0) {
  $rr = array();
  foreach ($filters as $filter) {
    $rr[] = getLdapInfo($base, $filter, $attributes_map, $sizelimit, $timelimit);
  }
  $r = mergeArraysNoDuplicateKeys($rr, $uniqueField);
  if ($sizelimit > 0)
      $r = array_splice($r, 0, $sizelimit);
  return $r;
}

function getFirstLdapInfo($base, $filter, $attributes_map) {
  $r = getLdapInfo($base, $filter, $attributes_map, 1);
  return $r ? $r[0] : NULL;
}

function existsLdap($base, $filter) {
  $r = getLdapInfo($base, $filter, array(), 1);
  return (bool) $r;
}

function getLdapInfo($base, $filter, $attributes_map, $sizelimit = 0, $timelimit = 0) {
  global $DEBUG;

  $before = microtime(true);

  $ds = global_ldap_open();

  if ($DEBUG) error_log("searching $base for $filter");
  $search_result = @ldap_search($ds, $base, $filter, array_keys($attributes_map), 0, $sizelimit, $timelimit);
  if (!$search_result) return array();
  $all_entries = ldap_get_entries($ds, $search_result);
  if ($DEBUG) error_log("found " . $all_entries['count'] . " results");

  unset($all_entries["count"]);
  $r = array();  
  foreach ($all_entries as $entry) {
    $map = array();
    foreach ($attributes_map as $ldap_attr => $attr) {
      $ldap_attr_ = strtolower($ldap_attr);
      if (isset($entry[$ldap_attr_])) {
	$vals = $entry[$ldap_attr_];
	if ($attr == "MULTI") {
	  // no remapping, but is multi-valued attr
	  unset($vals["count"]);
	  $map[$ldap_attr] = $vals;
	} else {
	  $map[$attr] = $vals["0"];
	}
      }
    }
    $r[] = $map;
  }

  //echo sprintf("// Elapsed %f\t%3d answers for $filter on $base\n", $before - microtime(true), count($r));

  return $r;
}

function global_ldap_open() {
    global $ldapDS;
    if (!$ldapDS) {
	global $LDAP_HOST, $LDAP_BIND_DN, $LDAP_BIND_PASSWORD;
	$ldapDS = ldap_connect($LDAP_HOST);
	if (!$ldapDS) exit("error: connection to $LDAP_HOST failed");

	if (!ldap_bind($ldapDS,$LDAP_BIND_DN,$LDAP_BIND_PASSWORD)) exit("error: failed to bind using $LDAP_BIND_DN");
    }
    return $ldapDS;
}

function ensure_ldap_close() {
    global $ldapDS;
    if ($ldapDS) {
      ldap_close($ldapDS);
      $ldapDS = NULL;
    }
}

function echoJson($array) {
  ensure_ldap_close();
  header('Content-type: application/json; charset=UTF-8');
  if (isset($_GET["callback"]))
    echo $_GET["callback"] . "(" . json_encode($array) . ");";
  else
    echo json_encode($array);  
}

function echoJsonSimpleGroups($groups) {
    remove_rawKey($groups);
    remove_modifyTimestamp($groups);
    echoJson($groups);
}

function identiqueMap($list) {
    $map = array();
    foreach ($list as $e) $map[$e] = $e;
    return $map;
}

function mergeArraysNoDuplicateKeys($rr, $uniqueField) {
    $keys = array();
    $r = array();
    foreach ($rr as $one_array) {
	foreach ($one_array as $e) {
	    $key = $e[$uniqueField];
	    if (isset($keys[$key])) continue;
	    $keys[$key] = 1;
	    $r[] = $e;
	}
    }
    return $r;
}

function exact_match_first($r, $token) {
    $exact = array();
    $i = 0;
    while ($i < count($r)) {
	$e = $r[$i];
	if (in_array($token, array_values($e))) {
	    $exact[] = $e;
	    array_splice($r, $i, 1);
	} else {
	    $i++;
	}
    }
    return array_merge($exact, $r);
}

function remove_businessCategory($r) {
    foreach ($r as &$e) {
	unset($e["businessCategory"]);
    }
    return $r;
}

// after exact_match_first, rawKey can be safely removed: it is used for search token=matiXXXXX, "key" will contain groups-matiXXXXXX and won't match. "rawKey" will match!
function remove_rawKey(&$r) {
    foreach ($r as &$e) {
	unset($e["rawKey"]);
    }
}
// modifyTimestamp is only used by allGroups
function remove_modifyTimestamp(&$r) {
    foreach ($r as &$e) {
	unset($e["modifyTimestamp"]);
    }
}

function contains($hay, $needle) {
    return strpos($hay, $needle) !== false;
}

function startsWith($hay, $needle) {
  return substr($hay, 0, strlen($needle)) === $needle;
}

function removePrefix($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : $s;
}
function removePrefixOrNULL($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : NULL;
}

function error($msg) {
   header("HTTP/1.0 400 $msg");
   echo("// $msg\n");
}

// taken more mantisbt
function ldap_escape_string( $p_string ) {
  $t_find = array( '\\', '*', '(', ')', '/', "\x00" );
  $t_replace = array( '\5c', '\2a', '\28', '\29', '\2f', '\00' );

  $t_string = str_replace( $t_find, $t_replace, $p_string );

  return $t_string;
}

function mayRemap($map, $k) {
  return isset($map[$k]) ? $map[$k] : $k;
}

function array_flatten_non_rec($r) {
    return sizeof($r) > 0 ? call_user_func_array('array_merge', $r) : array();
}

?>
