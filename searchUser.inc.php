<?php // -*-PHP-*-

require ('./common.inc.php');

$token = GET_ldapFilterSafe("token");
$attrs = GET_or_NULL("attrs");
$maxRows = min(max(GET_or_NULL("maxRows"), 1), 10);
$showErrors = GET_or_NULL("showErrors");

$restriction = GET_extra_people_filter_from_params();

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
$users = searchPeople(people_filters($token, $restriction), $allowListeRouge, $wanted_attrs, $KEY_FIELD, $maxRows);

echoJson($users);

?>
