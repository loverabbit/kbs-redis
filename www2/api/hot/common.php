<?php
function get_hots($key) {
   require_once dirname(__FILE__) . '/../lib/Predis.php';

   $redis = new Predis_client();
   $replies = $redis->pipeline(function($pipe) use($key) {
      $pipe->lrange('stat:' . $key . ':title', 0, -1);
      $pipe->lrange('stat:' . $key . ':author', 0, -1);
      $pipe->lrange('stat:' . $key . ':board', 0, -1);
      $pipe->lrange('stat:' . $key . ':time', 0, -1);
      $pipe->lrange('stat:' . $key . ':id', 0, -1);
      $pipe->lrange('stat:' . $key . ':replies', 0, -1);
   });

   $topics = array();
   for ($i = 0; $i < count($replies[0]); $i++) {
      $topics[] = array(
         'title'   => napi_text_utf8($replies[0][$i]),
         'author'  => $replies[1][$i],
         'board'   => $replies[2][$i],
         'time'    => intval($replies[3][$i]),
         'id'      => intval($replies[4][$i]),
         'replies' => intval($replies[5][$i]) - 1
      );
      $keys[] = 'count:' . $replies[2][$i] . ':' . $replies[4][$i];
   }

   $counts = $redis->mget($keys);
   foreach ($topics as $i => &$topic)
      $topic['read'] = intval($counts[$i]);

   return $topics;
}

function print_hots($key) {
   napi_print(array('topics' => get_hots($key)));
}
?>
