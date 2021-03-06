<?php

require_once ('config/config.inc.php');
require_once ('config/config-groups.inc.php');
$ANNEE = 2014;
$ANNEE_PREV = 2013;
$DIPLOMA_DN = "ou=$ANNEE,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_PREV_DN = "ou=$ANNEE_PREV,ou=diploma,o=Paris1,".$BASE_DN;

require_once ('lib/common.inc.php');
require_once ('gen/tables.inc.php'); // TODO: use a generated one with tests data
$LDAP_CONNECT['test_ldif_files'] = glob('test/*/*.ldif');
$LDAP_CONNECT_LEVEL1 = $LDAP_CONNECT_LEVEL2 = $LDAP_CONNECT;
$LEVEL1_FILTER = $LEVEL2_FILTER = '(uid=prigaux)';
$_SERVER["HTTP_CAS_USER"] = 'prigaux';

function map_obj_attr($l, $attr) {
    $r = array();
    foreach ($l as $e) $r[] = @$e->$attr;
    return $r;
}
function test($ws, $params) {
    $_GET = $params;

    ob_start();
    require "./$ws";
    $out = ob_get_contents();
    ob_end_clean();
    
    return $out;
}

function fail($test_name, $msg) {
    echo "FAILED test $test_name: $msg\n";
    exit(1);
}

function expectToBe($got, $wanted, $test_name) {
    if ($got !== $wanted) {
        fail($test_name, "got\n\n$got\n\ninstead of\n\n$wanted");
    }
}

function expect($name, $wanted, $ws, $params) {
    $got = test($ws, $params);
    expectToBe($got, $wanted, $name);
}

function expect_js($test_name, $ws, $params, $wanted, $remap) {
    $js = test($ws, $params);
    $r = json_decode($js);
    if ($r === NULL) fail($test_name, "invalid response\n$js");
    $got = $remap($r);
    expectToBe(json_encode($got), $wanted, $test_name);
}

function test_js_list_attr($test_name, $ws, $attr, $params, $wanted) {
    expect_js($test_name, $ws, $params, $wanted, function ($r) use ($attr) {
        return map_obj_attr($r, $attr);
    });
}

function test_js_attr($test_name, $ws, $attr, $params, $wanted) {
    expect_js($test_name, $ws, $params, $wanted, function ($r) use ($attr) {
        return $r[0]->$attr;
    });
}

function Xexpect() {}

function checkUserAttr($attr, $expected, $params = []) {
    $params = array_merge($params, ['token' => 'fbar', 'attrs' => $attr]);
    test_js_attr("checkUserAttr $attr", "searchUser", $attr, $params, $expected);
}
checkUserAttr('displayName', '"Fooo Bar"');
checkUserAttr('memberOf', '["cn=grp1,ou=groups,dc=univ-paris1,dc=fr"]', ['showExtendedInfo' => 1]);
checkUserAttr('memberOf-all', '[{"key":"grp1","name":"GRP1","description":"Utilisateurs GRP1","objectClass":["groupOfNames","labeledURIObject","supannGroupe","top","posixGroup"]}]', ['showExtendedInfo' => 1]);
checkUserAttr('supannParrainDN', '["ou=DGEP,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"]');
checkUserAttr('supannParrainDN-all', '[{"key":"DGEP","name":"DRH-SP BIATSS","description":"DRH-SP BIATSS : service des personnels des biblioth\u00e8ques, ing\u00e9nieurs, administratifs, techniques, sociaux et de sant\u00e9","businessCategory":"administration"}]');
checkUserAttr('supannEntiteAffectation', '["DSIUN-SAS"]'); // deprecated
checkUserAttr('supannEntiteAffectation-ou', '["DSIUN-SAS"]');
checkUserAttr('supannEntiteAffectation-all', '[{"key":"DGHA","name":"DSIUN-SAS","description":"DSIUN-SAS : Service des applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"}]');

