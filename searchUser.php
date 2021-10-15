<?php // -*-PHP-*-

require_once ('lib/common.inc.php');

if (GET_or_NULL("auth") === "Bearer") force_Bearer_Authentication();
if (GET_bool("CAS")) forceCASAuthentication();

require ('lib/searchUser.inc.php');

?>
