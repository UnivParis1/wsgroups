<?php

// wrapper to real CAS /proxyValidate, adding attributes (protocol CAS v3)
set_include_path("..:" . get_include_path());
require_once ('config/config.inc.php');
require_once ('lib/common.inc.php');

$service = $_GET["service"];
$ticket = $_GET["ticket"];

if (!$CASv3_WRAPPER_AUTHORIZED_SERVICES || !preg_match($CASv3_WRAPPER_AUTHORIZED_SERVICES, $service)) exit("service not allowed");

$our_service = "$OUR_CASv3_URL/login?service=" . urlencode($service);

$xml = wget("https://$CAS_HOST/cas/proxyValidate?service=" . urlencode($our_service) . "&ticket=" . urlencode($ticket));

$xml = preg_replace_callback('!<cas:user>(.*)</cas:user>!', function ($m) {
    global $PEOPLE_DN, $CASv3_WRAPPER_ALLOWED_ATTRS;
    $uid = $m[1];
    $attributes = "";
    foreach (getFirstLdapInfo($PEOPLE_DN, "(uid=$uid)", $CASv3_WRAPPER_ALLOWED_ATTRS) as $attr => $val) {
        $attributes .= "<cas:$attr>" . xml_entities($val) . "</cas:$attr>\n";
    }
    return "<cas:user>$uid</cas:user>\n" . 
        "<cas:attributes>" . $attributes . "</cas:attributes>";
}, $xml);

echo $xml;

function wget($url) {
    $session = curl_init($url);

    // Don't return HTTP headers. Do return the contents of the call
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

    $data = curl_exec($session);
    curl_close($session);

    return $data;
}

function xml_entities($string) {
    return strtr($string, [ "<" => "&lt;", ">" => "&gt;", "&" => "&amp;" ]);
}
