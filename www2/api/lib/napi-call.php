<?php
function napi_call($method, $parameters = array()) {
   global $napi_mode;
   global $napi_http;
   global $napi_relt;
   $origin_mode = $napi_mode;
   $origin_http = $napi_http;
   $napi_mode = 'php';
   $napi_http = $parameters;
   $napi_relt = array();

   try {
      require dirname(__FILE__) . '/../' . $method . '.php';
   } catch (Exception $e) {
      $napi_relt = array('error' => $e->getMessage());
   }

   $napi_mode = $origin_mode;
   $napi_http = $origin_http;
   return $napi_relt;
}
?>
