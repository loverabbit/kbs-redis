<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();

global $napi_http;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'favdel');

if (!empty($napi_http['bname'])) {
	$add_bname=trim($napi_http['bname']);
	napi_print(array('result' => bbs_del_favboard(0,$delete_s)));
} else if (!empty($napi_http['dname'])) {
	$add_dname=trim($napi_http["dname"]);
	napi_print(array('result' => bbs_add_favboarddir($add_dname)));
} else
   throw new Exception('无效参数');
?>
