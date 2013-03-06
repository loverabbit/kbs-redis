<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

napi_guard_parameters(array('id', 'board', 'attid'));
napi_try_login();
global $napi_http;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'attachget');

$napi_http['raw'] = 1;
$napi_http['limit'] = 1;
$topic = napi_call('topic/get', $napi_http);
$topic = $topic['topics'][0];
if (!$topic)
   throw new Exception('无效文章');

$attachments = napi_call('attachment/list', $napi_http);
$attch = $attachments['attachments'][intval($napi_http['attid']) - 1];
if (!$attch)
   throw new Exception('无效附件');
$filename = 'boards/' . $topic['board'] . '/' . $topic['filename'];

bbs_file_output_attachment($filename, $attch['pos'], $attch['filename']);
?>
