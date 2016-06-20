<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$login = GET_ldapFilterSafe("login");

exec("echo $login | ssh userinfo@cas.univ-paris1.fr", $lines);
$since = strtotime(array_shift($lines));

$list = array();
$fuzzy_failed = array();
foreach ($lines as $line) {
  if (preg_match("/(.*?) - AUTHENTICATION_(SUCCESS|FAILED) from '(.*)'/", $line, $m)) {
    $e = array("time" => strtotime($m[1]), "ip" => $m[3]);
    if ($m[2] != "SUCCESS") $e["error"] = $m[2];
    $list[] = $e;
  } elseif (preg_match("/(.*?) - AUTHENTICATION_FAILED for '\[username: (.*?)\]' from '(.*)'/", $line, $m)) {
    $e = array("time" => strtotime($m[1]), "ip" => $m[3], "username" => $m[2]);
    $fuzzy_failed[] = $e;
  }
}

echoJson(array("since" => $since, "list" => $list, "fuzzy_failed" => $fuzzy_failed));

?>
