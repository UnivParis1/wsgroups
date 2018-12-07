<?php // -*-PHP-*-

require_once ('lib/groups.inc.php');

if (GET_bool("CAS")) {
    forceCASAuthentication();
} else {
    ipTrustedOrExit();
}

$uid = GET_ldapFilterSafe_or("uid", GET_uid());

$groups = getUserGroups($uid);
echoJsonSimpleGroups($groups);

?>
