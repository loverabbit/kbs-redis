<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/Predis.php';

napi_guard_parameters(array('key'));

global $napi_http;
$key = $napi_http['key'];

$redis = new Predis_Client();
$k_ip = 'count:' . $key . ':ip';
$k_co = 'count:' . $key;

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
if (!$ip)
   $ip = $_SERVER['REMOTE_ADDR'];
if (!$ip)
   $ip = '127.0.0.1';

// add count
$exists = $redis->exists($k_ip);
if ($redis->sadd($k_ip, $ip)) {
   if (!$exists)
      $redis->expire($k_ip, 3600);
   $redis->incr($k_co);
}

napi_print(array('count' => intval($redis->get($k_co))));
?>

