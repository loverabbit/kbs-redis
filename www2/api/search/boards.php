<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('name'));
napi_try_login();

global $napi_http;
$name = napi_text_gbk(trim($napi_http['name']));
$exact = !empty($napi_http['exact']);

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'searchboard');

// ËÑË÷°æÃæ
$boards = array();
bbs_searchboard($name, $exact, $boards);

$result = array();
foreach ($boards as $board)
   $result[] = array(
      'name' => $board['NAME'],
      'leaf' => true,
      'description' => napi_text_utf8($board['TITLE'])
   );

napi_print(array('boards' => $result));
?>
