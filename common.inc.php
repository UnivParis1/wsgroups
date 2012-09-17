<?php

require ('./config.inc.php');

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

function people_filters($token, $allowListeRouge = false, $restriction = '') {
    if (!$allowListeRouge) $restriction = $restriction . '(!(supannListeRouge=TRUE))';
    $r = array("(&(uid=$token)(eduPersonAffiliation=*)$restriction)");
    if (strlen($token) > 3) 
	// too short strings are useless
	$r[] = "(&(eduPersonAffiliation=*)(|(displayName=*$token*)(cn=*$token*))$restriction)";
    return $r;
}
function groups_filters($token) {
  return array("(cn=$token)", "(|(description=*$token*)(ou=*$token*))");
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

function getUserGroups($uid) {
    $groups = getGroupsFromGroupsDn(array(member_filter($uid)));

    global $PEOPLE_DN;
    $attrs = identiqueMap(array("supannEntiteAffectation"));
    $attrs["eduPersonAffiliation"] = "MULTI";
    $attrs["eduPersonOrgUnitDN"] = "MULTI";
    $user = getFirstLdapInfo($PEOPLE_DN, "(uid=$uid)", $attrs);
    if (!$user) return $groups;

    if (isset($user["eduPersonOrgUnitDN"])) {	
	$groups_ = getGroupsFromEduPersonOrgUnitDN($user["eduPersonOrgUnitDN"]);
	$groups = array_merge($groups, $groups_);
    }
    if (isset($user["supannEntiteAffectation"])) {
	$key = $user["supannEntiteAffectation"];
	$groupsStructures = getGroupsFromStructuresDn(array("(supannCodeEntite=$key)"), 1);
	$groups = array_merge($groups, $groupsStructures);
    }
    if (isset($user["eduPersonAffiliation"])) {
      $groups_ = getGroupsFromAffiliations($user["eduPersonAffiliation"], $groupsStructures);
      $groups = array_merge($groups, $groups_);
    }

    return $groups;
}

function getGroupsFromGroupsDn($filters, $sizelimit = 0) {
  global $GROUPS_DN, $GROUPS_ATTRS;
  $r = getLdapInfoMultiFilters($GROUPS_DN, $filters, $GROUPS_ATTRS, "key", $sizelimit);
  foreach ($r as &$map) {
      $map["rawKey"] = $map["key"];
      $map["key"] = "groups-" . $map["key"];
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

function getGroupsFromEduPersonOrgUnitDN($eduPersonOrgUnitDNs) {
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

function getGroupsFromDiplomaDnOrPrev($filters, $want_prev, $sizelimit) {
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
      $r[] = array("key" => $group["key"] . "-affiliation-" . $affiliation, 
		   "name" => $group["name"] . $suffix, 
		   "description" => $group["description"] . $suffix);
    }
  }
  return $r;
}

function getLdapInfoMultiFilters($base, $filters, $attributes_map, $uniqueField, $sizelimit = 0) {
  $rr = array();
  foreach ($filters as $filter) {
    $rr[] = getLdapInfo($base, $filter, $attributes_map, $sizelimit);
  }
  return mergeArraysNoDuplicateKeys($rr, $uniqueField);
}

function getFirstLdapInfo($base, $filter, $attributes_map) {
  $r = getLdapInfo($base, $filter, $attributes_map, 1);
  return $r ? $r[0] : NULL;
}

function existsLdap($base, $filter) {
  $r = getLdapInfo($base, $filter, array(), 1);
  return (bool) $r;
}

function getLdapInfo($base, $filter, $attributes_map, $sizelimit = 0) {
  global $DEBUG;

  $before = microtime(true);

  $ds = global_ldap_open();

  if ($DEBUG) error_log("searching $base for $filter");
  $search_result = @ldap_search($ds, $base, $filter, array_keys($attributes_map), 0, $sizelimit);
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

// after exact_match_first, rawKey can be safely removed: it is used for search token=matiXXXXX, "key" will contain groups-matiXXXXXX and won't match. "rawKey" will match!
function remove_rawKey(&$r) {
    foreach ($r as &$e) {
	unset($e["rawKey"]);
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

?>
