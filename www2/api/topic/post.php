<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

napi_guard_parameters(array('board', 'title', 'content'));
napi_guard_login();

global $napi_http;
$notopten = !empty($napi_http['notopten']);
$noquote = !empty($napi_http['noquote']);
$anony = !empty($napi_http['anony']);
$type = intval($napi_http['type']) % 5;
$reid = intval($napi_http['reid']);
$board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['board']);

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'topicpost');

$arr = array();
if (is_null(bbs_safe_getboard(0, $board, $arr)))
   throw new Exception('无效版面');

// 在内容后自动增加引用
$content = napi_text_gbk($napi_http['content']);
if (!$noquote && $reid != 0)
   $content .= "\n" . get_quote($board, $reid) . "\n";

$type_map = array(3, 5, 6, 7, 8);
$result = bbs_postarticle($board,
                          napi_text_gbk($napi_http['title']),
                          $content,
                          0, /* signature */
                          $reid,
                          false, /* outgo */
                          $anony, /* anonymous */
                          false, /* mailback */
                          false, /* tex */
                          $type_map[$type], /* system type */
                          $notopten);

if ($result <= 0) {
   switch ($result) {
   case 0:
      throw new Exception('无效用户');
   case -1:
      throw new Exception('错误的讨论区名称');
   case -2:
      throw new Exception('二级目录版');
   case -3:
      throw new Exception('标题为NULL');
   case -4:
      throw new Exception('此讨论区是唯读的, 或是您尚无权限在此发表文章');
   case -5:
      throw new Exception('很抱歉, 你被版务人员停止了本版的post权利');
   case -6:
      throw new Exception('两次发文间隔过密, 请休息几秒后再试');
   case -7:
      throw new Exception('索引文件不存在');
   case -8:
      throw new Exception('本文不能回复');
   default:
      throw new Exception('发文失败：' . $result);
   }
}

$topics = napi_call('topic/get', array(
   'board' => $napi_http['board'],
   'id'    => $result,
   'limit' => 1,
   'token' => $napi_http['token']
));

napi_print(array('topic' => $topics['topics'][0]));

function get_quote($board, $reid) 
{
   global $napi_http;
   $topics = napi_call('topic/get', array(
      'gbk'   => true,
      'board' => $board,
      'id'    => $reid,
      'token' => $napi_http['token']
   ));

   $topic = $topics['topics'][0];

   $result = "【 在 {$topic['author']} 的大作中提到: 】";
   $lines = explode("\n", $topic['content']);
   $count = 0;
   foreach ($lines as &$line) {
      ++$count;
      if ($count > 4) {
         $return .= "\n: ......";
         break;
      }

      $result .= "\n: " . $line;
   }

   return $result;
}
?>
