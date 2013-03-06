<?php
require_once dirname(__FILE__) . '/../lib/napi.php';

napi_guard_parameters(array('id'));
napi_guard_login();

global $napi_http;
$user = preg_replace('/[^a-zA-Z]/', '', $napi_http['id']);

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'friendsadd');

napi_print(array(
   'result' => bbs_add_friend($user, $napi_http['description'])
));
?>
