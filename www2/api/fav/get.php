<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();

function expand_boards($prefix, $bid = 0) {
   global $napi_http;

   $result = array();
   $boards = bbs_getboards($prefix, $bid, 9);
   if (!$boards)
      return $result;

   foreach ($boards as $board) {
      $append = array(
         'name'        => $board['NAME'],
         'unread'      => !!$board['UNREAD'],
         'description' => napi_text_utf8($board['DESC']),
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

function get_fav_boards($select) {
   global $napi_http;
   $result = array();

   //for api stat.
   $akey = intval($napi_http['akey']);
   napi_count($akey,'favget');

   if (bbs_load_favboard($select) == -1) return array();
   $boards = bbs_fav_boards($select, 1);

   $brd_name = $boards["NAME"]; // 英文名
   $brd_desc = $boards["DESC"]; // 中文描述
   $brd_flag = $boards["FLAG"]; 
   $brd_bid  = $boards["BID"];  // 版ID 或者 fav dir 的索引值 
   $brd_unread= $boards["UNREAD"];
   $rows = count($brd_name);

   for ($i = 0; $i < $rows; $i++) {
      if ($brd_bid[$i] == -1) continue;

      // 用户自建目录
      if ($brd_flag[$i] == -1) {
         $append = array(
            'name' => napi_text_utf8($brd_desc[$i]),
            'description' => '[目录]',
            'leaf' => false,
            'unread' => !!$brd_unread[$i],
            'boards' => get_fav_boards($brd_bid[$i])
         );
         if (!empty($napi_http['up']))
            array_unshift($append['boards'], array(
               'name' => '..',
               'description' => '[上级目录]',
               'leaf' => false,
               'unread' => false
            ));
         $result[] = $append;
         continue;
      }

      // 判断是否为目录版面，是的话展开
      if ($brd_flag[$i] & BBS_BOARD_GROUP) {
	     $arr = array();
	     bbs_getboard($brd_name[$i], $arr);
         $append = array(
            'name' => $brd_name[$i],
            'description' => '[目录]',
            'leaf' => false,
            'unread' => !!$brd_unread[$i],
            'boards' => expand_boards(
               constant('BBS_SECCODE' . $arr['SECNUM']), $brd_bid[$i])
         );
         if (!empty($napi_http['up']))
            array_unshift($append['boards'], array(
               'name' => '..',
               'description' => '[上级目录]',
               'leaf' => false,
               'unread' => false
            ));
         $result[] = $append;
         continue;
      }

      $result[] = array(
         'name' => $brd_name[$i],
         'description' => napi_text_utf8($brd_desc[$i]),
         'leaf' => true,
         'unread' => !!$brd_unread[$i]
      );
   }

   return $result;
}

napi_print(array('boards' => get_fav_boards(0)));
?>
