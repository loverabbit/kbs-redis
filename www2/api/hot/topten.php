<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/common.php';

//for api stat.
global $napi_http;
$akey = intval($napi_http['akey']);
napi_count($akey,'topten');

$key = 'day';
print_hots($key);
?>
