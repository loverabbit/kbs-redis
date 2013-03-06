<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_try_login();

global $napi_http;
if (empty($napi_http['name'])) {
   global $napi_user;
   $user = $napi_user;
} else {
   $user = preg_replace('/[^a-zA-Z]/', '', $napi_http['name']);
}

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'userget');

$arr = array();
$qur = array();
if (0 == bbs_getuser($user, $arr))
   throw new Exception('¸ÃÓÃ»§²»´æÔÚ');

bbs_get_query_info($user, $qur);

$result = array(
   'id'         => $arr['userid'],
   'name'       => napi_text_utf8($arr['username']),
   'avatar'     => 'http://bbs.seu.edu.cn/wForum/' . $arr['userface_url'],
   'lastlogin'  => $arr['lastlogin'],
   'level'      => napi_text_utf8(bbs_getuserlevel($arr['userid'])),
   'posts'      => $arr['numposts'],
   'perform'    => $qur['perf'],
   'experience' => $qur['exp'],
   'medals'     => $arr['nummedals'],
   'logins'     => $arr['numlogins'],
   'life'       => bbs_compute_user_value($arr['userid'])
);

if ($arr['userdefine0'] & BBS_DEF_SHOWDETAILUSERDATA) {
   $result['gender'] = chr($arr['gender']);
   $result['astro']  = napi_text_utf8(get_astro($arr['birthmonth'], $arr['birthday']));
}

napi_print(array('user' => $result));

function get_astro($birthmonth, $birthday)
{
    if ($birthmonth == 0 || $birthday == 0)
        return 'Î´Öª';

    switch ($birthmonth) {
    case 1:
        if ($birthday >= 21)
           return 'Ë®Æ¿×ù';
        else
           return 'Ä§ôÉ×ù';
    case 2:
        if ($birthday >= 20)
           return 'Ë«Óã×ù';
        else
           return 'Ë®Æ¿×ù';
    case 3:
        if ($birthday >= 21)
           return '°×Ñò×ù';
        else
           return 'Ë«Óã×ù';
    case 4:
        if ($birthday >= 21)
           return '½ðÅ£×ù';
        else
           return '°×Ñò×ù';
    case 5:
        if ($birthday >= 22)
           return 'Ë«×Ó×ù';
        else
           return '½ðÅ£×ù';
    case 6:
        if ($birthday >= 22)
           return '¾ÞÐ·×ù';
        else
           return 'Ë«×Ó×ù';
    case 7:
        if ($birthday >= 23)
           return 'Ê¨×Ó×ù';
        else
           return '¾ÞÐ·×ù';
    case 8:
        if ($birthday >= 24)
           return '´¦Å®×ù';
        else
           return 'Ê¨×Ó×ù';
    case 9:
        if ($birthday >= 24)
           return 'ÌìèÒ×ù';
        else
           return '´¦Å®×ù';
    case 10:
        if ($birthday >= 24)
           return 'ÌìÐ«×ù';
        else
           return 'ÌìèÒ×ù';
    case 11:
        if ($birthday >= 23)
           return 'ÉäÊÖ×ù';
        else
           return 'ÌìÐ«×ù';
    case 12:
        if ($birthday >= 22)
           return 'Ä§ôÉ×ù';
        else
           return 'ÉäÊÖ×ù';
    default:
        return 'ÉñÂí×ù';
    }
}
?>
