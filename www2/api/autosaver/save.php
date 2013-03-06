<?php
require_once dirname(__FILE__) . '/../lib/Predis.php';
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('title', 'content'));
napi_guard_login();
global $napi_http;
global $napi_user;

$redis = new Predis_Client();
$prefix = 'service:save:' . $napi_user . ':';

$key = $prefix . 'title';
$redis->set($key, $napi_http['title']);
$redis->expire($key, 3600 * 24 * 3); // save for 3 days
$key = $prefix . 'content';
$redis->set($key, $napi_http['content']);
$redis->expire($key, 3600 * 24 * 3); // save for 3 days
?>
