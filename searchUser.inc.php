<?php // -*-PHP-*-

require ('./common.inc.php');
require ('./tables.inc.php');

$token = GET_ldapFilterSafe("token");
$attrs = GET_or_NULL("attrs");
$maxRows = min(GET_or_NULL("maxRows"), 10);

$filters = array();
$filters_not = array();
foreach (array("eduPersonAffiliation") as $attr) {
  $filters[$attr] = GET_ldapFilterSafe_or_NULL("filter_$attr");
  $filters_not[$attr] = GET_ldapFilterSafe_or_NULL("filter_not_$attr");
}

$KEY_FIELD = 'uid';
$ALLOWED_MONO_ATTRS = array('uid', 'mail', 'displayName', 'cn', 'eduPersonPrimaryAffiliation', 'employeeType', 'postalAddress', 'supannRoleGenerique', 'supannEtablissement');
$ALLOWED_MULTI_ATTRS = array('supannEntiteAffectation', 'eduPersonAffiliation', 'departmentNumber');

$wanted_attrs = array();
foreach (explode(',', $attrs) as $attr) {
  if (in_array($attr, $ALLOWED_MONO_ATTRS)) {
    $wanted_attrs[$attr] = $attr;
  } else if (in_array($attr, $ALLOWED_MULTI_ATTRS)) {
    $wanted_attrs[$attr] = 'MULTI';
  } else {
    error("unknown attribute $attr. allowed attributes: " . join(",", array_merge($ALLOWED_MONO_ATTRS, $ALLOWED_MULTI_ATTRS)));
    exit;
  }
}
if (!isset($wanted_attrs[$KEY_FIELD]))
  $wanted_attrs[$KEY_FIELD] = $KEY_FIELD;

// employeeType is only allowed on some eduPersonPrimaryAffiliation
// departmentNumber is only useful for some eduPersonPrimaryAffiliation
if (isset($wanted_attrs['employeeType']) || isset($wanted_attrs['departmentNumber']))
  $wanted_attrs['eduPersonPrimaryAffiliation'] = 'eduPersonPrimaryAffiliation';

$allowListeRouge = GET_uid() && isStaffOrFaculty(GET_uid());
$restriction = computeFilter($filters, false) . computeFilter($filters_not, true);
$users = searchPeople(people_filters($token, $restriction), $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows);

function computeOneFilter($attr, $valsS) {
    $vals = explode('|', $valsS);
    $orFilter = '';
    foreach ($vals as $val)
      $orFilter .= "($attr=$val)";
    return sizeof($vals) > 1 ? "(|$orFilter)" : $orFilter;
}
function computeFilter($filters, $not) {
   $r = '';
  foreach ($filters as $attr => $vals) {
    if (!$vals) continue;
    $one = computeOneFilter($attr, $vals);
    $r .= $not ? "(!$one)" : $one;
  }
  return $r;
}

function structureShortnames($keys) {
    GLOBAL $structureKeyToShortname;
    $shortnames = array();
    foreach ($keys as &$key) {
      if (isset($structureKeyToShortname[$key]))
	$shortnames[] = $structureKeyToShortname[$key];
    }
    return empty($shortnames) ? NULL : $shortnames;
}

foreach ($users as &$user) {
  if (isset($user['employeeType']) || isset($user['departmentNumber']))
    if (!in_array($user['eduPersonPrimaryAffiliation'], array('teacher', 'emeritus', 'researcher'))) {
      unset($user['employeeType']); // employeeType is private for staff & student
      unset($user['departmentNumber']); // departmentNumber is not interesting for staff & student
    }
  if (isset($user['supannEntiteAffectation'])) {
    $user['supannEntiteAffectation'] = structureShortnames($user['supannEntiteAffectation']);
  }
  if (isset($user['supannRoleGenerique'])) {
    $user['supannRoleGenerique'] = $roleGeneriqueKeyToShortname[$user['supannRoleGenerique']];
  }
  if (isset($user['supannEtablissement'])) {
    if (in_array($user['supannEtablissement'], array('{UAI}0751717J', "{autre}"))) {
      unset($user['supannEtablissement']); // only return interesting supannEtablissement (ie not Paris1)
    } else {
      $user['supannEtablissement'] = mayRemap($etablissementKeyToShortname, $user['supannEtablissement']);
    }
  }
}

echoJson($users);

?>
