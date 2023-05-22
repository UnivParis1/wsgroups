<?php // -*-PHP-*-
require_once ('lib/common.inc.php');
require_once ('config/config-groups.inc.php');
require_once ('lib/supannPerson.inc.php');

$key = GET_ldapFilterSafe("key");
$wantedAttr = GET_or_NULL("attr");
if (ipTrusted()) {
  $maxRows = 0;
  $attrRestrictions = attrRestrictions(2);
  $SEARCH_TIMELIMIT = 0;
} else {
  $attrRestrictions = attrRestrictions(-1);
  //exit("your IP (" . $_SERVER['REMOTE_ADDR'] . ") is not allowed");   
  $maxRows = 5;
}
$filter = groupKey2filter($key);
$attrs = array('uid' => 'uid');
if ($wantedAttr) $attrs[$wantedAttr] = $wantedAttr;
$users = searchPeople(array($filter), $attrRestrictions, $attrs, 'uid', $maxRows);

if ($wantedAttr) {
  $r = array();
  foreach ($users as $user) {
    $r[] = $user[$wantedAttr];
  }
  $users = $r;
}

echoJson($users);

?>
