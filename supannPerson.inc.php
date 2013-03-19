<?php

require_once ('./common.inc.php');
require_once ('./tables.inc.php');

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
function staffFaculty_filter() {
    return "(|(eduPersonAffiliation=staff)(eduPersonAffiliation=faculty))";
}

function GET_extra_people_filter_from_params() {
  $filters = array();
  $filters_not = array();
  foreach (array("eduPersonAffiliation", "supannEntiteAffectation") as $attr) {
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
  return computeFilter($filters, false) . computeFilter($filters_not, true);
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

function wanted_attrs_raw($wanted_attrs) {
    $r = array();
    foreach ($wanted_attrs as $attr => $v) {
	$attr_raw = preg_replace('/-.*/', '', $attr);
	$r[$attr_raw] = $v;
    }
    return $r;
}

function searchPeople($filter, $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows) {
    $wanted_attrs_raw = wanted_attrs_raw($wanted_attrs);
    $r = searchPeopleRaw($filter, $allowListeRouge, $wanted_attrs_raw, $KEY_FIELD, $maxRows);
    foreach ($r as &$user) {
      userHandleSpecialAttributePrivacy($user);
      userAttributesKeyToText($user, $wanted_attrs);
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

function userAttributesKeyToText(&$user, $wanted_attrs) {
  $supannEntiteAffectation = getAndUnset($user, 'supannEntiteAffectation');
  if ($supannEntiteAffectation) {
      if (isset($wanted_attrs['supannEntiteAffectation']))
	  // deprecated
	  $user['supannEntiteAffectation'] = structureShortnames($supannEntiteAffectation);
      if (isset($wanted_attrs['supannEntiteAffectation-ou']))
	  $user['supannEntiteAffectation-ou'] = structureShortnames($supannEntiteAffectation);
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

?>