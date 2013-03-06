<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();

global $napi_http;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'favadd');

if (bbs_load_favboard(0) == -1)
    throw new Exception("错误的参数");

if (!empty($napi_http['bname'])) {
    $add_bname=trim($napi_http['bname']);
    napi_print(array('result' => bbs_add_favboard($add_bname)));
} else if (!empty($napi_http['dname'])) {
    $add_dname=trim($napi_http["dname"]);
    napi_print(array('result' => bbs_add_favboarddir($add_dname)));
} else
   throw new Exception('无效参数');
?>
