<?php // -*-PHP-*-
require_once ('lib/groups.inc.php');

$key = GET_ldapFilterSafe("key");
$attrs = explode(',', GET_or_NULL("attrs"));
$group = getGroupFromKey($key, 'allStructures', $attrs);

echoJson($group);

?>
