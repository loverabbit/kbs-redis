<?php
require_once 'lib/napi.php';

login_init();
$napi_user = $currentuser['userid'];

$redis = new Predis_Client();
$key_reply = 'notify:' . strtolower($napi_user) . ':reply';
$key_at = 'notify:' . strtolower($napi_user) . ':at';
$mail_fullpath = bbs_setmailfile($napi_user, '.DIR');
$mail_num = bbs_getmailnum2 ($mail_fullpath);
$maildata = bbs_getmails ($mail_fullpath, 0, $mail_num);

function atomic_clear_notifications() {
    global $key_reply;
    global $key_at;
    global $redis;

    $redis->del($key_reply);
    $redis->del($key_at);
    echo "clear unread notifications success.";
}

function atomic_notifications($limit) {
    global $key_reply,$key_at,$redis,$mail_fullpath,$mail_num,$maildata;
    $n = 0 ;

    // unread mails
    {
    for ($i = 0; $i < $mail_num; $i++) {
       $mail = $maildata[$i];
       if ($mail['FLAGS'][0] == 'N') {
          $n = $n + 1;
          echo $n .'.'.$mail['OWNER'].'来信 <a class="m" href="'.$_SERVER['PHP_SELF'].'?act=mailread&box=recieved&num='. ($i + 1) .'">'. $mail['TITLE'] .'</a><br>';
       }
    }
    }
    // at
    foreach ($redis->smembers($key_at) as $v) {
       $a = napi_key2array($v);
    if($a){
       $n = $n + 1;
       echo $n .'.'.$a[user].'在 <a class="m" href="'.$_SERVER['PHP_SELF'].'?act=article&board='. $a[board] .'&id='. $a[id] .'">'. iconv('UTF-8', 'GB2312',$a[title]) .'</a> 爱特了你<br>';}
    else
       $redis->srem($key, $v);
    }
    //被回复
    foreach ($redis->smembers($key_reply) as $v) {
       $a = napi_key2array($v);
       if($a) {
          $n = $n + 1;
          if ($limit) {
             if ($n > $limit) {
                echo '...<a class="m" href="'.$_SERVER['PHP_SELF'].'?act=napi">点击查看更多</a><br>';
                break;
             }
          }
          echo $n .'.'.$a[user].'在 <a class="m" href="'.$_SERVER['PHP_SELF'].'?act=article&board='. $a[board] .'&id='. $a[id] .'">'. iconv('UTF-8', 'GB2312',$a[title]) .'</a> 回复了你<br>';
       }
       else
          $redis->srem($key, $v);
    }
    if (!$limit && !$n && !$i ) echo '<a class="m" href="'.$_SERVER['PHP_SELF'].'">没有未读消息...</a><br>';
    if (!$limit && $n && $i ) echo '<a class="m" href="'.$_SERVER['PHP_SELF'].'?act=clear_napi">清空所有消息(mail not include)...</a><br>';
}
?>