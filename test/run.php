<?php

require_once ('config/config.inc.php');
require_once ('config/config-groups.inc.php');
$ANNEE = 2014;
$ANNEE_PREV = 2013;
$DIPLOMA_DN = "ou=$ANNEE,ou=diploma,o=Paris1,".$BASE_DN;
$DIPLOMA_PREV_DN = "ou=$ANNEE_PREV,ou=diploma,o=Paris1,".$BASE_DN;

require_once ('lib/common.inc.php');
require_once ('test/tables.inc.php');
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
    require "./$ws.php";
    $out = ob_get_contents();
    ob_end_clean();
    
    return $out;
}

function fail($test_name, $msg) {
    echo "FAILED test $test_name: $msg\n";
    exit(1);
}

function diff($got, $wanted) {
    $context_lines = 4;
    for ($i = 0; $i < sizeof($got); $i++) {
        if ($got[$i] !== $wanted[$i]) {
            $from = max(0, $i - $context_lines);
            $context = $i >= $context_lines ? array_slice($got, $i - $context_lines, $context_lines) : array_slice($got, 0, $i);
            return implode("\n", array_merge(
                    $context,
                    array_map(function ($s) { return "- $s"; }, array_slice($got, $i, 2)),
                    array_map(function ($s) { return "+ $s"; }, array_slice($wanted, $i, 2))));
        }
    }
    return "NO_DIFF????";
}

function diff_json($got, $wanted) {
    return diff(explode("\n", json_encode($got, JSON_PRETTY_PRINT)), 
                explode("\n", json_encode($wanted, JSON_PRETTY_PRINT)));
}

function expect_json($test_name, $ws, $params, $wanted, $remap = null) {
    $js = test($ws, $params);
    $r = json_decode($js);
    if ($r === NULL) fail($test_name, "invalid response\n$js");
    if (is_array(($r))) {
        // ignore globalInfo
        foreach ($r as &$e) unset($e->globalInfo);
    }
    $got = $remap ? $remap($r) : $r;
    $got_s = json_encode($got);
    if ($got_s !== $wanted) {
        fail($test_name, "got\n\n$got_s\n\ninstead of\n\n$wanted" . "\n\nDiff ('-' is wanted):\n\n" . diff_json(json_decode($wanted), $got));
    }
}

function test_js_list_attr($test_name, $ws, $attr, $params, $wanted) {
    expect_json($test_name, $ws, $params, $wanted, function ($r) use ($attr) {
        return map_obj_attr($r, $attr);
    });
}

function test_js_attr($test_name, $ws, $attr, $params, $wanted) {
    expect_json($test_name, $ws, $params, $wanted, function ($r) use ($attr) {
        return @$r[0]->$attr;
    });
}

function Xexpect_json() {}

function checkUserAttr($attr, $expected, $params = []) {
    $params = array_merge($params, ['token' => 'fbar', 'attrs' => $attr]);
    test_js_attr("checkUserAttr $attr", "searchUser", $attr, $params, $expected);
}
checkUserAttr('displayName', '"Fooo Bar"');
checkUserAttr('eduPersonPrimaryAffiliation', '"staff"');
checkUserAttr('memberOf', '["cn=grp1,ou=groups,dc=univ-paris1,dc=fr"]', ['showExtendedInfo' => 1]);
checkUserAttr('memberOf-all', '[{"key":"grp1","name":"GRP1","description":"Utilisateurs GRP1","objectClass":["groupOfNames","labeledURIObject","supannGroupe","top","posixGroup"]}]', ['showExtendedInfo' => 1]);
checkUserAttr('supannParrainDN', '["ou=DGEP,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"]');
checkUserAttr('supannParrainDN-all', '[{"key":"supannCodeEntite=DGEP,ou=structures,dc=univ-paris1,dc=fr","name":"DRH-SP BIATSS","description":"DRH-SP BIATSS : service des personnels des biblioth\u00e8ques, ing\u00e9nieurs, administratifs, techniques, sociaux et de sant\u00e9"}]');
checkUserAttr('supannEntiteAffectation', '["DSIUN-PAS"]'); // deprecated
checkUserAttr('supannEntiteAffectation-ou', '["DSIUN-PAS"]');
checkUserAttr('supannEntiteAffectation-all', '[{"key":"DGHA","name":"DSIUN-PAS","description":"DSIUN-PAS : P\u00f4le applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"}]');

