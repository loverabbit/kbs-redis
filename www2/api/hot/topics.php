<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/common.php';

global $napi_http;
$topics = array();
//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'hottopics');

if (!empty($napi_http['flat'])) {
   for ($i = 0; $i < 12; $i++) {
      $key = 'day_sec' . $i;
      $nar = get_hots($key);
      foreach ($nar as &$nr) $nr['section'] = $i;

      $topics = array_merge($topics, $nar);
   }
} else {
   for ($i = 0; $i < 12; $i++) {
      $key = 'day_sec' . $i;
      $topics[] = array(
         'section' => $i,
         'description' => napi_text_utf8(constant('BBS_SECNAME'.$i.'_0')),
         'topics' => get_hots($key)
      );
   }
}

napi_print(array('topics' => $topics));
?>
