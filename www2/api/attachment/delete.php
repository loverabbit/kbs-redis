<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

global $napi_http;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'attachdel');

napi_guard_parameters(array('attid'));
napi_guard_login();

$attid = intval($napi_http['attid']);
if (!empty($napi_http['board'])) {
   napi_guard_parameters(array('id', 'board'));
   $board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['board']);
   $id    = intval($napi_http['id']);
   $ret   = bbs_attachment_del($board, $id, $attid);
} else {
   // adapt attid to filename
   $attachments = napi_call('attachment/list');
   if (empty($attachments['attachments'][$attid - 1]))
      throw new Exception('¸½¼þ²»´æÔÚ');
   $ret = bbs_upload_del_file($attachments['attachments'][$attid - 1]['filename']);
}

if (is_int($ret) && $ret != 0)
   throw new Exception(bbs_error_get_desc($ret));

if ($board)
   $attachments = napi_call('attachment/list', array('board' => $board,
													 'id' => $id));
else
   $attachments = napi_call('attachment/list');

napi_print($attachments);
?>
