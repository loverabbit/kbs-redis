<?php
require("www2-funcs.php");
require("www2-board.php");
login_init();
bbs_session_modify_user_mode(BBS_MODE_READING);
if (isset($_GET["board"]))
	$board = $_GET["board"];
else
	html_error_quit("错误的讨论区");

$brdarr = array();
$bid = bbs_getboard($board, $brdarr);
if ($bid == 0)
	html_error_quit("错误的讨论区");
$usernum = $currentuser["index"];
if (!bbs_is_bm($bid, $usernum))
	html_error_quit("你不是版主");
$board = $brdarr['NAME'];
$brd_encode = urlencode($board);

bbs_board_nav_header($brdarr, "自定版面封禁理由");

if (isset($_GET['act'])) {
	switch ($_GET['act']) {
		case 'set':
			if (!isset($_POST['setreason']))
				html_error_quit("错误的参数");
			$setreason = ($_POST['setreason']);
			switch (bbs_setdenyreason($board, $setreason)) {
				case -1:
					html_error_quit("讨论区错误");
					break;
				default:
					html_success_quit("自定义版面封禁理由保存成功<br/><br/>",
						array("<a href=bbsdoc.php?board=".$board.">返回版面</a>",
							"<a href=\"javascript:history.go(-1)\">继续修改</a>"));
			}
			break;
		default:
	}
}

$denyreasons = array();
$maxreason = bbs_getdenyreason($board, $denyreasons, 0);
?>
<script type="text/javascript">
function remove(r) {
	var table = document.getElementById("tableId");
	var tbody = table.tBodies[0];
	var len = tbody.rows.length;
	var item = r.parentNode.parentNode;
	var found = 0;
	if (!confirm("确定删除？"))
		return;
	for (var i=1;i<len-1;i++) {
		if (found==0 && item==tbody.rows[i])
			found = 1;
		if (found==1) {
			tbody.rows[i].getElementsByTagName("span")[0].innerHTML = tbody.rows[i+1].getElementsByTagName("span")[0].innerHTML;
			tbody.rows[i].getElementsByTagName("span")[1].innerHTML = tbody.rows[i+1].getElementsByTagName("span")[1].innerHTML;
		}
	}
	tbody.removeChild(tbody.rows[len-1]);
}

function move(r, dir) {
	var table = document.getElementById("tableId");
	var tbody = table.tBodies[0];
	var len = tbody.rows.length;
	var item = r.parentNode.parentNode;
	for (var i=1;i<len;i++) {
		if (item==tbody.rows[i]) {
			break;
		}
	}
	var next;
	if (dir=='up')
		next = i-1;
	else if (dir=='down')
		next = i+1;
	else {		
		alert("参数错误");
		return;
	}
	if (next<=1) {
		alert("到头了");
		return;
	}
	if (next>=len) {
		alert("到底了");
		return;
	}
	var tmp = tbody.rows[i].getElementsByTagName("span")[0].innerHTML;
	tbody.rows[i].getElementsByTagName("span")[0].innerHTML = tbody.rows[next].getElementsByTagName("span")[0].innerHTML;
	tbody.rows[next].getElementsByTagName("span")[0].innerHTML = tmp;
	tmp = tbody.rows[i].getElementsByTagName("span")[1].innerHTML;
	tbody.rows[i].getElementsByTagName("span")[1].innerHTML = tbody.rows[next].getElementsByTagName("span")[1].innerHTML;
	tbody.rows[next].getElementsByTagName("span")[1].innerHTML = tmp;
}

function edit(r) {
	var item = r.parentNode.parentNode;
	if (item.getElementsByTagName('input').length) {
		item.getElementsByTagName('span')[0].innerHTML = item.getElementsByTagName('span')[1].innerHTML;
		return;
	}
	var oldValue = item.getElementsByTagName('span')[0].innerHTML;
	var str = '';
	str += '<input type="text" maxLength=13 size=40 value="' + oldValue + '">';
	str += '<input type="button" value="确定" onclick="edit_do(this, 1);">';
	str += '<input type="button" value="取消" onclick="edit_do(this, 0);">';
	item.getElementsByTagName('span')[0].innerHTML = str;
}

