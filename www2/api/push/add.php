<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();
napi_guard_parameters(array('iToken'));

global $napi_http;
global $napi_user;

$redis = new Predis_client();
$key = 'itoken:' . strtolower($napi_user);
$redis->sadd($key, $napi_http['iToken']);

foreach ($redis->smembers($key) as $v) {
    if ($v == $napi_http['iToken']) {
        napi_print(array('result' => 0));
    }
}

throw new Exception('°ó¶¨Ê§°Ü');
?>