checkUserAttr('employeeType-all', 'null');

expect_json('no prefered profile', 'searchUser', ['token' => 'fbar', 'attrs' => 'eduPersonPrimaryAffiliation'], '[{"eduPersonPrimaryAffiliation":"staff","uid":"fbar"}]');
expect_json('use prefered profile', 'searchUser', ['token' => 'fbar', 'profile_supannEntiteAffectation' => 'DS', 'attrs' => 'eduPersonPrimaryAffiliation'], '[{"eduPersonPrimaryAffiliation":"teacher","uid":"fbar"}]');
test_js_attr('no prefered profile employeeType', 'searchUser', 'employeeType', ['token' => 'fbar'], 'null');
test_js_attr('use prefered profile employeeType', 'searchUser', 'employeeType', ['token' => 'fbar', 'profile_supannEntiteAffectation' => 'DS'], '["Charg\u00e9 d\'enseignement"]');

expect_json('no prefered profile zbar', 'searchUser', ['token' => 'zbar', 'attrs' => 'eduPersonAffiliation,eduPersonPrimaryAffiliation'], '[{"eduPersonAffiliation":["employee","member","staff","registered-reader","teacher"],"eduPersonPrimaryAffiliation":"staff","uid":"zbar"}]');
expect_json('use prefered profile zbar', 'searchUser', ['token' => 'zbar', 'profile_supannEntiteAffectation' => 'DS', 'attrs' => 'eduPersonAffiliation,eduPersonPrimaryAffiliation'], '[{"eduPersonAffiliation":["employee","member","staff","teacher"],"eduPersonPrimaryAffiliation":"teacher","uid":"zbar"}]');
test_js_attr('no prefered profile zbar supannActivite', 'searchUser', 'supannActivite-all', ['token' => 'zbar', 'attrs' => 'supannActivite-all'], '[{"key":"{REFERENS}E1B22","name":"Chef-fe de projet ou expert-e en Ing\u00e9ni\u00e9rie logicielle"}]');
test_js_attr('use prefered profile zbar supannActivite', 'searchUser', 'supannActivite-all', ['token' => 'zbar', 'profile_supannEntiteAffectation' => 'DS', 'attrs' => 'supannActivite-all'], 'null');

