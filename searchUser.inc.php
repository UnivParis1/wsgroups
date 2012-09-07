<?php // -*-PHP-*-

require ('./common.inc.php');
require ('./tables.inc.php');

if (GET_uid()) {
  echo GET_uid() . "\n";
  return;
}

$token = GET_ldapFilterSafe("token");
$attrs = GET_or_NULL("attrs");
$maxRows = min(GET_or_NULL("maxRows"), 10);

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


$users = getLdapInfoMultiFilters($PEOPLE_DN, people_filters($token), 
				 $wanted_attrs, $KEY_FIELD, $maxRows);


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
