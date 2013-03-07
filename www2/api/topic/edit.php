<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

napi_guard_parameters(array('board', 'id'));
napi_guard_login();

global $napi_http;
$board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['board']);
$id    = intval($napi_http['id']);
$ftype = empty($napi_http['top']) ? 0 : 11;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'topicedit');

$arr = array();
if (is_null(bbs_safe_getboard(0, $board, $arr)))
   throw new Exception('无效版面');

$articles = array();
if (!bbs_get_records_from_id($board, $id, $ftype, $articles))
   throw new Exception('错误的文章号');

if (!empty($napi_http['title'])) {
   $ret = bbs_edittitle($board, $id, napi_text_gbk(rtrim($napi_http['title'])), $ftype, 0);
   if ($ret != 0)
       switch ($ret) {
       case -1:
           throw new Exception('错误的讨论区');
       case -2:
           throw new Exception('对不起，该讨论区不能修改标题');
       case -3:
           throw new Exception('对不起，该讨论区为只读讨论区');
       case -4:
           throw new Exception('错误的文章号');
       case -5:
           throw new Exception('对不起，您已被停止在该版的发文权限');
       case -6:
           throw new Exception('对不起，您无权修改本文');
       case -7:
           throw new Exception('标题含有不雅用字');
       case -8:
           throw new Exception('对不起，当前模式无法修改标题');
       case -9:
           throw new Exception('标题过长');
       default:
           throw new Exception('系统错误，请联系管理员');
       }
}

if (!empty($napi_http['content'])) {
   $ret = bbs_updatearticle($board, $articles[1]['ID'], $articles[1]['FILENAME'], napi_text_gbk($napi_http['content']));
   if ($ret != 0)
      switch ($ret) {
         case -1:
             throw new Exception('修改文章失败，文章可能含有不恰当内容');
         case -10:
             throw new Exception('找不到文件!');
         default:
             throw new Exception('系统错误，请联系管理员');
      }
}

$topics = napi_call('topic/get', array(
   'board' => $board,
   'id'    => $id,
   'limit' => 1,
   'token' => $napi_http['token']
));

napi_print(array('topic' => $topics['topics'][0]));
?>
