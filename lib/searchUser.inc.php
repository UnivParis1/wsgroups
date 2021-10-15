<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

$token = GET_ldapFilterSafe_or("token", '');
$attrs = GET_or_NULL("attrs");
$format = GET_or_NULL("format");
$anonymous = !(@$isTrustedIp || GET_uid());
$maxRows = !$anonymous ? GET_or("maxRows", 0) : min(max(GET_or_NULL("maxRows"), 1), 10);
$showErrors = GET_or_NULL("showErrors");
$showExtendedInfo = GET_or_NULL("showExtendedInfo");
$allowInvalidAccounts = GET_or_NULL("allowInvalidAccounts");
$allowNoAffiliationAccounts = GET_or("allowNoAffiliationAccounts", $allowInvalidAccounts);
$allowRoles = GET_or_NULL("allowRoles");

$allowExtendedInfo = $anonymous ? -1 : 0;
if ((isset($showExtendedInfo) || isset($allowInvalidAccounts)) && (@$isTrustedIp || GET_uid())) {
  if (GET_or_NULL("auth") === "Bearer") fatal('"Bearer" auth is limited to non extended info'); 
  $allowExtendedInfo = @$isTrustedIp ? 2 : loggedUserAllowedLevel();
}

$extendedInfo = $allowExtendedInfo;
if ($allowExtendedInfo >= 1) {
  if (is_numeric($showExtendedInfo)) {
      $extendedInfo = min($extendedInfo, $showExtendedInfo);
  }
  global $LDAP_CONNECT_LEVEL1, $LDAP_CONNECT_LEVEL2;
  $LDAP_CONNECT = $extendedInfo == 2 ? $LDAP_CONNECT_LEVEL2 : $LDAP_CONNECT_LEVEL1;
  global_ldap_open('reOpen');
}

$restriction = GET_extra_people_filter_from_params();
$wanted_attrs = people_attrs($attrs, $extendedInfo);

if ($allowInvalidAccounts && $extendedInfo < 1) $allowInvalidAccounts = false;

$attrRestrictions = attrRestrictions($extendedInfo);
$attrRestrictions['allowRoles'] = $allowRoles;

if ($attrRestrictions['forceProfile']) {
    $wanted_attrs['up1Profile'] = 'MULTI';
}

global $USER_KEY_FIELD;
$users = searchPeople(people_filters($token, $restriction, $allowInvalidAccounts, $allowNoAffiliationAccounts), $attrRestrictions, $wanted_attrs, $USER_KEY_FIELD, $maxRows);

if ($allowExtendedInfo) {
  foreach ($users as &$u) $u["allowExtendedInfo"] = $allowExtendedInfo;
}

if ($format === 'vcard') {
    require_once 'lib/vcard.inc.php';
    echo_vcard($users);
} else {
    echoJson($users);
}

?>
