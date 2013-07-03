<?php

require_once ('./config-auth.inc.php');
require_once ('./config.inc.php');

function GET_ldapFilterSafe($name) {
    return ldap_escape_string($_GET[$name]);
}
function GET_ldapFilterSafe_or_NULL($name) {
    return isset($_GET[$name]) ? ldap_escape_string($_GET[$name]) : NULL;
}
function GET_or_NULL($name) {
  return isset($_GET[$name]) ? $_GET[$name] : NULL;
}

function GET_uid() {
  return isset($_SERVER["HTTP_CAS_USER"]) ? $_SERVER["HTTP_CAS_USER"] : ''; // CAS-User
}


function computeOneFilter($attr, $valsS) {
    $vals = explode('|', $valsS);
    $orFilter = '';
    foreach ($vals as $val)
      $orFilter .= "($attr=$val)";
    return sizeof($vals) > 1 ? "(|$orFilter)" : $orFilter;
}
function computeFilter($filters, $not) {
   $r = '';
  foreach ($filters as $attr => $vals) {
    if (!$vals) continue;
    $one = computeOneFilter($attr, $vals);
    $r .= $not ? "(!$one)" : $one;
  }
  return $r;
}

function ldapAnd($l) {
  $r = implode('', $l);
  return count($l) > 1 ? "(&$r)" : $r;
}
function ldapOr($l) {
  $r = implode('', $l);
  return count($l) > 1 ? "(|$r)" : $r;
}

function wordsFilterRaw($searchedAttrs, $token) {
  $and = array();
  $words = preg_split("/[\s,]+/", $token, -1, PREG_SPLIT_NO_EMPTY);
  foreach ($words as $tok) {
    $or = array();
    foreach ($searchedAttrs as $attr => $prefix) $or[] = "($attr=" . ($prefix ? $prefix : '') . "*$tok*)";
    $and[] = ldapOr($or);
  }
  return ldapAnd($and);
}


function wordsFilter($searchedAttrs, $token) {
  $searchedAttrsRaw = array();
  foreach ($searchedAttrs as $attr) $searchedAttrsRaw[$attr] = null;
  return wordsFilterRaw($searchedAttrsRaw, $token);
}

function getLdapInfoMultiFilters($base, $filters, $attributes_map, $uniqueField, $sizelimit = 0, $timelimit = 0) {
  $rr = array();
  foreach ($filters as $filter) {
    $rr[] = getLdapInfo($base, $filter, $attributes_map, $sizelimit, $timelimit);
  }
  $r = mergeArraysNoDuplicateKeys($rr, $uniqueField);
  if ($sizelimit > 0)
      $r = array_splice($r, 0, $sizelimit);
  return $r;
}

function getFirstLdapInfo($base, $filter, $attributes_map) {
  $r = getLdapInfo($base, $filter, $attributes_map, 1);
  return $r ? $r[0] : NULL;
}

function existsLdap($base, $filter) {
  $r = getLdapInfo($base, $filter, array(), 1);
  return (bool) $r;
}

function getLdapInfo($base, $filter, $attributes_map, $sizelimit = 0, $timelimit = 0) {
  global $DEBUG;

  $before = microtime(true);

  $ds = global_ldap_open();

  if ($DEBUG) error_log("searching $base for $filter");
  $search_result = @ldap_search($ds, $base, $filter, array_keys($attributes_map), 0, $sizelimit, $timelimit);
  if (!$search_result) return array();
  $all_entries = ldap_get_entries($ds, $search_result);
  if ($DEBUG) error_log("found " . $all_entries['count'] . " results");

  unset($all_entries["count"]);
  $r = array();  
  foreach ($all_entries as $entry) {
    $map = array();
    foreach ($attributes_map as $ldap_attr => $attr) {
      $ldap_attr_ = strtolower($ldap_attr);
      if (isset($entry[$ldap_attr_])) {
	$vals = $entry[$ldap_attr_];
	if ($attr == "MULTI") {
	  // no remapping, but is multi-valued attr
	  unset($vals["count"]);
	  $map[$ldap_attr] = $vals;
	} else {
	  $map[$attr] = $vals["0"];
	}
      }
    }
    $r[] = $map;
  }

  //echo sprintf("// Elapsed %f\t%3d answers for $filter on $base\n", $before - microtime(true), count($r));

  return $r;
}

