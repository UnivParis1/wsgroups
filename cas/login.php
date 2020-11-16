<?php

// simple wrapper to real CAS /login

require_once ('../config/config.inc.php');

$service = $_GET["service"];
if (isset($_GET["ticket"])) {
    $ticket = @$_GET["ticket"];
    $redirect = $service . (strpos($service, '?') !== false ? '&' : '?') . "ticket=$ticket";
} else {
    $current_url = "$OUR_CASv3_URL/login?service=" . urlencode($service);
    $redirect = "https://$CAS_HOST/cas/login?service=" . urlencode($current_url);
}
header("Location: $redirect");