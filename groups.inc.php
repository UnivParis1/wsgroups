<?php

require_once ('./common.inc.php');
require_once ('./config-groups.inc.php');

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

function getGroupsFromGroupsDnRaw($filters, $sizelimit = 0) {
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
function getGroupsFromGroupsDn($filters, $sizelimit = 0) {
    $r = getGroupsFromGroupsDnRaw($filters, $sizelimit);
    computeDescriptionsFromSeeAlso($r);
    return $r;
}

function getGroupsFromStructuresDn($filters, $sizelimit = 0) {
    global $STRUCTURES_DN, $STRUCTURES_ATTRS;
    $r = getLdapInfoMultiFilters($STRUCTURES_DN, $filters, $STRUCTURES_ATTRS, "key", $sizelimit);
    foreach ($r as &$map) {
      $map["rawKey"] = $map["key"];
      $map["key"] = "structures-" . $map["key"];
      normalizeNameGroupFromStructuresDn($map);
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

function normalizeSeeAlso($seeAlso) {
    global $ALT_STRUCTURES_DN, $STRUCTURES_DN;
    return preg_replace("/ou=(.*)," . preg_quote($ALT_STRUCTURES_DN) . "/", 
			"supannCodeEntite=$1,$STRUCTURES_DN", $seeAlso);
}
function getNameFromSeeAlso($seeAlso) {
    global $GROUPS_DN, $STRUCTURES_DN;

    $seeAlso = normalizeSeeAlso($seeAlso);

    if (contains($seeAlso, $GROUPS_DN))
	$groups = getGroupsFromGroupsDnRaw(array("(entryDN=$seeAlso)"), 1);
    else if (contains($seeAlso, $STRUCTURES_DN)) {
	$groups = getGroupsFromStructuresDn(array("(entryDN=$seeAlso)"), 1);
    } else
	$groups = getGroupsFromDiplomaEntryDn(array($seeAlso));

    if ($groups && $groups[0])
    	return $groups[0]["name"];
    else
	return '';
}

function computeDescriptionsFromSeeAlso(&$groups) {
    $seeAlsos = array();
    foreach ($groups as $g) 
	if (isset($g["seeAlso"])) $seeAlsos[] = $g["seeAlso"];
    $seeAlsos = array_unique(array_flatten_non_rec($seeAlsos));

    $names = array();
    foreach ($seeAlsos as $seeAlso)
	$names[$seeAlso] = getNameFromSeeAlso($seeAlso);

    foreach ($groups as &$g) {
	$l = array();
	if (!isset($g["seeAlso"])) continue;

	foreach ($g["seeAlso"] as $seeAlso)
	    $l[] = $names[$seeAlso];
	sort($l);
	$g["description"] = join("<br>\n", $l);
	unset($g["seeAlso"]);
    }
}

function normalizeNameGroupFromStructuresDn(&$map) {
    $shortName = $map["name"];
    $name = $map["description"];

    $name = preg_replace("/^UFR(\d+)/", "UFR $1", $name); // normalize UFRXX into "UFR XX"

    if ($shortName && $shortName != $name && !preg_match("/^[^:]*" . preg_quote($shortName) . "\s*:/", $name)) {
	//echo "adding $shortName to $name\n";
	$name = "$shortName : $name";
    }

    //if ($shortName !== groupNameToShortname($name))
    //  echo "// different shortnames for $name: " . $shortName . " vs " . groupNameToShortname($name) . "\n";

    $map["name"] = $name;
    $map["description"] = '';
}

function groupNameToShortname($name) {
    if (preg_match('/(.*?)\s*:/', $name, $matches))
      return $matches[1];
    else
      return $name;
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

function echoJsonSimpleGroups($groups) {
    remove_rawKey($groups);
    remove_modifyTimestamp($groups);
    echoJson($groups);
}

?>
