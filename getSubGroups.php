<?php // -*-PHP-*-
require_once ('lib/groups.inc.php');

if (GET_bool("CAS")) forceCASAuthentication();

$key = GET_ldapFilterSafe("key");
$attrs = explode(',', GET_or_NULL("attrs"));
$depth = GET_uid() ? GET_or("depth", 0) : min(max(0, GET_or_NULL("depth")), 3);
$restriction = GET_extra_group_filter_from_params();

$all_groups = getSubGroups($key, $depth, $restriction, $attrs);

echoJson($all_groups);

?>
