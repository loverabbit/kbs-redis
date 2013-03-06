<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/Predis.php';

napi_guard_parameters(array('keys'));
napi_try_login();

global $napi_http;
$q = $napi_http['keys'];
$s = 0;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'searchtopic');

$charset = empty($napi_http['charset']) ? 'UTF-8' : $napi_http['charset'];

$limit = intval($napi_http['limit']);
$start = intval($napi_http['start']);
if ($limit == 0)
   $limit = 10;
if ($start < 0) 
   throw new Exception('无效参数"start"');
if ($limit < 0)
   throw new Exception('无效参数"limit"');

// 搜索
$api_mode = true;
$api_start = $start;
$n = $limit;
include dirname(__FILE__) . '/../../search/search.php';

// 转换
$result = array();
foreach ($docs as $doc)
{
   $counts[] = 'count:' . $doc->board . ':' . $doc->id;
   $result[] = array(
      'id'     => $doc->id,
      'title'  => $doc->title,
      'board'  => $doc->board,
      'author' => $doc->author,
      'time'   => $doc->time,
      'mark'   => !!$doc->mark
   );
}

// 阅读数
$redis = new Predis_client();
$counts = $redis->mget($counts);
foreach ($result as $i => &$topic)
   $topic['read'] = intval($counts[$i]);

napi_print(array('topics' => $result));
?>
