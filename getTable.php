<?php

require_once ('lib/supannPerson.inc.php');

$name = $_GET["name"];

if ($name === 'employeeTypes') {
    require_once 'lib/employeeTypes.inc.php';
    echoJson($GLOBALS['employeeTypes']);
}
