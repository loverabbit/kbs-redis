<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

//for api stat.
global $napi_http;
$akey = intval($napi_http['akey']);
napi_count($akey,'friendsget');

napi_guard_login();

$friends = bbs_getonlinefriends();
if (!$friends)
    throw new Exception('暂时没有好友在线');

$result = array();
foreach ($friends as &$friend) {
   $user = napi_call('user/get', array('name' => $friend['userid']));
   $result[] = array_merge($user['user'], array(
      'from'      => $friend['userfrom'],
      'invisible' => $friend['invisible'],
      'mode'      => napi_text_utf8($friend['mode']),
      'idle'      => $friend['idle']
   ));
}

napi_print(array('friends' => $result));
?>
