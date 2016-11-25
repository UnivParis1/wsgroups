<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

$token = GET_ldapFilterSafe_or("token", '');
$attrs = GET_or_NULL("attrs");
$anonymous = !(@$isTrustedIp || GET_uid());
$maxRows = !$anonymous ? GET_or("maxRows", 0) : min(max(GET_or_NULL("maxRows"), 1), 10);
$showErrors = GET_or_NULL("showErrors");
$showExtendedInfo = GET_or_NULL("showExtendedInfo");
$allowInvalidAccounts = GET_or_NULL("allowInvalidAccounts");

$allowExtendedInfo = $anonymous ? -1 : 0;
if (isset($showExtendedInfo) && GET_uid()) {
  global $LEVEL1_FILTER, $LEVEL2_FILTER;
  if (isPersonMatchingFilter(GET_uid(), $LEVEL1_FILTER)) {
    if (isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
      $allowExtendedInfo = 2;
    } else {
      $allowExtendedInfo = 1;
    }
  }
}

if ($allowExtendedInfo >= 1) {
  global $LDAP_CONNECT_LEVEL1, $LDAP_CONNECT_LEVEL2;
  $LDAP_CONNECT = $allowExtendedInfo == 2 ? $LDAP_CONNECT_LEVEL2 : $LDAP_CONNECT_LEVEL1;
  global_ldap_open('reOpen');
}

$restriction = GET_extra_people_filter_from_params();
$wanted_attrs = people_attrs($attrs, $allowExtendedInfo);

if ($allowInvalidAccounts) $allowInvalidAccounts = $allowExtendedInfo >= 1;

$attrRestrictions = attrRestrictions($allowExtendedInfo);

global $USER_KEY_FIELD;
$users = searchPeople(people_filters($token, $restriction, $allowInvalidAccounts), $attrRestrictions, $wanted_attrs, $USER_KEY_FIELD, $maxRows);

if ($allowExtendedInfo) {
  foreach ($users as &$u) $u["allowExtendedInfo"] = $allowExtendedInfo;
}

echoJson($users);

?>
