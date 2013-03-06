<?php
require_once 'lib/napi.php';

napi_guard_login();

$redis = new Predis_Client();

// unread mails
{
   $mail_fullpath = bbs_setmailfile($napi_user, '.DIR');
   $mail_num = bbs_getmailnum2($mail_fullpath);
   $maildata = bbs_getmails($mail_fullpath, 0, $mail_num);

   for ($i = 0; $i < $mail_num; $i++) {
      $mail = $maildata[$i];

      if ($mail['FLAGS'][0] == 'N') {
         bbs_setmailreaded($mail_fullpath, $i);
      }
   }
}

$key = 'notify:' . strtolower($napi_user) . ':at';
$redis->del($key);
$key = 'notify:' . strtolower($napi_user) . ':reply';
$redis->del($key);

napi_print(array('result' => 0));
?>
