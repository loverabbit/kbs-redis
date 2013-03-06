<?php
	require("www2-funcs.php");
	require("www2-bmp.php");
	$sessionid = login_init(TRUE);
	assert_login();

	$msg=array();
	$id=intval(@$_GET['id']);
	if ($id<=0)
		exit();
	if (bbs_new_msg_get_message($id, &$msg)<=0)
		exit();

	$output=bbs_new_msg_get_attachment($id);
	if ($output===false)
		exit();

	/*
	header('Pragma: public');
	header('Cache-Control: max-age=86400');
	header('Expires: '.gmdate('D, d M Y H:i:s', time()+86400)." GMT");
	header('Last-Modified: '.gmdate('D, d M Y H:i:s', $msg['TIME'])." GMT");
	header('Content-Transfer-Encoding: binary');
	header('Content-Encoding: none');
	header('Content-Type:'.$msg['ATTACHMENT_TYPE']);
	header('Content-Length:'.$msg['ATTACHMENT_SIZE']);
	*/
	header('Content-Type: '.$msg['ATTACHMENT_TYPE']);
	header('Accept-Ranges: bytes');
	header('Content-Length: '.$msg['ATTACHMENT_SIZE']);
	
	if ($msg['FLAG']&0x02)
		header('Content-Disposition: inline;filename='. rawurlencode($msg['ATTACHMENT_NAME']) .'');
		//header('Content-Disposition: inline;filename="'. rawurlencode($msg['ATTACHMENT_NAME']) .'"');
	else
		header('Content-Disposition:attachment; filename="'. rawurlencode($msg['ATTACHMENT_NAME']) .'"');

	echo $output;
	$output=null;
	exit();
