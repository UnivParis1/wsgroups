<?php // -*-PHP-*-

require_once ('lib/groups.inc.php');

$token = GET_ldapFilterSafe("token");
$maxRows = min(max(1, GET_or_NULL("maxRows")), 40);
$attrs = explode(',', GET_or_NULL("attrs"));
$restriction = GET_extra_group_filter_from_params();

$all_groups = searchGroups($token, $maxRows, $restriction, $attrs);

echoJson($all_groups);

?>
