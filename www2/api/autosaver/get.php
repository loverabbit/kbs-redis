<?php
require_once dirname(__FILE__) . '/../lib/Predis.php';
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();
global $napi_user;

$redis = new Predis_Client();
$prefix = 'service:save:' . $napi_user . ':';

$key = $prefix . 'title';
$title = $redis->get($key);
$key = $prefix . 'content';
$content = $redis->get($key);
napi_print(array('title' => $title, 'content' => $content));
?>
