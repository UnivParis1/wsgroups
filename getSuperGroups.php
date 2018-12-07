<?php // -*-PHP-*-
require_once ('lib/groups.inc.php');

$DEBUG = 99;


$key = GET_ldapFilterSafe("key");
$depth = min(max(0, GET_or_NULL("depth")), 3);
$restriction = GET_extra_group_filter_from_params();
$all_groups = array();
getSuperGroups($all_groups, $key, $depth, $restriction);

echoJson($all_groups);

?>
