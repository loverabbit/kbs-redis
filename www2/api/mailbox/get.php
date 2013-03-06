<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('type'));
napi_guard_login();

global $napi_http;
global $napi_user;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'mailboxget');

$type = intval($napi_http['type']) % 3;
$typeMap = array('.DIR', '.SENT', '.DELETED');
$path = bbs_setmailfile($napi_user, $typeMap[$type]);

$start = intval($napi_http['start']);
$limit = intval($napi_http['limit']);
$total = bbs_getmailnum2($path);

if ($limit == 0)
   $limit = 10;
if ($start < 0) 
   throw new Exception('无效参数"start"');
if ($limit < 0)
   throw new Exception('无效参数"limit"');

$i = $total - $start - $limit;
if ($i < 0) {
   $limit += $i;
   $i = 0;
}
$mails = bbs_getmails($path, $i, $limit);

$result = array();
foreach ($mails as $j => &$mail) {
   $result[] = array(
      'id'      => $i + $j,
      'size'    => $mail['EFFSIZE'],
      'unread'  => $mail['FLAGS'][0] == 'N',
      'author'  => $mail['OWNER'],
      'time'    => $mail['POSTTIME'],
      'title'   => napi_text_utf8($mail['TITLE'])
   );
}

$result = array_reverse($result);
napi_print(array('mails' => $result));
?>
