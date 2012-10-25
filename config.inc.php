<?php

require ('./config-auth.inc.php');

$DEBUG = 0;
$SEARCH_TIMELIMIT = 5; // seconds

$BASE_DN = "dc=univ-paris1,dc=fr";

// specific Paris1:
$ANNEE = 2012;
$ANNEE_PREV = 2011;
$ALT_STRUCTURES_DN = "ou=structures,o=Paris1,".$BASE_DN;
$DIPLOMA_DN = "ou=$ANNEE,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_PREV_DN = "ou=$ANNEE_PREV,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_ATTRS = array("ou" => "key", "description" => "description", "modifyTimestamp" => "modifyTimestamp");


// supann:
$PEOPLE_DN = "ou=people,".$BASE_DN;
$GROUPS_DN = "ou=groups,".$BASE_DN;
$STRUCTURES_DN = "ou=structures,".$BASE_DN;
$ROLE_GENERIQUE_DN = "ou=supannRoleGenerique,ou=tables,".$BASE_DN;
$ETABLISSEMENT_TABLE_DN = "ou=supannEtablissement,ou=tables,".$BASE_DN;

$PEOPLE_ATTRS = array("uid" => "uid", "displayName" => "displayName", "supannEntiteAffectation" => "MULTI");
$GROUPS_ATTRS = array("cn" => "key", "description" => "name", "modifyTimestamp" => "modifyTimestamp", "seeAlso" => "MULTI");
$STRUCTURES_ATTRS = array("supannCodeEntite" => "key", "ou" => "name", "description" => "description", "businessCategory" => "businessCategory", "modifyTimestamp" => "modifyTimestamp");
$ROLE_GENERIQUE_ATTRS = array("supannRoleGenerique" => "key", "displayName" => "name");
$ETABLISSEMENT_TABLE_ATTRS = array("supannEtablissement" => "key", "displayName" => "name");

// someone having supannListeRouge=TRUE will be returned with all attrs anonymized except the following
$PEOPLE_LISTEROUGE_NON_ANONYMIZED_ATTRS = array('eduPersonAffiliation', 'eduPersonPrimaryAffiliation');

$AFFILIATION2TEXT = array("faculty" => "enseignants", 
			  "student" => "étudiants", 
			  "staff" => "Biatss", 
			  "researcher" => "chercheurs", 
			  "emeritus" => "professeurs émérites", 
			  "affiliate" => "invités", 
			  );

?>
