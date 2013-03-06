<?php
require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';
require_once dirname(__FILE__) . '/../lib/Predis.php';

napi_guard_parameters(array('board', 'id'));
napi_try_login();

global $napi_http;
$board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['board']);
$id    = intval($napi_http['id']);
$start = intval($napi_http['start']);
$limit = intval($napi_http['limit']);

if ($limit == 0)
   $limit = 10;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'topicget');

// get right board name
$arr = array();
if (is_null(bbs_safe_getboard(0, $board, $arr)))
   throw new Exception('无效版面');
$board = $arr['NAME'];
$bid = $arr['BID'];

// get articles
$articles = array();
$gid = bbs_get_article_gid($bid, $id);
$num = bbs_get_threads_from_gid($bid, $gid, $id, $articles, $haveprev);
if (!$num) {
   $articles_raw = array();
   $num = bbs_get_records_from_id($board, $id, 11, $articles_raw);
   if ($num <= 0)
      throw new Exception('无效文章');
   $articles[0] = $articles_raw[1];
}
if ($start < 0) 
   throw new Exception('参数"start"无效');
if ($limit < 0)
   throw new Exception('参数"limit"无效');

// strip articles
$keys = array();
$articles = array_slice($articles, $start, $limit);
if ($articles) {
   $tmpMap = array();
   foreach ($articles as &$article) {
      // read it 
      bbs_brcaddread($board, $article['ID']);

      // redis key
      $keys[] = 'count:' . $board . ':' . $article['ID']; 

      // map id to topic
      $tmpMap[$article['ID']] = $article;

      $append = array(
         'id'       => $article['ID'],
         'gid'      => $gid,
         'reid'     => $article['REID'],
         'board'    => $board,
         'size'     => $article['EFFSIZE'],
         'unread'   => $article['FLAGS'][0] == '*',
         'top'      => strtolower($article['FLAGS'][0]) == 'd',
         'mark'     => in_array(strtolower($article['FLAGS'][0]), array('m', 'g', 'b')),
         'author'   => $article['OWNER'],
         'norep'    => $article['FLAGS'][2] == 'y',
         'time'     => $article['POSTTIME'],
         'title'    => napi_text_utf8($article['TITLE']),
      );

      if (empty($napi_http['raw'])) {
         list($content, $quote) = filter(bbs_originfile($board, $article['FILENAME']));
         $append['content'] = trim(napi_text_utf8($content));

         // 引文
         if ($article['ID'] != $article['GROUPID']) {
            $append['quote'] = trim(napi_text_utf8($quote));

            // 引用者
            if (empty($tmpMap[$article['REID']]))
               $append['quoter'] = get_quoter($bid, $article['REID']);
            else
               $append['quoter'] = $tmpMap[$article['REID']]['OWNER'];
         }
      } else {
         $append['filename'] = $article['FILENAME'];
         $append['content']  = napi_text_utf8(bbs_originfile($board, $article['FILENAME']));
      }

      // 附件
      $attch = napi_call('attachment/list', array('board' => $board, 'id' => $append['id']));
      if (!empty($attch['attachments']))
         $append['attachments'] = $attch['attachments'];

      $topics[] = $append;
   }

   // read counts
   $redis = new Predis_client();
   $counts = $redis->mget($keys);
   foreach ($topics as $i => &$topic)
      $topic['read'] = intval($counts[$i]);
} else {
   $topics = array();
}

napi_print(array('topics' => $topics));

function filter($str)
{
   $ret = '';
   $quote = '';

   // Filter out signature
   $i = strrpos($str, "\n--\n");
   if ($i !== false) $str = substr($str, 0, $i);

   $skip = 0;
   $tok = strtok($str, "\n");
   while ($tok !== false) {
       if ($skip > 0) {
           --$skip;

           $tok = strtok("\n");
           continue;
       }

       // Filter out and store quotes
       if (($tok[0] == chr(161) && $tok[1] == chr(190)) ||
           $tok[0] == ':')
       {
           if ($tok[0] == ':') {
              $quote .= substr($tok, 2) . "\n";
           }

           $tok = strtok("\n");
           continue;
       }

       $ret .= $tok . "\n";

       $tok = strtok("\n");
   }

   // Force free memory
   strtok('', '');

   // constrain quote's length
   if (strlen($quote) > 100) {
      $quote = substr($quote, 0, 100) . '...';
   }

   return array(rtrim($ret), rtrim($quote));
}

function get_quoter($bid, $id) {
   $articles = array();
   $gid = bbs_get_article_gid($bid, $id);
   $num = bbs_get_threads_from_gid($bid, $gid, $id, $articles, $haveprev);
   if ($num > 0)
      return $articles[0]['OWNER'];
}
?>
