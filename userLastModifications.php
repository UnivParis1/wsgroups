<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$uid = GET_or_NULL("uid");
if (!$uid) fatal("missing uid query param") ;
if (!preg_match("/^\w+$/", $uid)) fatal("invalid uid");

header('Content-type: text/plain; charset=UTF-8');
$cmd = "\"grep -h -- '- $uid@' /var/log/activ/esup-activ-bo-modifiedDataFile.log*\"";
passthru("echo $cmd | base64 | ssh wsgroups@marmite.univ-paris1.fr 'base64 -d | bash'");
