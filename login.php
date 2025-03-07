<?php // -*-PHP-*-

require_once ('lib/common.inc.php');

$r = [];
if (GET_bool("CAS")) {
    initPhpCAS();
    if (phpCAS::checkAuthentication()) {
        $r['USER'] = $_SERVER["HTTP_CAS_USER"] = phpCAS::getUser();
        $r['loggedUserAllowedLevel'] = loggedUserAllowedLevel();
    }    
    $r['LOGIN_URL'] = "https://$CAS_HOST$CAS_CONTEXT/login";
}

echoJson($r);

?>

