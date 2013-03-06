<?php
require_once dirname(__FILE__) . '/../lib/Predis.php';
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();
global $napi_user;

$redis = new Predis_Client();
$prefix = 'service:save:' . $napi_user . ':';

$key = $prefix . 'title';
$redis->del($key);
$key = $prefix . 'content';
$redis->del($key);
?>