function global_ldap_open() {
    global $ldapDS;
    if (!$ldapDS) {
	global $LDAP_CONNECT;
	$ldapDS = ldap_connect($LDAP_CONNECT['HOST']);
	if (!$ldapDS) exit("error: connection to " . $LDAP_CONNECT['HOST'] . " failed");

	if (!ldap_bind($ldapDS,$LDAP_CONNECT['BIND_DN'],$LDAP_CONNECT['BIND_PASSWORD'])) exit("error: failed to bind using " . $LDAP_CONNECT['BIND_DN']);
    }
    return $ldapDS;
}

function ensure_ldap_close() {
    global $ldapDS;
    if ($ldapDS) {
      ldap_close($ldapDS);
      $ldapDS = NULL;
    }
}

function echoJson($array) {
  ensure_ldap_close();
  header('Content-type: application/json; charset=UTF-8');
  if (isset($_GET["callback"]))
    echo $_GET["callback"] . "(" . json_encode($array) . ");";
  else
    echo json_encode($array);  
}

function identiqueMap($list) {
    $map = array();
    foreach ($list as $e) $map[$e] = $e;
    return $map;
}

function mergeArraysNoDuplicateKeys($rr, $uniqueField) {
    $keys = array();
    $r = array();
    foreach ($rr as $one_array) {
	foreach ($one_array as $e) {
	    $key = $e[$uniqueField];
	    if (isset($keys[$key])) continue;
	    $keys[$key] = 1;
	    $r[] = $e;
	}
    }
    return $r;
}

function exact_match_first($r, $token) {
    $exact = array();
    $i = 0;
    while ($i < count($r)) {
	$e = $r[$i];
	if (in_array($token, array_values($e))) {
	    $exact[] = $e;
	    array_splice($r, $i, 1);
	} else {
	    $i++;
	}
    }
    return array_merge($exact, $r);
}

function remove_businessCategory($r) {
    foreach ($r as &$e) {
	unset($e["businessCategory"]);
    }
    return $r;
}

// after exact_match_first, rawKey can be safely removed: it is used for search token=matiXXXXX, "key" will contain groups-matiXXXXXX and won't match. "rawKey" will match!
function remove_rawKey(&$r) {
    foreach ($r as &$e) {
	unset($e["rawKey"]);
    }
}
// modifyTimestamp is only used by allGroups
function remove_modifyTimestamp(&$r) {
    foreach ($r as &$e) {
	unset($e["modifyTimestamp"]);
    }
}

function contains($hay, $needle) {
    return strpos($hay, $needle) !== false;
}

function startsWith($hay, $needle) {
  return substr($hay, 0, strlen($needle)) === $needle;
}

function removePrefix($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : $s;
}
function removePrefixOrNULL($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : NULL;
}

function error($msg) {
   header("HTTP/1.0 400 $msg");
   echo("// $msg\n");
}

// taken more mantisbt
function ldap_escape_string( $p_string ) {
  $t_find = array( '\\', '*', '(', ')', '/', "\x00" );
  $t_replace = array( '\5c', '\2a', '\28', '\29', '\2f', '\00' );

  $t_string = str_replace( $t_find, $t_replace, $p_string );

  return $t_string;
}

function mayRemap($map, $k) {
  return isset($map[$k]) ? $map[$k] : $k;
}

function array_flatten_non_rec($r) {
    return sizeof($r) > 0 ? call_user_func_array('array_merge', $r) : array();
}

function getAndUnset(&$a, $prop) {
  if (isset($a[$prop])) {
    $v = $a[$prop];
    unset($a[$prop]);
    return $v;
  } else {
    return null;
  }
}

?>
