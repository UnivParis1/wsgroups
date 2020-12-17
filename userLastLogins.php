<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$login = strtolower(GET_ldapFilterSafe("login"));
$mail = strtolower(GET_ldapFilterSafe("mail"));

exec("(echo $login; echo $mail) | ssh userinfo@cas.univ-paris1.fr", $lines);
$since = strtotime(array_shift($lines));

$list = array();
$fuzzy_failed = array();
foreach ($lines as $line) {
  if (preg_match("/(.*?) - AUTHENTICATION_(SUCCESS|FAILED) for '\[username: (.*?)\]' from '(.*)'/", $line, $m)) {
    $e = array("time" => strtotime($m[1]), "ip" => $m[4], "username" => $m[3]);
    if ($m[2] != "SUCCESS") $e["error"] = $m[2];
    if ($e['username'] === $login || $e['username'] === $mail) {
        $list[] = $e;
    } else {
        $fuzzy_failed[] = $e;
    }
  } else {
      #echo "skipping $line\n";
  }
}

echoJson(array("since" => $since, "list" => $list, "fuzzy_failed" => $fuzzy_failed));

?>
