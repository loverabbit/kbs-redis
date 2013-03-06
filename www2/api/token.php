<?php
require_once dirname(__FILE__) . '/lib/napi.php';
require_once dirname(__FILE__) . '/../www2-funcs.php';

global $napi_http;
global $napi_user;
napi_guard_parameters(array('user', 'pass'));

if (bbs_checkpasswd($napi_http['user'], $napi_http['pass'], false))
  throw new Exception('用户名或密码错误');

$user = array();
bbs_getuser($napi_http['user'], $user);

napi_print(array(
   'id' => $user['userid'],
   'name' => napi_text_utf8($user['username']),
   'token' => napi_token_encrypt($user['userid'], $user['md5passwd'])
));
?>
