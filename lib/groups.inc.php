<?php

require_once ('lib/common.inc.php');
require_once ('config/config-groups.inc.php');

function groups_filters($token) {
  $words_filter = wordsFilter(array('description', 'ou'), $token);
  return array("(cn=$token)", "(&" . $words_filter . "(cn=*))");
}
function structures_filters($token) {
  $words_filter = wordsFilter(array('description', 'ou'), $token);
  return array("(supannCodeEntite=$token)", "(&(ou=$token)(supannCodeEntite=*))", "(&" . $words_filter . "(supannCodeEntite=*))");
}
function diploma_filters($token, $filter_attrs) {
  $r = array();
  if (in_array('ou', $filter_attrs))
      $r[] = "(ou=$token)";

  if (in_array('description', $filter_attrs) ||
      in_array('displayName', $filter_attrs)) {
      $prefix = in_array('displayName', $filter_attrs) ? '*-' : null;
      $r[] = wordsFilterRaw(array('description' => $prefix), $token);
  }
  return $r;
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

function GET_extra_group_filter_from_params() {
  $r = array(
       // default value
      'allStructures' => false,
  );
  foreach (array("category") as $attr) {
    $in = GET_or_NULL("filter_$attr");
    $out = GET_or_NULL("filter_not_$attr");
    $r[$attr] = computeFilterRegex($in, $out);
  }
  if (preg_match("/structures/", GET_or("filter_category", ""))) {
      // special case
      $r['allStructures'] = true;
  }
  $filter_attrs = GET_or_NULL("group_filter_attrs");
  if ($filter_attrs) {
      $r["filter_attrs"] = explode(',', $filter_attrs);
  } else {
      $r["filter_attrs"] = array('ou', 'description');
  }
  return $r;
}

function computeFilterRegex($in, $out) {
  $inQ = str_replace('\|', '|', preg_quote($in, '/'));
  $outQ = str_replace('\|', '|', preg_quote($out, '/'));
  return '/^' . ($outQ ? "(?!$outQ)" : '') . ($inQ ? "($inQ)$" : '')  . '/';
}

function get_businessCategories($l) {
  $r = array();
  foreach ($l as $e) $r[] = $e["businessCategory"];
  return array_unique($r);
}

function isPersonnel($user) {
  global $AFFILIATIONS_PERSONNEL;
  if (!isset($user["eduPersonAffiliation"])) return false;
  foreach ($user["eduPersonAffiliation"] as $affiliation) {
    if (in_array($affiliation, $AFFILIATIONS_PERSONNEL)) return true;
  }
  return false;
}

function getUserGroups($uid) {
    $groups = getGroupsFromGroupsDn(array(member_filter($uid)));

    global $PEOPLE_DN;
    $attrs["supannEntiteAffectation"] = "MULTI";
    $attrs["eduPersonAffiliation"] = "MULTI";
    $attrs["eduPersonOrgUnitDN"] = "MULTI";
    $user = getFirstLdapInfo($PEOPLE_DN, "(uid=$uid)", $attrs);
    if (!$user) return $groups;

    if (isset($user["eduPersonOrgUnitDN"])) {	
	$groups_ = getGroupsFromDiplomaEntryDn($user["eduPersonOrgUnitDN"]);
	$groups = array_merge($groups, $groups_);
    }
    if (isset($user["supannEntiteAffectation"])) {
        $filter = computeOneFilter('supannCodeEntite', implode('|', $user["supannEntiteAffectation"]));
        $groupsStructuresAll = getGroupsFromStructuresDn(array($filter), 0, 'allStructures');
	$groupsStructures = array_filter($groupsStructuresAll, 'structurePedagogyResearch');
	if (isPersonnel($user)) {
	  foreach (get_businessCategories($groupsStructuresAll) as $cat) {
	    $g = businessCategoryGroup($cat);
	    if ($g) $groups[] = $g;
	  }
	  $groups = array_merge($groups, remove_businessCategory($groupsStructures));
	}
    } else {
        $groupsStructures = array();
    }
    if (isset($user["eduPersonAffiliation"])) {
      $groups_ = getGroupsFromAffiliations($user["eduPersonAffiliation"], $groupsStructures);
      $groups = array_merge($groups, $groups_);
    }

    return $groups;
}

function groupsNotPedagogyResearchStructures($map) {  
  return !preg_match("/^employees\.(pedagogy|research|affiliation)\./", $map["key"]);
}

function structurePedagogyResearch($map) {  
  return $map["businessCategory"] === 'pedagogy' || $map["businessCategory"] === "research" || $map["businessCategory"] === 'library';
}

function removeStructureOrganization($map) {  
  return $map["businessCategory"] !== 'organization';
}

function getGroupsFromGroupsDnRaw($filters, $sizelimit = 0, $timelimit = 0) {
  global $GROUPS_DN, $GROUPS_ATTRS;
  $r = getLdapInfoMultiFilters($GROUPS_DN, $filters, $GROUPS_ATTRS, "key", $sizelimit, $timelimit);
  $r = array_filter($r, 'groupsNotPedagogyResearchStructures');
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

function getGroupsFromStructuresDn($filters, $sizelimit = 0, $all = false, $attrs = array()) {
  $r = getGroupsFromStructuresDnAll($filters, $sizelimit, $attrs);
  if ($all) {
      if (!isset($_GET["with_organization"]))
      $r = array_filter($r, 'removeStructureOrganization');
  } else {
      $r = array_filter($r, 'structurePedagogyResearch');
  }
  return $r;
}

function getGroupsFromStructuresDnAll($filters, $sizelimit, $attrs) {
    global $STRUCTURES_DN, $STRUCTURES_ATTRS;
    $r = getLdapInfoMultiFilters($STRUCTURES_DN, $filters, $STRUCTURES_ATTRS, "key", $sizelimit);
    foreach ($r as &$map) {
      if (in_array('roles', $attrs) || isset($_GET["with_organization"])) {
          $map["roles"] = structureRoles($map["key"]);
      }
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
	      continue; //$is_prev = true;
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
    return preg_replace("/ou=(.*)," . preg_quote($ALT_STRUCTURES_DN,'/') . "/", 
			"supannCodeEntite=$1,$STRUCTURES_DN", $seeAlso);
}
function getGroupFromSeeAlso($seeAlso, $allStructures = false, $attrs = array()) {
    global $GROUPS_DN, $STRUCTURES_DN;

    $seeAlso = normalizeSeeAlso($seeAlso);

    if (contains($seeAlso, $GROUPS_DN))
	$groups = getGroupsFromGroupsDnRaw(array("(entryDN=$seeAlso)"), 1, 1);
    else if (contains($seeAlso, $STRUCTURES_DN)) {
    $groups = getGroupsFromStructuresDn(array("(entryDN=$seeAlso)"), 1, $allStructures, $attrs);
    } else
	$groups = getGroupsFromDiplomaEntryDn(array($seeAlso));

    if ($groups && $groups[0])
    	return $groups[0];
    else
	return null;
}

function getNameFromSeeAlso($seeAlso) {
    $group = getGroupFromSeeAlso($seeAlso, 'allStructures');
    return $group ? $group["name"] : '';
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

	global $MAX_PARENTS_IN_DESCRIPTION;
	if (count($l) > $MAX_PARENTS_IN_DESCRIPTION) {
	  $l = array_slice($l, 0, $MAX_PARENTS_IN_DESCRIPTION);
	  $l[] = "Ce groupe est rattaché à un plus grand nombre de groupes non listés ici.";
	}
	$g["description"] = join("<br>\n", $l);
	unset($g["seeAlso"]);
    }
}

function normalizeNameGroupFromStructuresDn(&$map) {
    if (!@$map["name"]) return;
    $shortName = $map["name"];
    $name = $map["description"];

    $name = preg_replace("/^UFR(\d+)/", "UFR $1", $name); // normalize UFRXX into "UFR XX"

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

function groupIsStudentsOnly($key) {
    return in_array(groupKeyToCategory($key), array('gpelp', 'gpetp', 'elp', 'diploma'));
}

function groupKey2entryDn($key) {
  global $GROUPS_DN, $DIPLOMA_DN, $DIPLOMA_PREV_DN, $STRUCTURES_DN;

  if ($cn = removePrefixOrNULL($key, "groups-")) {
    return "cn=$cn,$GROUPS_DN";
  } else if ($supannCodeEntite = removePrefixOrNULL($key, "structures-")) {
    return "supannCodeEntite=$supannCodeEntite,$STRUCTURES_DN";
  } else if ($diploma = removePrefixOrNULL($key, "diploma-")) {
    return "ou=$diploma,$DIPLOMA_DN";
  } else if ($diploma = removePrefixOrNULL($key, "diplomaPrev-")) {
    return "ou=$diploma,$DIPLOMA_PREV_DN";
  } else {
    return null;
  }
}

function entryDn2groupKey($entryDN, $affiliation = '') {
    global $GROUPS_DN, $STRUCTURES_DN;
    global $DIPLOMA_DN, $DIPLOMA_PREV_DN;
    $entryDN = normalizeSeeAlso($entryDN);

    if (!preg_match("/=([^,]*)/", $entryDN, $matches)) {
        return null;
    }
    $key = $matches[1];

    if (contains($entryDN, $GROUPS_DN))
        return "groups-$key";
    else if (contains($entryDN, $STRUCTURES_DN))
	return $affiliation ? "structures-$key-affiliation-$affiliation" : "structures-$key";
    else if (contains($entryDN, $DIPLOMA_DN))
	return "diploma-$key";
    else if (contains($key, $DIPLOMA_PREV_DN))
	return "diplomaPrev-$key";
    else
	return null;
}

function getGroupFromKey($key, $allStructures, $attrs) {
  if ($supannCodeEntite = removePrefixOrNULL($key, "structures-")) {

    // handle key like structures-U05-affiliation-student:
    if (preg_match('/(.*)-affiliation-(.*)/', $supannCodeEntite, $matches)) {
      $supannCodeEntite = $matches[1];
      $affiliation = $matches[2];

      $structure = getGroupFromKey("structures-$supannCodeEntite", $allStructures, $attrs);
      return structureAffiliationGroup($structure, $affiliation);
    }
  }

  if ($affiliation = removePrefixOrNULL($key, "affiliation-")) {
      return affiliationGroup($affiliation);
  }

  if ($businessCategory = removePrefixOrNULL($key, "businessCategory-")) {
      return businessCategoryGroup($businessCategory);
  }

  if ($entryDn = groupKey2entryDn($key)) {
      return getGroupFromSeeAlso($entryDn, $allStructures, $attrs);
  }

  fatal("invalid group key $key");
}

function groupKey2parentKey($key) {
  if ($supannCodeEntite = removePrefixOrNULL($key, "structures-")) {

    // handle key like structures-U05-affiliation-teacher:
    if (preg_match('/(.*)-affiliation-(.*)/', $supannCodeEntite, $matches)) {
      $supannCodeEntite = $matches[1];
      $affiliation = $matches[2];
      $r = array("affiliation-$affiliation");
      // structures-U05 is for personnel only
      if ($affiliation !== 'student') $r[] = "structures-$supannCodeEntite";
      return $r;
    }
  } 
  if ($affiliation = removePrefixOrNULL($key, "affiliation-")) {
    return array();
  }

  if ($businessCategory = removePrefixOrNULL($key, "businessCategory-")) {
    return array();
  }

  if ($entryDn = groupKey2entryDn($key)) {
    $g = getLdapDN($entryDn, array("seeAlso" => "MULTI", "supannCodeEntiteParent" => "MULTI", "up1Flags" => "up1Flags"), 1);
    $affiliation = groupIsStudentsOnly($key) ? 'student' : '';
    $r = array();
    if ($g && isset($g["supannCodeEntiteParent"]) && getAndUnset($g, "up1Flags") === "included") {
        if (!isset($g["seeAlso"])) $g["seeAlso"] = [];
        foreach ($g["supannCodeEntiteParent"] as $parent) {
            $g["seeAlso"][] = groupKey2entryDn("structures-$parent");
        }
    }
    if ($g && isset($g["seeAlso"])) {
	foreach ($g["seeAlso"] as $seeAlso) {
	    $r[] = entryDn2groupKey($seeAlso, $affiliation);
	}
    }
    return $r;
  }

  fatal("invalid group key $key");
}

function group2parentKey($key, $group) {
  $r = groupKey2parentKey($key);
  if (!$r) {
    if (preg_match('/groups-employees\.(\w+)\./', $key, $matches)) {
      // groups generated by GSHcreator
      $r[] = "businessCategory-" . $matches[1];
    } else if (startsWith($key, "structures-") && isset($group['businessCategory'])) {
      // "standard" structures/composantes
      $r[] = "businessCategory-" . $group['businessCategory'];
    }
  }
  return $r;
}

function getSuperGroups(&$all_groups, $key, $depth, $restriction) {
  $group = getGroupFromKey($key, true, array());
  add_group_category($group);

  $category_filter = $restriction['category']; 
  $group['superGroups'] = array_filter(group2parentKey($key, $group), function ($k) use ($category_filter) {
      return preg_match($category_filter, groupKeyToCategory($k));
  });
  $all_groups[$key] = $group;

  $superGroups = $group['superGroups'];
  if ($depth > 0 && $superGroups) {
    foreach ($superGroups as $k) {
      getSuperGroups($all_groups, $k, $depth -1, $restriction);
    }
  }
}

function getSubGroups_one($key) {
  global $ALT_STRUCTURES_DN, $DIPLOMA_DN, $DIPLOMA_PREV_DN;

  $all_groups = array();
  if ($cn = removePrefixOrNULL($key, "groups-")) {
    $all_groups = getGroupsFromGroupsDn(array(seeAlso_filter($cn)));
  } else if ($supannCodeEntite = removePrefixOrNULL($key, "structures-")) {

    // handle key like structures-U05-affiliation-student:
    if (preg_match('/(.*)-affiliation-(.*)/', $supannCodeEntite, $matches)) {
      $supannCodeEntite = $matches[1];
      $affiliation = $matches[2];
    } else {
      $affiliation = null;
    }

    $groupsStructures = getGroupsFromStructuresDn(array("(supannCodeEntiteParent=$supannCodeEntite)"), 0, 'allStructures');
    if ($affiliation)
      $groupsStructures = getGroupsFromAffiliationAndStructures($affiliation, $groupsStructures);  

    $ou = "ou=$supannCodeEntite," . $ALT_STRUCTURES_DN;
    $groups = getGroupsFromSeeAlso($ou);
    $all_groups = array_merge($groupsStructures, $groups);
  } else if ($diploma = removePrefixOrNULL($key, "diploma-")) {
    $ou = "ou=$diploma," . $DIPLOMA_DN;
    $all_groups = getGroupsFromSeeAlso($ou);
  } else if ($diploma = removePrefixOrNULL($key, "diplomaPrev-")) {
    $ou = "ou=$diploma," . $DIPLOMA_PREV_DN;
    $all_groups = getGroupsFromSeeAlso($ou);
  } else if ($affiliation = removePrefixOrNULL($key, "affiliation-")) {
    $groupsStructures = getGroupsFromStructuresDn(array("(businessCategory=pedagogy)"));
    $all_groups = getGroupsFromAffiliationAndStructures($affiliation, $groupsStructures);  
  } else {
    error("invalid group key $key");
  }
  remove_rawKey_and_modifyTimestamp($all_groups);
  return $all_groups;
}

function getSubGroups($key, $depth, $restriction) {
  $groups = getSubGroups_one($key);
  add_groups_category($groups);
  $groups = apply_category_restriction($groups, $restriction['category']);
  if ($depth > 0) {
    foreach ($groups as &$g) {
      $subGroups = getSubGroups($g["key"], $depth-1, $restriction);
      if ($subGroups) $g["subGroups"] = $subGroups;
    }
  }
  return $groups;
}

function apply_category_restriction($groups, $category_filter) {
  return array_filter($groups, function ($g) use ($category_filter) {
      return preg_match($category_filter, $g["category"]);
  });
}

function add_group_category(&$g) {
  $g["category"] = groupKeyToCategory($g["key"]);
}
function add_groups_category(&$groups) {
  foreach ($groups as &$g) add_group_category($g);
}

function affiliationGroup($affiliation) {
    global $AFFILIATION2TEXT;
    if (!isset($AFFILIATION2TEXT[$affiliation])) return null;

    $text = $AFFILIATION2TEXT[$affiliation];
    $name = "Tous les " . $text;
    return array("key" => "affiliation-" . $affiliation, 
		 "name" => $name, "description" => $name);
}

function businessCategoryGroup($businessCategory) {
    global $BUSINESSCATEGORY2TEXT;
    if (!isset($BUSINESSCATEGORY2TEXT[$businessCategory])) return null;

    $name = $BUSINESSCATEGORY2TEXT[$businessCategory];
    return array("key" => "businessCategory-" . $businessCategory, 
		 "name" => $name, "description" => $name);
}

function businessCategoryGroups() {
    global $BUSINESSCATEGORY2TEXT;
    return array_map('businessCategoryGroup', array_keys($BUSINESSCATEGORY2TEXT));
}

function structureAffiliationGroup($groupStructure, $affiliation) {
    global $AFFILIATION2TEXT;
    $text = $AFFILIATION2TEXT[$affiliation];
    $suffix = " (" . $text . ")";

    $description = ''; //$groupStructure["description"] . $suffix;
    return array("key" => $groupStructure["key"] . "-affiliation-" . $affiliation, 
		 "name" => $groupStructure["name"] . $suffix, 
		 "description" => $description);
}

function getGroupsFromAffiliations($affiliations, $groupsStructures) {
  $r = array();
  foreach ($affiliations as $affiliation) {
    $affiliationGroup = affiliationGroup($affiliation);
    if ($affiliationGroup) {
      $r = array_merge($r, getGroupsFromAffiliationAndStructures($affiliation, $groupsStructures));

      $r[] = $affiliationGroup;
    }
  }
  return $r;
}

function getGroupsFromAffiliationAndStructures($affiliation, $groupsStructures) {
  $r = array();
  if ($groupsStructures && ($affiliation == "student" || $affiliation == "faculty")) {
    foreach ($groupsStructures as $group) {
	if ($group["businessCategory"] == "pedagogy")
	    $r[] = structureAffiliationGroup($group, $affiliation);
    }
  }
  return $r;
}

function remove_rawKey_and_modifyTimestamp(&$r) {
    remove_rawKey($r);
    remove_modifyTimestamp($r);
}

function echoJsonSimpleGroups($groups) {
    remove_rawKey_and_modifyTimestamp($groups);
    echoJson($groups);
}


function searchGroups($token, $maxRows, $restriction, $attrs) {
  $category_filter = $restriction['category'];
  $filter_attrs = $restriction['filter_attrs'];

  $groups = array();
  if (preg_match($category_filter, 'groups')) {
    $groups = getGroupsFromGroupsDn(groups_filters($token), $maxRows);
  }
  $structures = array();
  if (preg_match($category_filter, 'structures')) {
    $structures = getGroupsFromStructuresDn(structures_filters($token), $maxRows, $restriction['allStructures']);
    if (!in_array('businessCategory', $attrs)) {
        $structures = remove_businessCategory($structures);
    }
  }
  $diploma = array();
  if (preg_match($category_filter, 'diploma')) {
      // GLPI UP1 #77226
    if (preg_match('/^diplomaPrev-(.*)/', $token, $m)) {
        $diploma = getGroupsFromDiplomaDnOrPrev(diploma_filters($m[1], $filter_attrs), true, $maxRows);        
    } else {
    $diploma = getGroupsFromDiplomaDn(diploma_filters($token, $filter_attrs), $maxRows);
  }
  $all_groups = array_merge($groups, $structures, $diploma);

  $all_groups = exact_match_first($all_groups, $token);
  add_groups_category($all_groups);
  remove_rawKey_and_modifyTimestamp($all_groups);
  
  return $all_groups;
}

function structureRoles($supannCodeEntite) {
    $maxRows = 30;
    $filter = "(supannRoleEntite=*[code=$supannCodeEntite]*)";
    $wanted_attrs = array("uid" => "uid", "displayName" => "displayName", "supannRoleEntite" => "MULTI", "supannCivilite" => "supannCivilite");
    require_once('lib/supannPerson.inc.php');
    isCasAuthenticated();
    $all = searchPeople(array($filter), attrRestrictions(), $wanted_attrs, 'uid', $maxRows);    
    $r = [];
    foreach ($all as &$user) {
        $weight = _transform_supannRoleEntite_into_supannRoleGenerique($user, $supannCodeEntite);
        $r[$weight . ":" . $user['uid']] = $user;
    }
    ksort($r);
    return array_values($r);
}

function _transform_supannRoleEntite_into_supannRoleGenerique(&$user, $supannCodeEntite) {
    $l = @$user['supannRoleEntite'];
    unset($user['supannRoleEntite']);
    if ($l) {
        $weights = [];
        $roles = [];
        foreach ($l as $e) {
            $r = parse_composite_value($e);
            if (@$r["code"] == $supannCodeEntite) {
                global $roleGeneriqueKeyToAll;
                $role = $roleGeneriqueKeyToAll[$r['role']];
                $roles[] = all_to_name_with_gender($role, $user);
                if (isset($role['weight'])) $weights[$role['weight']] = 1;
            }
        }
        $user['supannRoleGenerique'] = $roles;
        ksort($weights);
        return implode('-', array_keys($weights));
    }
    return '';
}

?>
