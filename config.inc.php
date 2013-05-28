<?php

$DEBUG = 0;
$SEARCH_TIMELIMIT = 5; // seconds

$BASE_DN = "dc=univ-paris1,dc=fr";

// supann:
$PEOPLE_DN = "ou=people,".$BASE_DN;

$PEOPLE_ATTRS = array("uid" => "uid", "displayName" => "displayName", "supannEntiteAffectation" => "MULTI");

// someone having supannListeRouge=TRUE will be returned with all attrs anonymized except the following
$PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS = array('eduPersonAffiliation', 'eduPersonPrimaryAffiliation');


// specific Paris1:
$UP1_ROLES_DN = "ou=roles,".$BASE_DN;

?>
