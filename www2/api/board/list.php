<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

//for api stat.
global $napi_http;
$akey = intval($napi_http['akey']);
napi_count($akey,'boardlist');

napi_try_login();

$boards = array();
if ($boardsArray = bbs_getboards('*', 0, 6)) {
    $boards_count = count($boardsArray['NAME']);
    for ($i = 0; $i < $boards_count - 1; $i++) {
        if (($boardsArray['FLAG'][$i] & 0x4) ||
            ($boardsArray['FLAG'][$i] & BBS_BOARD_GROUP))
            continue;
        $board = $boardsArray['NAME'][$i];
        $descp = iconv('GB2312', 'UTF-8', $boardsArray['DESC'][$i]);
        $boards[] = $no_desc ? $board : "$board - $descp";
    }
    $board = $boardsArray['NAME'][$i];
    $descp = iconv('GB2312', 'UTF-8', $boardsArray['DESC'][$i]);
    $boards[] = $no_desc ? $board : "$board - $descp";
}

napi_print(array('boards' => $boards));
?>
