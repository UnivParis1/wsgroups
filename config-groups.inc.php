<?php

// specific Paris1:
$ANNEE = 2013;
$ANNEE_PREV = 2012;
$ALT_STRUCTURES_DN = "ou=structures,o=Paris1,".$BASE_DN;
$DIPLOMA_DN = "ou=$ANNEE,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_PREV_DN = "ou=$ANNEE_PREV,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_ATTRS = array("ou" => "key", "description" => "description", "modifyTimestamp" => "modifyTimestamp");

// supann:
$GROUPS_DN = "ou=groups,".$BASE_DN;
$STRUCTURES_DN = "ou=structures,".$BASE_DN;
$ROLE_GENERIQUE_DN = "ou=supannRoleGenerique,ou=tables,".$BASE_DN;
$ETABLISSEMENT_TABLE_DN = "ou=supannEtablissement,ou=tables,".$BASE_DN;
$ACTIVITE_TABLE_DN = "ou=supannActivite,ou=tables,".$BASE_DN;

$GROUPS_ATTRS = array("cn" => "key", "description" => "name", "modifyTimestamp" => "modifyTimestamp", "seeAlso" => "MULTI");
$STRUCTURES_ATTRS = array("supannCodeEntite" => "key", "ou" => "name", "description" => "description", "businessCategory" => "businessCategory", "labeledURI" => "labeledURI", "modifyTimestamp" => "modifyTimestamp");
$ROLE_GENERIQUE_ATTRS = array("supannRoleGenerique" => "key", "displayName" => "name");
$ETABLISSEMENT_TABLE_ATTRS = array("supannEtablissement" => "key", "displayName" => "name");
$ACTIVITE_TABLE_ATTRS = array("supannActivite" => "key", "displayName" => "name");

$AFFILIATION2TEXT = array("faculty" => "enseignants", 
			  "student" => "étudiants", 
			  "staff" => "Biatss", 
			  "researcher" => "chercheurs", 
			  "emeritus" => "professeurs émérites", 
			  "affiliate" => "invités", 
			  );

$BUSINESSCATEGORY2TEXT = array("research" => "Laboratoires de recherche", 
			       "library" => "Bibliothèques", 
			       "doctoralSchool" => "Écoles doctorales", 
			       "administration" => "Services", 
			       "pedagogy" => "Composantes personnels", 
			       );

$MAX_PARENTS_IN_DESCRIPTION = 4;

?>