$full_fbar = <<<'EOS'
[{"uid":"fbar","mail":"Fooo.Bar@univ-paris1.fr","displayName":"Fooo Bar","cn":"Bar Fooo","eduPersonPrimaryAffiliation":"staff","postalAddress":"90 rue de Tolbiac\n75634 PARIS CEDEX 13\nFRANCE","eduPersonPrincipalName":"fbar@univ-paris1.fr","sn":"Bar","givenName":"Fooo","supannEntiteAffectationPrincipale":"DGHA","supannCivilite":"M.","supannListeRouge":"FALSE","supannAliasLogin":"fbar","accountStatus":"active","supannEntiteAffectation":["DSIUN-PAS"],"eduPersonAffiliation":["employee","member","staff"],"supannActivite":["Chef-fe de projet ou expert-e syst\u00e8mes informatiques, r\u00e9seaux et t\u00e9l\u00e9communications"],"supannParrainDN":["ou=DGEP,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"],"roomNumber":["B 407"],"up1FloorNumber":["4e"],"telephoneNumber":["+33 1 44 07 86 59"],"up1Profile":[{"supannParrainDN":["ou=DGEP,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"],"eduPersonAffiliation":["member","employee","staff"],"supannEntiteAffectation":["DSIUN-PAS"],"buildingName":["Centre Pierre Mend\u00e8s France"],"supannEntiteAffectationPrincipale":"DGHA","postalAddress":"90 RUE DE TOLBIAC$75634 PARIS CEDEX 13$FRANCE","supannActivite":["Chef-fe de projet ou expert-e en Ing\u00e9ni\u00e9rie logicielle"],"eduPersonPrimaryAffiliation":"staff","supannEntiteAffectation-all":[{"key":"DGHA","name":"DSIUN-PAS","description":"DSIUN-PAS : P\u00f4le applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"}],"supannEntiteAffectation-ou":["DSIUN-PAS"],"supannEntiteAffectationPrincipale-all":{"key":"DGHA","name":"DSIUN-PAS","description":"DSIUN-PAS : P\u00f4le applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"},"supannParrainDN-all":[{"key":"supannCodeEntite=DGEP,ou=structures,dc=univ-paris1,dc=fr","name":"DRH-SP BIATSS","description":"DRH-SP BIATSS : service des personnels des biblioth\u00e8ques, ing\u00e9nieurs, administratifs, techniques, sociaux et de sant\u00e9"}],"supannActivite-all":[{"key":"{REFERENS}E1B22","name":"Chef-fe de projet ou expert-e en Ing\u00e9ni\u00e9rie logicielle","name-gender":"Chef de projet ou expert en Ing\u00e9ni\u00e9rie logicielle"}]},{"employeeType":["Charg\u00e9 d'enseignement"],"supannParrainDN":["ou=DGEB,ou=structures,o=Paris1,dc=univ-paris1,dc=fr"],"eduPersonAffiliation":["member","teacher","employee"],"supannEntiteAffectation":["EDS"],"buildingName":["Centre Pierre Mend\u00e8s France"],"supannEntiteAffectationPrincipale":"DS","postalAddress":"90 RUE DE TOLBIAC$75634 PARIS CEDEX 13$FRANCE","eduPersonPrimaryAffiliation":"teacher","supannEntiteAffectation-all":[{"key":"DS","name":"EDS","description":"EDS : \u00c9cole de droit de la Sorbonne","businessCategory":"pedagogy","labeledURI":"http:\/\/eds.univ-paris1.fr"}],"supannEntiteAffectation-ou":["EDS"],"supannEntiteAffectationPrincipale-all":{"key":"DS","name":"EDS","description":"EDS : \u00c9cole de droit de la Sorbonne","businessCategory":"pedagogy","labeledURI":"http:\/\/eds.univ-paris1.fr"},"supannParrainDN-all":[null],"employeeType-all":[{"name":"Charg\u00e9 d'enseignement","weight":"12"}]},{"eduPersonAffiliation":["employee","member","staff"],"eduPersonEntitlement":["urn:mace:univ-paris1.fr:entitlement:SC4:registered-reader"],"eduPersonPrimaryAffiliation":"staff","givenName":"Pascal","sn":"Rigaux","supannCivilite":"M.","supannEtablissement":["SERV COM DOC UNIV"],"supannParrainDN":["supannCodeEntite=SC4,ou=structures,dc=univ-paris1,dc=fr"],"supannParrainDN-all":[null]}],"supannEntiteAffectation-all":[{"key":"DGHA","name":"DSIUN-PAS","description":"DSIUN-PAS : P\u00f4le applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"}],"supannEntiteAffectation-ou":["DSIUN-PAS"],"supannEntiteAffectationPrincipale-all":{"key":"DGHA","name":"DSIUN-PAS","description":"DSIUN-PAS : P\u00f4le applications et services num\u00e9riques","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr"},"supannParrainDN-all":[{"key":"supannCodeEntite=DGEP,ou=structures,dc=univ-paris1,dc=fr","name":"DRH-SP BIATSS","description":"DRH-SP BIATSS : service des personnels des biblioth\u00e8ques, ing\u00e9nieurs, administratifs, techniques, sociaux et de sant\u00e9"}],"supannActivite-all":[{"key":"{REFERENS}E1C23","name":"Chef-fe de projet ou expert-e syst\u00e8mes informatiques, r\u00e9seaux et t\u00e9l\u00e9communications","name-gender":"Chef de projet ou expert syst\u00e8mes informatiques, r\u00e9seaux et t\u00e9l\u00e9communications"}]}]
EOS;

expect_json('simple searchUser all attrs', 'searchUser', ['token' => 'fbar'], $full_fbar);

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
searchGroup("dsiun", 'groups_key', '[null,null]', ['filter_category' => 'structures']);
searchGroup("dsiun", 'groups_key', '["groups-employees.administration.DGH","groups-employees.administration.DGHA"]', ['filter_category' => 'structures', 'attrs' => 'businessCategory']);
searchGroup("dsiun-sas", 'businessCategory', '[null]', ['filter_category' => 'structures']);
searchGroup("dsiun-sas", 'businessCategory', '["administration"]', ['filter_category' => 'structures', 'attrs' => 'businessCategory']);


