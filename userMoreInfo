<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL1_FILTER) &&
    !isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$uid = GET_ldapFilterSafe("uid");
$info = GET_ldapFilterSafe_or("info", '*');
$type = GET_or("type", "user");
if (!in_array($type, ["user", "role"])) exit("invalid user type $type");

exec("ssh wsgroups@marmite.univ-paris1.fr getinfo $type '$info' $uid", $lines);

echoJson(json_decode(implode('', $lines)));

?>
