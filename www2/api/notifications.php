<?php
require_once 'lib/napi.php';
global $napi_user;

napi_guard_login();

$notifications = array();
$redis = new Predis_Client();

// unread mails
{
   $mail_fullpath = bbs_setmailfile($napi_user, '.DIR');
   $mail_num = bbs_getmailnum2 ($mail_fullpath);
   $maildata = bbs_getmails ($mail_fullpath, 0, $mail_num);

   for ($i = 0; $i < $mail_num; $i++) {
      $mail = $maildata[$i];

      if ($mail['FLAGS'][0] == 'N') {
         $notifications['mails'][] = array(
            'id' => $i,
            'sender' => $mail['OWNER'],
            'title' => napi_text_utf8($mail['TITLE']),
            'time' => $mail['POSTTIME']
         );
      }
   }
}

// at
$ret = array();
$key = 'notify:' . strtolower($napi_user) . ':at';
foreach ($redis->smembers($key) as $v) {
    $a = napi_key2array($v);
    if ($a)
        $ret[] = $a;
    else
        $redis->srem($key, $v);
}
if ($ret) $notifications['ats'] = $ret;

// replies
$ret = array();
$key = 'notify:' . strtolower($napi_user) . ':reply';
foreach ($redis->smembers($key) as $v) {
    $a = napi_key2array($v);
    if ($a)
        $ret[] = $a;
    else
        $redis->srem($key, $v);
}
if ($ret) $notifications['replies'] = $ret;

napi_print($notifications);
?>
