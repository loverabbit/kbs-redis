<?php
require("www2-funcs.php");
require("www2-board.php");
login_init();
bbs_session_modify_user_mode(BBS_MODE_READING);
if (isset($_GET["board"]))
	$board = $_GET["board"];
else
	html_error_quit("错误的讨论区");
if (isset($_GET["user"]))
	$user = $_GET["user"];
else
	html_error_quit("错误的参数");

$brdarr = array();
$bid = bbs_getboard($board, $brdarr);
if ($bid == 0)
	html_error_quit("错误的讨论区");
$usernum = $currentuser["index"];
if (!bbs_is_bm($bid, $usernum))
	html_error_quit("你不是版主");
$board = $brdarr['NAME'];
$brd_encode = urlencode($board);

$denyreasons = array();
$maxreason = bbs_getdenyreason($board, $denyreasons, 1);
$maxdenydays = ($currentuser["userlevel"]&BBS_PERM_SYSOP)?70:14;

bbs_board_nav_header($brdarr, "修改用户封禁");

?>

<form action="bbsdeny.php?act=mod&board=<?php echo $brd_encode; ?>" method="post" class="medium">
	<fieldset><legend>修改用户封禁</legend>
		<div class="inputs">
			<label>用户名</label><input type="text" name="userid" size="12" maxlength="12" value="<?php echo $user; ?>" readonly/><br/>
			<label>封禁时间</label><select name="denyday">
<?php
	$i = 1;
	while ($i <= $maxdenydays) {
		echo '<option value="'.$i.'">'.$i.'</option>';
		$i += ($i >= 14)?7:1;
	}    
?>    
			</select>天<br/>
			<label>封禁原因</label><select name="exp">
<?php
	$i = 0;
	foreach ($denyreasons as $reason) {
		echo '<option value="'.$i.'">'.htmlspecialchars($reason['desc']).'</option>';
		$i ++;
	}
?>    
			</select><br />
			或手动输入封禁理由：
			<input type="text" name="exp2" size="20" maxlength="28" />
		</div>
		<div class="oper"><input type="submit" value="修改封禁" onclick="if(confirm('确定修改封禁?')){return true;}return false;"/>&nbsp;&nbsp;<input type="button" value="放弃修改" onclick="history.go(-1);"></div>
	</fieldset>
</form>
<?php
page_footer(FALSE);
