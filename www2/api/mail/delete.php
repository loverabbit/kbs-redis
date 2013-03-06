<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('type', 'id'));
napi_guard_login();

global $napi_http;
global $napi_user;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'maildelete');

$type = intval($napi_http['type']) % 3;
$id = intval($napi_http['id']);
$typeMap = array('.DIR', '.SENT', '.DELETED');
$path = bbs_setmailfile($napi_user, $typeMap[$type]);

// 找到符合id的邮件
$mails = bbs_getmails($path, $id, $id + 1);
$mail = $mails[0];
if (!$mail)
   throw new Exception('不存在此邮件');

napi_print(array('result' => bbs_delmail($typeMap[$type], $mail['FILENAME'])));
?>
