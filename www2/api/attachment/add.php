<?php
// mannual set cookie which is passed by uploadify
if (!empty($_REQUEST['cookie'])) {
   $kaker = explode(';', $_REQUEST['cookie']);
   foreach($kaker as $val){
      $k = explode('=', $val);
      $_COOKIE[trim($k[0])] = $k[1];
   }
}

require_once dirname(__FILE__) . '/../lib/napi.php';
require_once dirname(__FILE__) . '/../lib/napi-call.php';

napi_guard_login();

global $napi_http;

//for api stat.
$akey = intval($napi_http['akey']);
napi_count($akey,'attachadd');

if (empty($_FILES['file']))
   throw new Exception('无上传文件');

if (!empty($napi_http['board'])) {
   napi_guard_parameters(array('id', 'board'));
   $board = preg_replace('/[^_a-zA-Z0-9\s]/', '', $napi_http['board']);
   $id    = intval($napi_http['id']);
}

// check error
switch ($_FILES['file']['error']) {
case UPLOAD_ERR_OK:
   break;
case UPLOAD_ERR_INI_SIZE:
case UPLOAD_ERR_FORM_SIZE:
   throw new Exception('文件大小超过上限');
case UPLOAD_ERR_PARTIAL:
   throw new Exception('传输过程中出错');
case UPLOAD_ERR_NO_FILE:
   throw new Exception('没有文件');
default:
   throw new Exception('未知错误');
}

$tmppath    = $_FILES['file']['tmp_name'];
$filename   = napi_text_gbk($_FILES['file']['name']);
$filesize   = $_FILES['file']['size'];
$minetype   = $_FILES['file']['type'];
$nocompress = !empty($napi_http['nocompress']);
if (!file_exists($tmppath) || !is_uploaded_file($tmppath))
   throw new Exception('传输过程中出错');

// compress images
if (!$nocompress)
   if ((is_image($filename) || substr($minetype, 0, 6) == 'image/')
       && $filesize > 819200) {
      $filename = compress_image($tmppath, $filename, $filesize);
   }

// put
if (!empty($napi_http['board']))
   $ret = bbs_attachment_add($board, $id, $tmppath, $filename);
else
   $ret = bbs_upload_add_file($tmppath, $filename);

if (is_int($ret) && $ret != 0)
   throw new Exception(bbs_error_get_desc($ret));

if ($board)
   $attachments = napi_call('attachment/list', array('board' => $board,
													 'id' => $id));
else
   $attachments = napi_call('attachment/list');

napi_print($attachments);

function compress_image($srcFile, $filename, $size) {
    // Temporarily increase the memory limit to allow for larger images
    ini_set('memory_limit', '128M');

    list($width, $height, $type) = getimagesize($srcFile);
    switch ($type) {
        case IMAGETYPE_BMP:
            $image = imagecreatefrombmp($srcFile);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($srcFile);
            break;
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($srcFile);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($srcFile);
            break;
        default:
            throw new Exception('Unrecognized image type ' . $type);
    }
    if ($width > 2000)
       $image = resize_image($image, $width, $height);

    imagejpeg($image, $srcFile, 90);
    return substr($filename, 0, strrpos($filename, '.')) . '.jpg';
}

function imagecreatefrombmp($p_sFile) 
{ 
    $file = fopen($p_sFile,"rb");
    $read = fread($file,10);
    while(!feof($file)&&($read<>"")) 
        $read .= fread($file,1024);
    
    $temp   = unpack("H*",$read);
    $hex    = $temp[1];
    $header = substr($hex,0,108);
    
    if (substr($header,0,4)=="424d") 
    { 
        $header_parts = str_split($header,2);
        $width        = hexdec($header_parts[19].$header_parts[18]);
        $height       = hexdec($header_parts[23].$header_parts[22]);
        unset($header_parts); 
    } 
    
    $x = 0;
    $y = 1;
    $image       = imagecreatetruecolor($width,$height);
    $body        = substr($hex,108);
    $body_size   = (strlen($body)/2);
    $header_size = ($width*$height);
    $usePadding  = ($body_size>($header_size*3)+4);
    
    for ($i=0;$i<$body_size;$i+=3) 
    { 
        if ($x>=$width) 
        { 
            if ($usePadding) 
                $i += $width%4; 
            $x    =    0; 
            $y++; 
            if ($y>$height) 
                break; 
        } 
        $i_pos = $i*2;
        $r     = hexdec($body[$i_pos+4].$body[$i_pos+5]);
        $g     = hexdec($body[$i_pos+2].$body[$i_pos+3]);
        $b     = hexdec($body[$i_pos].$body[$i_pos+1]);
        
        $color    =    imagecolorallocate($image,$r,$g,$b); 
        imagesetpixel($image,$x,$height-$y,$color); 

        $x++; 
    } 
    
    unset($body); 
    return $image; 
}

function resize_image(&$image, $width_orig, $height_orig, $max_size = 1280)
{
    $ratio_orig = $width_orig / $height_orig;
    $width = $height = $max_size;

    // resize to height (orig is portrait) 
    if ($ratio_orig < 1) {
        $width = $height * $ratio_orig;
    } 
    // resize to width (orig is landscape)
    else {
        $height = $width / $ratio_orig;
    }

    $newImage = imagecreatetruecolor($width, $height);
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
    imagedestroy($image);
    return $newImage;
}

function is_image($name)
{
    $allowed = array('.jpg', '.jpeg', '.bmp', '.png', '.gif');
    return in_array(strtolower(strrchr($name, '.')), $allowed);
}

?>
