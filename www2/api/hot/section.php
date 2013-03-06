<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/common.php';

global $napi_http;
napi_guard_parameters(array('section'));

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'hotsec');

$key = 'day_sec' . intval($napi_http['section']);;
print_hots($key);
?>
