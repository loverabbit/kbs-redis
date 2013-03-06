<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

global $napi_http;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'attachlist');

if (!empty($napi_http['board'])) {
   napi_guard_parameters(array('id', 'board'));
   napi_try_login();
   $board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['board']);
   $id = intval($napi_http['id']);
   $raw = bbs_attachment_list($board, $id);
} else {
   napi_guard_login();
   $raw = bbs_upload_read_fileinfo();
}

$size = 0;
$attachments = array();; 
foreach ($raw as $i => $att) {
   $size += $att['size'];
   $attid = $i + 1;
   $filename = napi_text_utf8($att['name']);
   $encoded = urlencode($filename);
   $attachments[] = array(
      'id'       => $attid,
      'filename' => $filename,
      'pos'      => $att['pos'],
      'size'     => $att['size'],
      'url'      => 'http://' . $_SERVER['SERVER_NAME'] . "/api/attachment/get/$board/$id/$attid/$encoded",
   );
}

napi_print(array(
   'size'        => $size,
   'attachments' => $attachments
));
?>
