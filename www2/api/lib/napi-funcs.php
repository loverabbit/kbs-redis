<?php
// convert text'encoding to utf8
function napi_text_utf8($text) {
   global $napi_http;

   if (empty($napi_http['gbk']))
	  return iconv('GBK', 'UTF-8', $text);
   else
	  return $text;
}

// convert text'encoding to gbk
function napi_text_gbk($text) {
   global $napi_http;

   if (empty($napi_http['gbk']))
	  return iconv('UTF-8', 'GBK', $text);
   else
	  return $text;
}

// convert 'Board:id' to array
function napi_key2array($key) {
   list($board, $id) = explode(':', $key);
   if (!bbs2_record_is_unread($board, $id)) return null;
   if (!bbs2_access_board($board)) return null;

   $articles = array();
   bbs_get_records_from_id($board, $id, 0, $articles);
   if (!$articles) return null;

   return array(
      'board' => $board,
      'id' => $articles[1]['ID'],
      'user' => $articles[1]['OWNER'],
      'title' => napi_text_utf8($articles[1]['TITLE'])
   );
}

// conver username and pasword to token
function napi_token_encrypt($user, $pass) {
   return base64_encode($user) . ':' . strrev(base64_encode($pass));
}

// conver token to username and pasword
function napi_token_decrypt($token) {
   // URL中的+会被替换成空格，允许这种错误情况
   $token = str_replace(' ', '+', $token);
   list($user, $pass) = explode(':', $token);
   return array(base64_decode($user), base64_decode(strrev($pass)));
}

// get session's ip address
function napi_get_ip() {
    $hosts = $_SERVER['HTTP_X_FORWARDED_FOR'];
    if (!$hosts)
       return $_SERVER['REMOTE_ADDR'];

    $ips = explode(',', $hosts);
    $c = count($ips);
    if ($c > 1) {
       $fromhost = trim($ips[$c - 1]);
    } else {
       $fromhost = $hosts;
    }

    if (!$fromhost)
       return '127.0.0.1';

    return $fromhost;
}
?>