$full_fbar = <<<'EOS'
[{"uid":"fbar","mail":"Fooo.Bar@univ-paris1.fr","displayName":"Fooo Bar","cn":"Bar Fooo","eduPersonPrimaryAffiliation":"staff","postalAddress":"90 rue de Tolbiac\n75634 PARIS CEDEX 13\nFRANCE","eduPersonPrincipalName":"fbar@univ-paris1.fr","sn":"Bar","givenName":"Fooo","supannEntiteAffectationPrincipale":"DGHA","supannCivilite":"M.","supannListeRouge":"FALSE","supannAliasLogin":"fbar","accountStatus":"active","supannEmpId":"99007","up1BirthName":"Bar","up1BirthDay":"20150101010000Z","homePhone":"+33 1 02 03 04 05","homePostalAddress":"6 rue Zoo$75018 PARIS$FRANCE","pager":"0607070707","supannEntiteAffectation":["DGHA"],"eduPersonAffiliation":["employee","member","staff"],"supannActivite":["Chef de projet ou expert syst\u00e8mes informatiques, r\u00e9seaux et t\u00e9l\u00e9communications"],"supannParrainDN":["ou=DGEP,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"],"roomNumber":["B 407"],"up1FloorNumber":["4e"],"telephoneNumber":["+33 1 44 07 86 59"],"objectClass":["eduPerson","inetOrgPerson","organizationalPerson","person","posixAccount","shadowAccount","supannPerson"],"up1Profile":[{"supannParrainDN":["ou=DGEP,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"],"eduPersonAffiliation":["member","employee","staff"],"supannEntiteAffectation":["DGHA"],"buildingName":["Centre Pierre Mend\u00e8s France"],"supannEntiteAffectationPrincipale":"DGHA","postalAddress":"90 RUE DE TOLBIAC$75634 PARIS CEDEX 13$FRANCE","supannActivite":["Chef de projet ou expert en d\u00e9veloppement et d\u00e9ploiement d'applications"],"eduPersonPrimaryAffiliation":"staff","supannEntiteAffectation-all":[{"key":"DGHA","name":"DSIUN-SAS","description":"DSIUN-SAS : Service des applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"}],"supannParrainDN-all":[{"key":"DGEP","name":"DRH-SP BIATSS","description":"DRH-SP BIATSS : service des personnels des biblioth\u00e8ques, ing\u00e9nieurs, administratifs, techniques, sociaux et de sant\u00e9","businessCategory":"administration"}],"supannActivite-all":[{"key":"{REFERENS}E1B22","name":"Chef de projet ou expert en d\u00e9veloppement et d\u00e9ploiement d'applications"}]},{"eduPersonAffiliation":["employee","member","staff"],"eduPersonEntitlement":["urn:mace:univ-paris1.fr:entitlement:SC4:registered-reader"],"eduPersonPrimaryAffiliation":"staff","givenName":"Pascal","sn":"Rigaux","supannCivilite":"M.","supannEtablissement":["SERV COM DOC UNIV"],"supannParrainDN":["supannCodeEntite=SC4,ou=structures,dc=univ-paris1,dc=fr"],"supannParrainDN-all":[{"key":"SC4","name":"SCD","description":"Service Commun de la Documentation","businessCategory":"library","labeledURI":"http:\/\/bib.univ-paris1.fr"}]}],"supannEntiteAffectation-all":[{"key":"DGHA","name":"DSIUN-SAS","description":"DSIUN-SAS : Service des applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"}],"supannParrainDN-all":[{"key":"DGEP","name":"DRH-SP BIATSS","description":"DRH-SP BIATSS : service des personnels des biblioth\u00e8ques, ing\u00e9nieurs, administratifs, techniques, sociaux et de sant\u00e9","businessCategory":"administration"}],"supannActivite-all":[{"key":"{REFERENS}E1C23","name":"Chef de projet ou expert syst\u00e8mes informatiques, r\u00e9seaux et t\u00e9l\u00e9communications"}]}]
EOS;

expect('simple searchUser all attrs', $full_fbar,
       'searchUser', ['token' => 'fbar']);

function searchUser($token, $expected, $params = []) {
    $params = array_merge(['token' => $token, 'attrs' => 'uid', 'maxRows' => 5], $params);
    test_js_list_attr("searchUser $token", 'searchUser', 'uid', $params, $expected);
}

searchUser('Fooo Bar', '["fbar"]');
searchUser('o Bar', '["fbar","zbar"]');
searchUser('Fooo B', '["fbar"]');
searchUser('Fooo ', '["fbar"]');
searchUser('Fooo', '["fbar"]');
searchUser('Foo', '[]'); // no sub search if short token

searchUser('Bar Fooo', '["fbar"]');
searchUser('Bar Foo', '["fbar"]');

searchUser('Bar', '["fbar","zbar"]'); // exact search on sn
searchUser('99007', '["fbar"]'); // exact search on supannEmpId

searchUser('Suzie', '["e0g422l021q","e2404567812"]'); // filter person with no eduPersonAffiliation


function searchGroup($token, $attr, $expected, $params = []) {
    $params = array_merge(['token' => $token, 'maxRows' => 5], $params);
    test_js_list_attr("searchGroup $token", 'searchGroup', $attr, $params, $expected);
}

searchGroup("dsiun", 'key', '["groups-employees.administration.DGH","groups-employees.administration.DGHA"]');
searchGroup("dsiun", 'key', '["structures-DGH","structures-DGHA"]', ['filter_category' => 'structures']);
searchGroup("dsiun-sas", 'businessCategory', '[null]', ['filter_category' => 'structures']);
searchGroup("dsiun-sas", 'businessCategory', '["administration"]', ['filter_category' => 'structures', 'attrs' => 'businessCategory']);


function searchUserAndGroup($token, $attr, $expected, $params = []) {
    $params = array_merge(['token' => $token, 'maxRows' => 5], $params);
    expect_js("searchUserAndGroup $token", 'search', $params, $expected, function ($r) use ($attr) {
        return map_obj_attr($r->groups, $attr);
    });
}

