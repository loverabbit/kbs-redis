<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/Predis.php';

//for api stat.
global $napi_http;
$akey = intval($napi_http['akey']);
napi_count($akey,'hotboards');

$redis = new Predis_client();
$raw = $redis->lrange('stat:board', 0, 10);

foreach ($raw as $r) {
   list($section, $board, $visits, $stay, $description) = explode(':', $r);
   $boards[] = array(
      'name' => $board,
      'section' => intval($section),
      'leaf' => true,
      'description' => napi_text_utf8($description)
   );
}

napi_print(array('boards' => $boards));
?>
