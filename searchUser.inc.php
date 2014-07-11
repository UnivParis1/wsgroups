<?php // -*-PHP-*-

require ('./supannPerson.inc.php');

$token = GET_ldapFilterSafe_or("token", '');
$attrs = GET_or_NULL("attrs");
$maxRows = @$isTrustedIp ? GET_or("maxRows", 0) : min(max(GET_or_NULL("maxRows"), 1), 10);
$showErrors = GET_or_NULL("showErrors");
$showExtendedInfo = GET_or_NULL("showExtendedInfo");
$allowInvalidAccounts = GET_or_NULL("allowInvalidAccounts");

$restriction = GET_extra_people_filter_from_params();

$KEY_FIELD = 'uid';
$ALLOWED_MONO_ATTRS = 
  array('uid', 'mail', 'displayName', 'cn', 'eduPersonPrimaryAffiliation', 
	'postalAddress', 'eduPersonPrincipalName',
	'sn', 'givenName',
	//'up1AltGivenName',


	// below are restricted or internal attributes.
	// restricted attributes should only be accessible through $LDAP_CONNECT_LEVEL1 or $LDAP_CONNECT_LEVEL2
	'accountStatus', 'shadowFlag', 'shadowExpire', 'shadowLastChange',
	'up1KrbPrincipal',

	'supannCivilite', 
	'supannListeRouge',
	'supannEmpCorps', 

	'supannAliasLogin',
	'uidNumber', 'gidNumber',
	'supannEmpId', 'supannEtuId', 'supannCodeINE',
	'employeeNumber',

	'homeDirectory', 'gecos',
	'sambaAcctFlags', 'sambaSID',

	'up1BirthName',
	'up1BirthDay',

	'homePhone', 'homePostalAddress', 'pager',
	'supannMailPerso',
	);
$ALLOWED_MULTI_ATTRS = 
  array('supannEntiteAffectation', 'supannEntiteAffectation-ou', 'supannEntiteAffectation-all',
	'employeeType', 'eduPersonAffiliation', 'departmentNumber', 'buildingName', 'description', 'info',
	'supannEtablissement', 'supannActivite', 'supannActivite-all',
	'supannParrainDN', 'supannParrainDN-ou', 'supannParrainDN-all',
	'supannEtuInscription', 'supannEtuInscription-all',
	'memberOf', 'memberOf-all',
	'supannRoleGenerique',

	'roomNumber', 'up1FloorNumber',

	'telephoneNumber', 
	'facsimileTelephoneNumber', 
	'supannAutreTelephone', 'mobile',

	'objectClass',
	'labeledURI',

	// below are restricted or internal attributes.
	// restricted attributes should only be accessible through $LDAP_CONNECT_LEVEL1 or $LDAP_CONNECT_LEVEL2
	'mailForwardingAddress', 'mailDeliveryOption',
	'up1Profile',
	);
if (@$UP1_ROLES_DN) $ALLOWED_MULTI_ATTRS[] = 'up1Roles'; // computed

if (!$attrs) $attrs = implode(',', array_merge($ALLOWED_MONO_ATTRS, $ALLOWED_MULTI_ATTRS));
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

$allowExtendedInfo = 0;
if (isset($showExtendedInfo) && GET_uid()) {
  if (isPersonMatchingFilter(GET_uid(), $LEVEL1_FILTER)) {
    if (isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
      $allowExtendedInfo = 2;
    } else {
      $allowExtendedInfo = 1;
    }
  }
}

if ($allowExtendedInfo >= 1) {
  $LDAP_CONNECT = $allowExtendedInfo == 2 ? $LDAP_CONNECT_LEVEL2 : $LDAP_CONNECT_LEVEL1;
  global_ldap_open('reOpen');
}
if ($allowInvalidAccounts) $allowInvalidAccounts = $allowExtendedInfo >= 1;

$attrRestrictions = 
  array('allowListeRouge' => $allowExtendedInfo > 0 || @$isTrustedIp || GET_uid() && isStaffOrFaculty(GET_uid()),
	'allowMailForwardingAddress' => $allowExtendedInfo > 1,
	'allowEmployeeType' => $allowExtendedInfo > 1,
	);

$users = searchPeople(people_filters($token, $restriction, $allowInvalidAccounts), $attrRestrictions, $wanted_attrs, $KEY_FIELD, $maxRows);

if ($allowExtendedInfo) {
  foreach ($users as &$u) $u["allowExtendedInfo"] = $allowExtendedInfo;
}

echoJson($users);

?>