searchUserAndGroup("dsiun", 'key', '["groups-employees.administration.DGH","groups-employees.administration.DGHA"]');
searchUserAndGroup("dsiun", 'key', '["structures-DGH","structures-DGHA"]', ['filter_category' => 'structures']);
searchUserAndGroup("dsiun-sas", 'businessCategory', '[null]', ['filter_category' => 'structures']);
searchUserAndGroup("dsiun-sas", 'businessCategory', '["administration"]', ['filter_category' => 'structures', 'group_attrs' => 'businessCategory']);


$parents = <<<'EOS'
{"diploma-L2T101":{"key":"diploma-L2T101","description":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","rawKey":"L2T101","name":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","category":"diploma","superGroups":["structures-DGH-affiliation-student"]},"structures-DGH-affiliation-student":{"key":"structures-DGH-affiliation-student","name":"DSIUN : Direction du syst\u00e8me d'information et des Usages Num\u00e9riques (\u00e9tudiants)","description":"","category":"structures","superGroups":["affiliation-student"]},"affiliation-student":{"key":"affiliation-student","name":"Tous les \u00e9tudiants","description":"Tous les \u00e9tudiants","category":"affiliation","superGroups":[]}}
EOS;
expect('getSuperGroups diploma', $parents, 'getSuperGroups', ['key' => 'diploma-L2T101', 'depth' => 99]);

$parents = <<<'EOS'
{"structures-DGHA":{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGHA","category":"structures","superGroups":["structures-DGH"]},"structures-DGH":{"key":"structures-DGH","name":"DSIUN : Direction du syst\u00e8me d'information et des Usages Num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGH","category":"structures","superGroups":["businessCategory-administration"]}}
EOS;
expect('getSuperGroups structures', $parents, 'getSuperGroups', ['key' => 'structures-DGHA', 'depth' => 1]);
$parents = <<<'EOS'
{"structures-DGHA":{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGHA","category":"structures","superGroups":["structures-DGH"]},"structures-DGH":{"key":"structures-DGH","name":"DSIUN : Direction du syst\u00e8me d'information et des Usages Num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGH","category":"structures","superGroups":[]}}
EOS;
expect('getSuperGroups only structures', $parents, 'getSuperGroups', ['key' => 'structures-DGHA', 'depth' => 1, 'filter_category' => 'structures']);

$children = <<<'EOS'
[{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","category":"structures"},{"key":"diploma-L2T101","description":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","name":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","category":"diploma"}]
EOS;
expect('getSubGroups structures', $children, 'getSubGroups', ['key' => 'structures-DGH', 'depth' => 1]);

$children = <<<'EOS'
[{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","category":"structures"}]
EOS;
expect('getSubGroups only structures', $children, 'getSubGroups', ['key' => 'structures-DGH', 'depth' => 1, 'filter_category' => 'structures']);

$subAndSuper = <<<'EOS'
{"subGroups":[{"key":"groups-matiB1010514","name":"UFR 02 - Mati\u00e8re (Semestre 1) : Comptabilit\u00e9 d'entreprise","description":"<br>\n<br>\n<br>\n","category":"elp"}],"superGroups":{"diploma-L2B101":{"category":null,"superGroups":[]}}}
EOS;
expect('getSubAndSuperGroups diploma', $subAndSuper, 'getSubAndSuperGroups', ['key' => 'diploma-L2B101', 'depth' => 99]);


$allGroups = <<<'EOS'
[{"key":"groups-employees.administration.DGH","name":"employees.administration.DGH"},{"key":"groups-employees.administration.DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"employees.administration.DGH"},{"key":"groups-grp1","name":"Utilisateurs GRP1"},{"key":"groups-matiB1010514","name":"UFR 02 - Mati\u00e8re (Semestre 1) : Comptabilit\u00e9 d'entreprise","description":"<br>\n<br>\n<br>\n"},{"key":"diploma-L2T101","description":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","name":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)"},{"key":"affiliation-faculty","name":"Tous les enseignants","description":"Tous les enseignants"},{"key":"affiliation-teacher","name":"Tous les enseignants et charg\u00e9s d'enseignement","description":"Tous les enseignants et charg\u00e9s d'enseignement"},{"key":"affiliation-student","name":"Tous les \u00e9tudiants","description":"Tous les \u00e9tudiants"},{"key":"affiliation-staff","name":"Tous les Biatss","description":"Tous les Biatss"},{"key":"affiliation-researcher","name":"Tous les chercheurs","description":"Tous les chercheurs"},{"key":"affiliation-emeritus","name":"Tous les professeurs \u00e9m\u00e9rites","description":"Tous les professeurs \u00e9m\u00e9rites"},{"key":"affiliation-affiliate","name":"Tous les invit\u00e9s","description":"Tous les invit\u00e9s"},{"key":"businessCategory-research","name":"Laboratoires de recherche","description":"Laboratoires de recherche"},{"key":"businessCategory-library","name":"Biblioth\u00e8ques","description":"Biblioth\u00e8ques"},{"key":"businessCategory-doctoralSchool","name":"\u00c9coles doctorales","description":"\u00c9coles doctorales"},{"key":"businessCategory-administration","name":"Services","description":"Services"},{"key":"businessCategory-pedagogy","name":"Composantes personnels","description":"Composantes personnels"}]
EOS;

expect('allGroups', $allGroups, 'allGroups', []);
