<?php // -*-PHP-*-

require_once ('lib/groups.inc.php');

if (GET_bool("CAS")) forceCASAuthentication();

$token = GET_ldapFilterSafe("token");
$anonymous = !(ipTrusted() || GET_uid());
$maxRows = !$anonymous ? GET_or("maxRows", 0) : min(max(GET_or_NULL("maxRows"), 1), 40);
$attrs = explode(',', GET_or_NULL("attrs"));
$restriction = GET_extra_group_filter_from_params();

$all_groups = searchGroups($token, $maxRows, $restriction, $attrs);

echoJson($all_groups);

?>
