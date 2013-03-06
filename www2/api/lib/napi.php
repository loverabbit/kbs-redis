<?php
global $napi_time;
$napi_time = round(microtime(true) * 1000);

// don't include this file in wForum mode
if (!defined('_BBS_FUNCS_PHP_')) {
   require_once dirname(__FILE__) . '/../../www2-funcs.php';
   require_once dirname(__FILE__) . '/../../www2-board.php';
}

require_once dirname(__FILE__) . '/napi-funcs.php';
require_once dirname(__FILE__) . '/Predis.php';

global $napi_mode;
global $napi_http;
global $napi_user;
global $napi_erro;

if (empty($napi_http)) {
   $napi_http = array();

   foreach($_REQUEST as $k => $v)
      $napi_http[$k] = $v;
}

if (empty($napi_mode))
   $napi_mode = $_REQUEST['napi_mode'] ? $_REQUEST['napi_mode'] : 'json';

set_exception_handler('napi_exception_handler');

// show error when caught exception
function napi_exception_handler($except) {
   napi_error($except->getMessage(), $except->getCode());
}

// check whether logined and return error if not
function napi_try_login() {
   global $napi_http;
   global $napi_user;
   global $loginok;
   global $currentuser;

   // anti multiple login
   if ($loginok && $currentuser['userid'] != 'guest') {
      $napi_user = $currentuser['userid'];
      return 0;
   }

   // auth by token
   if (!empty($napi_http['token'])) {
      list($user, $pass) = napi_token_decrypt($napi_http['token']);
      if (bbs_checkuserpasswd($user, $pass, true)) {
         $napi_user = 'guest';
         bbs_setuser_nologin('guest');
         return 1;
      }

      // www2 compatible
      $loginok = true;
      $currentuser['userid'] = $napi_user = $user;

      // set user and session
      $ip = napi_get_ip();
      bbs_setfromhost($ip, $ip);
      bbs_setuser_nologin($user);
      napi_session_init($user);
      return 0;
   }

   // auth by cookie
   login_init();

   $napi_user = $currentuser['userid'];
   return ($loginok && $napi_user != 'guest') ? 0 : 2;
}

function napi_guard_login() {
   $ret = napi_try_login();
   switch ($ret) {
   case 0:
      return;
   case 1:
      throw new Exception('无效token', 1);
   case 2:
      throw new Exception('该操作需要登录', 1);
   default:
      throw new Exception('未知错误：' . $ret, 1);
   }
}

// check whether parameters is set and return error is not
function napi_guard_parameters($pas) {
   global $napi_mode;
   global $napi_http;

   if ($napi_mode == 'php')
      return;

   foreach($pas as $pa)
      if (!isset($napi_http[$pa])) {
         throw new Exception('参数 \'' . $pa . '\' 是必需的');
      }
}

// login by using one global session
function napi_session_init($user) {
   $redis = new Predis_client();

   // try to login by saved session
   $key = 'api:session:' . $user;
   $value = $redis->get($key);
   if (!empty($value)) {
      list($utmpnum, $utmpkey) = explode(':', $value);
      $currentuinfo = array();
      if (0 == bbs_setonlineuser($user, intval($utmpnum), intval($utmpkey), $currentuinfo))
         return;
   }

   // otherwise we set a new session
   bbs_wwwlogin(1);
   $data = array();
   $utmpnum = bbs_getcurrentuinfo($data);
   // and save it
   $redis->set($key, $utmpnum . ':' . $data['utmpkey']);
   $redis->expire($key, 3600);
}

// print json's header 
function napi_header_json() {
   header('Content-type: application/json');
   header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
   header('Pragma: no-cache');
}

function napi_header_js() {
   header('Content-type: text/javascript');
   header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
   header('Pragma: no-cache');
}

// print result
function napi_print($ret) {
   global $napi_time;
   global $napi_mode;
   global $napi_http;

   if ($napi_mode != 'php') {
      $ret['time'] = time();
      $ret['cost'] = round(1000 * microtime(true) - $napi_time);
      $ret['success'] = isset($ret['success']) ? $ret['success'] : true;
   }

   if ($napi_mode == 'json' || $napi_mode == 'js') {
      if ($napi_mode == 'json')
         napi_header_json();
      else
         napi_header_js();

      // check for JSONP
      if (!empty($napi_http['callback']))
         $output = $napi_http['callback'] . '(' . json_encode($ret) . ');';
      else
         $output = json_encode($ret);

      // can we gzip
      if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
         $output = gzencode($output);
         header('Content-Encoding: gzip');
         header('Content-Length: '.strlen($output));
      }

      echo $output;
   } else if ($napi_mode == 'php') {
      global $napi_relt;
      $napi_relt = $ret;
   }
}

// print an error and quit
function napi_error($err, $code) {
   global $napi_erro;
   $napi_erro = $err;

   $result = array('success' => false, 'error' => napi_text_utf8($err));
   if ($code == 1)
      $result['login'] = false;

   napi_print($result);
   exit;
}

// 按小时统计不同客户端的api一年的调用量
function napi_count($akey, $type) {
   $redis = new Predis_client();
   $date = date('njG');				//e.g. 12411  月-日-小时
   $key = 'api_'.$type.':'.$akey.':'.$date;	//e.g. api_user:0:12411   $akey means different client, default:'0'
   $total = 'api_'.$type.':'.$date;	//e.g. api_user:12411
   // add count
   $redis->incr($key);
   $redis->incr($total);
}
?>
