<?php

require_once ('lib/groups.inc.php');

if (GET_bool("CAS")) {
    forceCASAuthentication();
} else {
    ipTrustedOrExit();
}

$uid = GET_ldapFilterSafe_or("uid", GET_uid());

$groups = getUserGroups($uid);
foreach ($groups as &$g) {
  $g["role"] = "";
}
$groups2 = getGroupsFromGroupsDn(array(responsable_filter($uid)), false);
remove_rawKey($groups2);
foreach ($groups2 as &$g) {
  $g["role"] = "Responsable";
}
echoJsonSimpleGroups(array_merge($groups, $groups2));

?>
