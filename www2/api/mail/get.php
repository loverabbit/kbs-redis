<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('type', 'id'));
napi_guard_login();

global $napi_http;
global $napi_user;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'mailget');

$type = intval($napi_http['type']) % 3;
$id = intval($napi_http['id']);
$typeMap = array('.DIR', '.SENT', '.DELETED');
$path = bbs_setmailfile($napi_user, $typeMap[$type]);

// 找到符合id的邮件
$mails = bbs_getmails($path, $id, $id + 1);
$mail = $mails[0];
if (!$mail)
   throw new Exception('不存在此邮件');

// 是否标记已读
if (empty($napi_http['peek']))
   bbs_setmailreaded($path, $id);

list($content, $quote) = filter(bbs_originfile(dirname($path) . '/' . $mail['FILENAME']));
$result = array(
   'id'      => $id,
   'size'    => $mail['EFFSIZE'],
   'unread'  => $mail['FLAGS'][0] == 'N',
   'author'  => $mail['OWNER'],
   'time'    => $mail['POSTTIME'],
   'content' => napi_text_utf8($content),
   'quote'   => napi_text_utf8($quote),
   'title'   => napi_text_utf8($mail['TITLE'])
);

napi_print(array('mail' => $result));

function filter($str)
{
   $ret = '';
   $quote = '';

   // Filter out signature
   $i = strrpos($str, "\n--\n");
   if ($i !== false) $str = substr($str, 0, $i);

   $skip = 0;
   $tok = strtok($str, "\n");
   while ($tok !== false) {
       if ($skip > 0) {
           --$skip;

           $tok = strtok("\n");
           continue;
       }

       // Filter out and store quotes
       if (($tok[0] == chr(161) && $tok[1] == chr(190)) ||
           $tok[0] == ':')
       {
           if ($tok[0] == ':') {
              $quote .= substr($tok, 2) . "\n";
           }

           $tok = strtok("\n");
           continue;
       }

       $ret .= $tok . "\n";

       $tok = strtok("\n");
   }

   // Force free memory
   strtok('', '');

   // constrain quote's length
   if (strlen($quote) > 100) {
      $quote = substr($quote, 0, 100) . '...';
   }

   return array(rtrim($ret), rtrim($quote));
}
?>
