<?php // -*-PHP-*-
require_once ('lib/groups.inc.php');

$groups = array();

$groups[] = getGroupsFromGroupsDn(array("(cn=*)"));

$groups[] = getGroupsFromDiplomaDnOrPrev(array("(description=*)"), false);
//$groups[] = getGroupsFromDiplomaDnOrPrev(array("(description=*)"), true);

$groupsStructures = getGroupsFromStructuresDn(array("(&(supannCodeEntite=*)(ou=*))"));
$groups[] = remove_businessCategory($groupsStructures);

global $AFFILIATION2TEXT;
$groups[] = getGroupsFromAffiliations(array_keys($AFFILIATION2TEXT), $groupsStructures);

$groups[] = businessCategoryGroups();

$all_groups = array_flatten_non_rec($groups);
remove_rawKey($all_groups);
echoJson($all_groups);

?>
