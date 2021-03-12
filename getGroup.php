<?php // -*-PHP-*-
require_once ('lib/groups.inc.php');

if (GET_bool("CAS")) forceCASAuthentication();

$key = GET_ldapFilterSafe("key");
$attrs = explode(',', GET_or_NULL("attrs"));
$group = getGroupFromKey($key, 'allStructures', $attrs);

echoJson($group);

?>
