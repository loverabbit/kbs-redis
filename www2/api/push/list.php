<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_login();

global $napi_user;
$redis = new Predis_client();

$ret = array();
$tokens = array();

$keys = $redis->keys('itoken*');

foreach($keys as $key){
    $u = get_user($key);
    $ret = array();
    foreach ($redis->smembers($key) as $v)
        $ret[] = $v;
    $tokens['token'][] = array(
            'name' => $u,
            'tokens' => $ret
         );
}

napi_print(array('tokens' => $tokens));

function get_user($key) {
    list($itoken, $user) = explode(':', $key);
    return $user;
}

?>
