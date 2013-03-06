<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/Predis.php';

napi_guard_parameters(array('key'));

global $napi_http;
$key = $napi_http['key'];
$k_co = 'count:' . $key;

$redis = new Predis_Client();
napi_print(array('count' => intval($redis->get($k_co))));
?>
