<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/Predis.php';

napi_guard_parameters(array('name'));
napi_try_login();

global $napi_http;

$board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['name']);

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'boardget');

// get right board name
$arr = array();
if (is_null(bbs_safe_getboard(0, $board, $arr)))
   throw new Exception('无效版面');
$board = $arr['NAME'];
$bid = $arr['BID'];

// 如果是目录版面的话返回包含的版面
if ($arr['FLAG'] & BBS_BOARD_GROUP) {
   napi_print(array('boards' => expand_boards(
      constant('BBS_SECCODE' . $arr['SECNUM']), $bid)));
} else {
   // 0 全部帖子
   // 1 主题贴
   // 2 按最后回复排序的主题贴
   // 3 置顶帖 
   // 4 文摘区
   // 5 保留区
   // 6 精华区
   $mode = intval($napi_http['mode']);
   if ($mode < 0 || $mode > 5)
      throw new Exception('无效参数"mode"');

   $start = intval($napi_http['start']);
   $limit = intval($napi_http['limit']);

   if ($limit == 0)
      $limit = 10;
   if ($start < 0) 
      throw new Exception('无效参数"start"');
   if ($limit < 0)
      throw new Exception('无效参数"limit"');

   // get articles
   if ($mode != 2) {
      $ftypes = array(0, 6, 0, 11, 1, 3);
      $ftype = $ftypes[$mode];
      $total = bbs_countarticles($bid, $ftype);
      $articles = bbs_getarticles($board, $total - $start - $limit + 1, $limit, $ftype);
      $articles = array_reverse($articles);
   } else {
      // 强制生成.WEBTHREAD
      bbs_getthreadnum($bid);
      $articles = bbs_getthreads($board, $start, $limit, 0);
      foreach ($articles as &$article) {
         $lid   = $article['lastreply']['OWNER'];
         $ltime = $article['lastreply']['POSTTIME'];
         $flag  = $article['lastreply']['FLAGS'];
         $count = $article['articlenum'];

         $article            = $article['origin'];
         $article['FLAGS']   = $flag;
         $article['replies'] = $count;
         $article['lid']     = $lid;
         $article['ltime']   = $ltime;
      }
   }

   // 强制切割
   $articles = array_slice($articles, 0, $limit);

   // convert to $topics
   $keys = array();
   $topics = array();
   foreach ($articles as &$article) {
      $keys[] = 'count:' . $board . ':' . $article['ID']; 
      $append = array(
         'id'      => $article['ID'],
         'reid'    => $article['REID'],
         'board'   => $board,
         'size'    => $article['EFFSIZE'],
         'unread'  => in_array($article['FLAGS'][0], array('*', 'M', 'G', 'B')),
         'top'     => strtolower($article['FLAGS'][0]) == 'd',
         'mark'    => in_array(strtolower($article['FLAGS'][0]), array('m', 'g', 'b')),
         'author'  => $article['OWNER'],
         'time'    => $article['POSTTIME'],
         'title'   => napi_text_utf8($article['TITLE'])
      );

      // 论坛模式特有信息
      if (!empty($article['replies'])) {
         $append['replies']     = $article['replies'] - 1;
         $append['last_author'] = $article['lid'];
         $append['last_time']   = $article['ltime'];
      }

      $topics[] = $append;
   }

   // read counts
   $redis = new Predis_client();
   $counts = $redis->mget($keys);
   foreach ($topics as $i => &$topic)
      $topic['read'] = intval($counts[$i]);

   napi_print(array('topics' => $topics, 'total' => $arr['TOTAL']));
}

function expand_boards($prefix, $bid = 0) {
   global $napi_http;

   $result = array();
   $boards = bbs_getboards($prefix, $bid, 11);
   if (!$boards)
      return $result;

   foreach ($boards as $board) {
      $append = array(
         'name'        => $board['NAME'],
         'description' => napi_text_utf8($board['DESC']),
         'count'       => $board['ARTCNT'],
         'users'       => $board['CURRENTUSERS'],
         'bm'          => $board['BM'] ? explode(' ', $board['BM']) : array(),
         'leaf'        => true
      );

      if ($board['FLAG'] & BBS_BOARD_GROUP) {
         $append['leaf'] = false;
         $append['boards'] = get_boards($prefix, $board['BID']);

         if (!empty($napi_http['up']))
            array_unshift($append['boards'], array(
               'name' => '..',
               'description' => '[上级目录]',
               'leaf' => false,
               'unread' => false
            ));
      }

      $result[] = $append;
   }

   return $result;
}
?>
