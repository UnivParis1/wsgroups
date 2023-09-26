<?php // -*-PHP-*-

require_once ('config/config.inc.php');
require_once ('lib/supannPerson.inc.php');


$CARTE_ETU_ALLOWED_ATTRS = [
    'sn' => 'sn', 'givenName' => 'givenName',
    'supannEtuId' => 'supannEtuId',
    'supannCodeINE' => 'supannCodeINE',
    'employeeNumber' => 'employeeNumber',
    'supannEntiteAffectationPrincipale' => 'supannEntiteAffectationPrincipale',
    'supannEtuInscription' => 'MULTI',
    'supannRefId' => 'MULTI',
];

$UP1_COMPOSANTE_TO_LIB_CMP_STICKER = [
    'UFR 08' => 'Géographie',
    'UFR 10' => 'Philo',
    'UFR 11' => 'Science Po',
    'UFR 27' => 'Maths info',
    'UFR 10 - Socio' => 'Socio',
];

$typedip_labels = [
    'XA-L1' => 'Lic 1 et 2',
    'XA-L2' => 'Lic 1 et 2',
    'XA-L3' => 'Licence 3',
    'XB-M1' => 'Master 1',
    'XB-M2' => 'Master 2',
    'YA-D1' => 'Doctorat',
    'YA-D2' => 'Doctorat',

    'XD-M1' => 'Master 1E',
    'XD-M2' => 'Master 2E',

    'UE-B4' => 'DU niv. M1',
    '03-B6' => 'HDR',
];


initPhpCAS();
if (!phpCAS::checkAuthentication()) {
    echoJson([ "error" => "Unauthorized", "cas_login_url" => 'https://' . $GLOBALS['CAS_HOST'] . $GLOBALS['CAS_CONTEXT'] . '/login' ]);
    exit(0);
}
$uid = phpCAS::getUser();

// to access supannCodeINE, employeeNumber
$LDAP_CONNECT = $GLOBALS['LDAP_CONNECT_LEVEL2'];

$impersonate = GET_or_NULL("uid");
if ($impersonate) {
    if (existsLdap($GLOBALS['PEOPLE_DN'], "(&(uid=$uid)" . $GLOBALS['LEVEL1_FILTER'] . ")")) {
        $uid = $impersonate;
    } else {
        error_log("not allowing $uid to impersonate");
    }
}

function affectation_label($code) {
    $affect = $GLOBALS['structureKeyToAll'][$code];
    if ($affect) {
        $label = str_replace('EDS-Formation-', 'EDS-', $affect['name']);
        $label = $GLOBALS['UP1_COMPOSANTE_TO_LIB_CMP_STICKER'][$label] ?? $label;
    }
    return $label ?? $code;
}

function typedip($typedip, $cursusann) {
    $typedip = removePrefix($typedip, '{SISE}');
    $cursusann = removePrefix($cursusann, '{SUPANN}');
    return $GLOBALS['typedip_labels']["$typedip-$cursusann"] ?? null;
}

function diploma_name($code_etape) {
    require_once 'lib/groups.inc.php';
    $diploma = getGroupsFromDiplomaDn(array("(ou=$code_etape)"), 1);
    return removePrefix($diploma[0]["description"], "$code_etape - ");
}

function importantEtuInscription($inscriptions, $primaryAffect, $uid) {
    $best = null;
    foreach ($inscriptions as $s) {
        $inscr = parse_supannEtuInscription($s);
	$inscr['anneeinsc'] = intval($inscr['anneeinsc']);
        if (!$best || $inscr['anneeinsc'] > $best['anneeinsc'] || $inscr['anneeinsc'] === $best['anneeinsc'] && $inscr['typedip'] > $best['typedip']) {
            $best = $inscr;
        }
    }
    if ($best) {
        if ($best['affect'] !== $primaryAffect) error_log("weird affectation for $uid");
        $code_etape = removePrefix($best['etape'], '{UAI:0751717J}');
        $best = [
            'affect' => affectation_label($best['affect']),
            'etape' => $code_etape,
            'anneeinsc' => $best['anneeinsc'],
            'typedip' => typedip($best['typedip'], $best['cursusann']) ?? diploma_name($code_etape),
        ];
    }
    return $best;
}

$attrs = getFirstLdapInfo($PEOPLE_DN, "(&(uid=$uid)(supannEntiteAffectationPrincipale=*)(supannEtuInscription=*))", $CARTE_ETU_ALLOWED_ATTRS);

if (!$attrs || !$attrs['employeeNumber'] || $attrs["supannEntiteAffectationPrincipale"] === 'COV1') {
    $error = !$attrs ? "Inconnu" : ($attrs["supannEntiteAffectationPrincipale"] === 'COV1' ? "Les étudiants de l'IAE ne sont pas autorisés. Nous devrions bientôt fournir une version spécifique IAE..." : 
        "Pour avoir accès à la carte d'étudiant dématérialisée, il faut actuellement :
        - soit avoir une carte physique
        - soit avoir perdu une carte physique
        - soit une carte physique est imprimée mais vous ne l'avez pas encore reçu
        
        Vous n'êtes donc pas éligible (pour l'instant, nous travaillons pour étendre les cas éligibles...)");
    echoJson([ "error" => $error ]);
    exit(0);
}

$ids = getAndUnset($attrs, 'supannRefId');
if ($ids) {
    foreach ($ids as $id) {
        $ESCN = removePrefixOrNULL($id, '{ESCN}');
        if ($ESCN) $attrs['ESCN'] = $ESCN;
    }
}

$attrs['importantEtuInscription'] = importantEtuInscription(getAndUnset($attrs, 'supannEtuInscription'), $primaryAffect, $uid);

echoJson($attrs);

?>
