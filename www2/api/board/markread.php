<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('name'));
napi_guard_login();

//for api stat.
global $napi_http;
$akey = intval($napi_http['akey']);
napi_count($akey,'boardmarkread');

$board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['name']);
$arr = array();
if (is_null(bbs_safe_getboard(0, $board, $arr))) {
   throw new Exception('°æÃæ²»´æÔÚ');
}

$board = $arr['NAME'];
bbs_brcclear($board);

napi_print(array('result' => 0));
?>