function searchUserAndGroup($token, $attr, $expected, $params = []) {
    $params = array_merge(['token' => $token, 'maxRows' => 5], $params);
    expect_json("searchUserAndGroup $token", 'search', $params, $expected, function ($r) use ($attr) {
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
expect_json('getSuperGroups diploma', 'getSuperGroups', ['key' => 'diploma-L2T101', 'depth' => 99], $parents);

$parents = <<<'EOS'
{"structures-DGHA":{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"rawKey":"DGHA","category":"structures","groups_key":"groups-employees.administration.DGHA","superGroups":["structures-DGH"]},"structures-DGH":{"key":"structures-DGH","name":"DSIUN : Direction du syst\u00e8me d'information et des Usages Num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGH","category":"structures","groups_key":"groups-employees.administration.DGH","superGroups":["businessCategory-administration"]}}
EOS;
expect_json('getSuperGroups structures', 'getSuperGroups', ['key' => 'structures-DGHA', 'depth' => 1], $parents);
$parents = <<<'EOS'
{"structures-DGHA":{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"rawKey":"DGHA","category":"structures","groups_key":"groups-employees.administration.DGHA","superGroups":["structures-DGH"]},"structures-DGH":{"key":"structures-DGH","name":"DSIUN : Direction du syst\u00e8me d'information et des Usages Num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGH","category":"structures","groups_key":"groups-employees.administration.DGH","superGroups":[]}}
EOS;
expect_json('getSuperGroups only structures', 'getSuperGroups', ['key' => 'structures-DGHA', 'depth' => 1, 'filter_category' => 'structures'], $parents);

$children = <<<'EOS'
[{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"category":"structures"},{"key":"diploma-L2T101","description":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","name":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","category":"diploma"}]
EOS;
expect_json('getSubGroups structures', 'getSubGroups', ['key' => 'structures-DGH', 'depth' => 1], $children);

$children = <<<'EOS'
[{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"roles":[{"uid":"ydupond","mail":"Yo.Dupond@univ-paris1.fr","displayName":"Yo Dupond","supannCivilite":"M.","supannRoleGenerique":["Adjoint au chef de service"],"supannRoleGenerique-all":[{"name":"Adjoint(e) au chef de service","weight":"{PRIO}060","code":"{SUPANN}J10","name-gender":"Adjoint au chef de service"}]},{"uid":"zdupond","mail":"Zo.Dupond@univ-paris1.fr","displayName":"Zo Dupond","supannCivilite":"Mme","supannRoleGenerique":["Adjointe au chef de service"],"supannRoleGenerique-all":[{"name":"Adjoint(e) au chef de service","weight":"{PRIO}060","code":"{SUPANN}J10","name-gender":"Adjointe au chef de service"}]}],"category":"structures"},{"key":"diploma-L2T101","description":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","name":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","category":"diploma"}]
EOS;
expect_json('getSubGroups structures with roles', 'getSubGroups', ['key' => 'structures-DGH', 'depth' => 1, 'attrs' => 'roles,roles.supannRoleGenerique-all'], $children);

$children = <<<'EOS'
[{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"category":"structures"}]
EOS;
expect_json('getSubGroups only structures', 'getSubGroups', ['key' => 'structures-DGH', 'depth' => 1, 'filter_category' => 'structures'], $children);

$subAndSuper = <<<'EOS'
{"subGroups":[{"key":"groups-matiB1010514","ou":"Etudiants:ELP:B1010514 - Comptabilit\u00e9 d'entreprise","name":"UFR 02 - Mati\u00e8re (Semestre 1) : Comptabilit\u00e9 d'entreprise","description":"<br>\n<br>\n<br>\n","category":"elp"}],"superGroups":[]}
EOS;
expect_json('getSubAndSuperGroups diploma', 'getSubAndSuperGroups', ['key' => 'diploma-L2B101', 'depth' => 99], $subAndSuper);

$getGroup = <<<'EOS'
{"key":"structures-DGH","name":"DSIUN : Direction du syst\u00e8me d'information et des Usages Num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","rawKey":"DGH"}
EOS;
expect_json('getGroup structure', 'getGroup', ['key' => 'structures-DGH'], $getGroup);

$getGroup = <<<'EOS'
{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"roles":[{"uid":"ydupond","mail":"Yo.Dupond@univ-paris1.fr","displayName":"Yo Dupond","supannCivilite":"M.","supannRoleGenerique":["Adjoint au chef de service"]},{"uid":"zdupond","mail":"Zo.Dupond@univ-paris1.fr","displayName":"Zo Dupond","supannCivilite":"Mme","supannRoleGenerique":["Adjointe au chef de service"]}],"rawKey":"DGHA"}
EOS;
expect_json('getGroup structure', 'getGroup', ['key' => 'structures-DGHA', 'attrs' => 'roles'], $getGroup);

$getGroup = <<<'EOS'
{"key":"structures-DGHA","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"","businessCategory":"administration","labeledURI":"http:\/\/dsiun.univ-paris1.fr","up1Flags":["included"],"roles":[{"uid":"ydupond","mail":"Yo.Dupond@univ-paris1.fr","displayName":"Yo Dupond","supannCivilite":"M.","supannRoleGenerique":["Adjoint au chef de service"],"supannRoleGenerique-all":[{"name":"Adjoint(e) au chef de service","weight":"{PRIO}060","code":"{SUPANN}J10","name-gender":"Adjoint au chef de service"}]},{"uid":"zdupond","mail":"Zo.Dupond@univ-paris1.fr","displayName":"Zo Dupond","supannCivilite":"Mme","supannRoleGenerique":["Adjointe au chef de service"],"supannRoleGenerique-all":[{"name":"Adjoint(e) au chef de service","weight":"{PRIO}060","code":"{SUPANN}J10","name-gender":"Adjointe au chef de service"}]}],"rawKey":"DGHA"}
EOS;
expect_json('getGroup structure with roles', 'getGroup', ['key' => 'structures-DGHA', 'attrs' => 'roles,roles.supannRoleGenerique-all'], $getGroup);


$allGroups = <<<'EOS'
[{"key":"groups-employees.administration.DGH","ou":"Personnels:Services:DSIUN","name":"employees.administration.DGH"},{"key":"groups-employees.administration.DGHA","ou":"Personnels:Services:DSIUN-SAS","name":"DSIUN-SAS : Service des applications et services num\u00e9riques","description":"employees.administration.DGH"},{"key":"groups-grp1","ou":"GRP1","name":"Utilisateurs GRP1"},{"key":"groups-matiB1010514","ou":"Etudiants:ELP:B1010514 - Comptabilit\u00e9 d'entreprise","name":"UFR 02 - Mati\u00e8re (Semestre 1) : Comptabilit\u00e9 d'entreprise","description":"<br>\n<br>\n<br>\n"},{"key":"diploma-L2T101","description":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)","name":"L2T101 - Licence 1\u00e8re ann\u00e9e Droit (FC)"},{"key":"affiliation-faculty","name":"Tous les enseignants-chercheurs","description":"Tous les enseignants-chercheurs"},{"key":"affiliation-teacher","name":"Tous les enseignants et charg\u00e9s d'enseignement","description":"Tous les enseignants et charg\u00e9s d'enseignement"},{"key":"affiliation-student","name":"Tous les \u00e9tudiants","description":"Tous les \u00e9tudiants"},{"key":"affiliation-staff","name":"Tous les Biatss","description":"Tous les Biatss"},{"key":"affiliation-researcher","name":"Tous les chercheurs","description":"Tous les chercheurs"},{"key":"affiliation-emeritus","name":"Tous les professeurs \u00e9m\u00e9rites","description":"Tous les professeurs \u00e9m\u00e9rites"},{"key":"affiliation-affiliate","name":"Tous les invit\u00e9s","description":"Tous les invit\u00e9s"},{"key":"businessCategory-research","name":"Laboratoires de recherche","description":"Laboratoires de recherche"},{"key":"businessCategory-library","name":"Biblioth\u00e8ques","description":"Biblioth\u00e8ques"},{"key":"businessCategory-doctoralSchool","name":"\u00c9coles doctorales","description":"\u00c9coles doctorales"},{"key":"businessCategory-administration","name":"Services","description":"Services"},{"key":"businessCategory-pedagogy","name":"Composantes personnels","description":"Composantes personnels"}]
EOS;

expect_json('allGroups', 'allGroups', [], $allGroups);
