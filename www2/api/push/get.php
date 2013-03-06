<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();

global $napi_user;

$tokens = array();
$redis = new Predis_client();

$ret = array();
$key = 'itoken:' . strtolower($napi_user);
foreach ($redis->smembers($key) as $v)
    $ret[] = $v;

if ($ret) $tokens['tokens'] = $ret;

napi_print($tokens);

?>
