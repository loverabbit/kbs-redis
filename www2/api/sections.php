<?php
require_once dirname(__FILE__) . '/lib/napi.php';

napi_try_login();

function get_boards($prefix, $bid = 0) {
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

global $napi_http;
$result = array();
for ($i = 0; $i < BBS_SECNUM; $i++) {
   $append = array(
      'name' => napi_text_utf8(constant("BBS_SECNAME{$i}_0")),
      'description' => '[目录]',
      'boards' => get_boards(constant('BBS_SECCODE' . $i), 0)
   );

   if (!empty($napi_http['up']))
      array_unshift($append['boards'], array(
         'name' => '..',
         'description' => '[上级目录]',
         'leaf' => false,
         'unread' => false
      ));

   $result[] = $append;
}

napi_print(array('boards' => $result));
?>
