<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

//for api stat.
global $napi_http;
$akey = intval($napi_http['akey']);
napi_count($akey,'friendsall');

napi_guard_login();

$friends = bbs_getfriends($napi_user, 0);
if (!$friends)
    throw new Exception('ÄúÃ»ÓÐºÃÓÑ');

foreach ($friends as &$friend) {
   $user = napi_call('user/get', array('name' => $friend['ID']));
   if ($user['error']) {
      $friend = array(
         'id'      => $friend['ID'],
         'name'    => napi_text_utf8($user['error']),
         'noexist' => true
      );
   } else {
      $user['user']['description'] = $friend['EXP'];
      $friend = $user['user'];
   }
}

napi_print(array('friends' => $friends));
?>