function edit_do(r, t) {
	var item = r.parentNode.parentNode;
	var table = document.getElementById("tableId");
	var tbody = table.tBodies[0];
	var len = tbody.rows.length;
	
	if (t) {
		var str = item.getElementsByTagName('input')[0].value;
		if (str.length<=0) {
			alert("太短了");
			return;
		}
		for (var i=1;i<len;i++) {
			if (str==tbody.rows[i].getElementsByTagName('span')[1].innerHTML
				&& item.parentNode.getElementsByTagName('td')[0].innerHTML!=tbody.rows[i].getElementsByTagName("td")[0].innerHTML) {
				alert("重复了");
				return;
			}
		}	  
	} else
		var str = item.getElementsByTagName('span')[1].innerHTML;
	item.getElementsByTagName('span')[0].innerHTML = str;
	item.getElementsByTagName('span')[1].innerHTML = str;
}

function add() {
	var table = document.getElementById("tableId");
	var tbody = table.tBodies[0];
	var len = tbody.rows.length;
	var newValue = document.getElementById("newValue").value;
	if (newValue.length<=0) {
		alert("太短了");
		return;
	}
	if (len><?php echo BBS_BOARD_MAXCUSTOMREASON; ?>+1) {
		alert("已经达到最大数了！");
		return;
	}
	for (var i=1;i<len;i++) {
		if (newValue==tbody.rows[i].getElementsByTagName("span")[0].innerHTML) {
			alert("重复了");
			return;
		}
	}
	var newRow = tbody.rows[1].cloneNode(true);
	newRow.getElementsByTagName("td")[0].innerHTML = len-1;
	newRow.getElementsByTagName("span")[0].innerHTML = newValue;
	newRow.getElementsByTagName("span")[1].innerHTML = newValue;

	tbody.appendChild(newRow);
	tbody.rows[len].style.display = "";
	document.getElementById("newValue").value = "";
}

function confirm_set() {
	var table = document.getElementById("tableId");
	var tbody = table.tBodies[0];
	var len = tbody.rows.length;
	var str = "";

	for (var i=1;i<len;i++) {
		var curstr = tbody.rows[i].getElementsByTagName("span")[1].innerHTML;
		if (curstr == '')
			continue;
		str += curstr + "\n";
	}
	document.getElementById("setreason").value = str;
}
</script>
<table class="main wide adj" id="tableId" width="400">
<caption>版面封禁理由</caption>
<col class="center" width="40"/><col width="*"/><col class="center" width="50"/><col class="center" width="50"/><col class="center" width="50"/><col class="center" width="50"/>
<tbody><tr><th>序号</th><th>描述</th><th>修改</th><th>上移</th><th>下移</th><th>删除</th></tr>
<tr style="height:30;display:none;"><td></td>
	<td><span></span>
	<span style="display:none;"></span></td>
	<td><img onclick="edit(this)" style="cursor:pointer;" src="images/edit.png" alt="修改" height="16"/></td>
	<td><img onclick="move(this, 'up')" style="cursor:pointer;" src="images/up.png" alt="上移" height="16"/></td>
	<td><img onclick="move(this, 'down')" style="cursor:pointer;" src="images/down.png" alt="下移" height="16"/></td>
	<td><img onclick="remove(this)" style="cursor:pointer;" src="images/del.png" alt="删除" height="16"/></td>
</tr>
<?php
	$i = 1;
	foreach ($denyreasons as $reason) {
		echo '<tr style="height:30;display:true;"><td>'.$i.'</td>'.
			 '<td><span>'.htmlspecialchars($reason['desc']).'</span>'.
			 '<span style="display:none;">'.htmlspecialchars($reason['desc']).'</span></td>'.
			 '<td><img onclick="edit(this)" style="cursor:pointer;" src="images/edit.png" alt="修改" height="16"/></td>'.
			 '<td><img onclick="move(this, \'up\')" style="cursor:pointer;" src="images/up.png" alt="上移" height="16"/></td>'.
			 '<td><img onclick="move(this, \'down\')" style="cursor:pointer;" src="images/down.png" alt="下移" height="16"/></td>'.
			 '<td><img onclick="remove(this)" style="cursor:pointer;" src="images/del.png" alt="删除" height="16"/></td>'.
			 '</tr>';
		$i ++ ;
	}
?>
</tbody></table>
	<br/>
	<legend>添加封禁理由</legend>
		<div class="inputs">
			<label>描述: </label><input type="text" id="newValue" size="40" maxlength="13" /><input type="button" value="添加" onclick="add()"><br/>
		</div>
	<br/>
<form action="<?php $_SERVER['PHP_SELF']; ?>?act=set&board=<?php echo $brd_encode; ?>" method="post" class="medium">
	<input type="hidden" name="setreason" id="setreason" value="" />
	<div align="center"><input type="button" value="取消修改" onclick="location.reload();"><input type="submit" value="确定修改" onclick="confirm_set();"></div>
	<br/>
</form>
<?php
page_footer(FALSE);
?>
