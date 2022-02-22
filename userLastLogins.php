<?php // -*-PHP-*-

require_once ('lib/supannPerson.inc.php');

forceCASAuthentication();
if (!isPersonMatchingFilter(GET_uid(), $LEVEL2_FILTER)) {
  header("HTTP/1.0 403 Forbidden");
  exit;
}

$login = strtolower(GET_ldapFilterSafe("login"));
$mail = strtolower(GET_ldapFilterSafe("mail"));

exec("(echo $login; echo $mail) | ssh userinfo@cas3.univ-paris1.fr", $lines);
array_shift($lines);
$audit_boundary_dates = json_decode(array_shift($lines));

$list = array();
array_shift($lines);
while ($line = array_shift($lines)) {
    if (preg_match('/^#/', $line)) break;
    $e = json_decode($line, true);
    $action = getAndUnset($e, 'action');
    if ($action !== 'TICKET_GRANTING_TICKET_CREATED') {
        $e['error'] = $action;
    }
    $list[] = $e;

}
# all remaining $lines are "similar login failures"
$fuzzy_failed = array_map(json_decode, $lines);

$since = compute_since_from_audit_boundary_dates($audit_boundary_dates);


# sort by date (needed since it is an aggregation of login logs + mail logs)
usort($list, function ($a, $b) { return strcmp($a["when"], $b["when"]); });


echoJson(array("since" => $since, "list" => $list, "fuzzy_failed" => $fuzzy_failed));


function compute_since_from_audit_boundary_dates($l) {
    $start1 = $l[0][0]; $end1 = $l[0][1];
    $start2 = $l[1][0]; $end2 = $l[1][1];
    if (strcmp($end1, $start2) <= 0 || strcmp($end2, $start1) <= 0) {
        return [$start1, $start2];
    } else {
        return [min($start1, $start2)];
    }
}

?>
