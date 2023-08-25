var step = 1;
var out_msg;
var out_img = "<img src='/style/busy.gif'>";
function view_sync()
{
	if (confirm("确定要同步吗") === false) {
		return ;
	}
	var dlog = art.dialog({id:'id22',content:'正在执行请等待...<br>', icon: 'face-smile',left:'50%',top:'20%'});
	$.ajax({
		type:'get',
		url :'?c=views&a=getViewVersion',
		dataType:'json',
		async:false,
		success:function(msg) {
			if (msg['code'] != 200) {
				$("#aui_content").append(msg['error']);
				return;
			}
			for (i=1; i<=3; i++) {
				show_msg(i);
				sync(i);
				if (out_msg != undefined) {
					$("#aui_content").append("<font color='red'>" + out_msg + "</font><br>");
					if (out_msg != "成功") {
						break ;
					}
				}
			}
		}
	});
}
function sync(step)
{
	$.ajax({
		type: 'get',
		url : '?c=views&a=viewsSync',
		data: 'step=' + step,
		async:false,
		success:function(msg){
			out_msg = msg;
		}
	});
}
function show_msg(step)
{
	switch(step) {
		case 1:
			$("#aui_content").append("正在更新线路...");
			break;
		case 2:
			$("#aui_content").append("正在重新初始化...");
			break;
		case 3:
			$("#aui_content").append("正在同步线路...");
			break;
		default:
			break;
	}
}
function close_piao(id)
{
	document.getElementById(id).style.display = 'none';
}
function piao_view_change(name,desc)
{
	$("#msg").html("");
	 var msg = "<form name='server' action='javascript:view_change(\"" + name + "\");' method='post'>";
	msg += "<div class='piao_div_1'>描述:</div><div class='piao_div_2'><input id='desc' name='desc' value=" + desc + " ></div>";
	//msg += "<div class='piao_div_1'>skey:</div><div class='piao_div_2'><input id='key' name='key' ></div>";
	msg += "<div class='piao_submit'><input type='submit'  value='提交'><input type='button' value='关闭' onclick=close_piao('msg')></div>";
	msg += "</form>";
	$("#msg").append(msg);
	document.getElementById("msg").style.display = 'block';
}
function view_change(name)
{
	var de = $("#desc").val();
	if (de == ""){
		return alert('输入不能为空');
	}
	if (de == document.getElementById("desc").defaultValue){
		close_piao('msg');
		return;
	}
	$.ajax({
			type: 'post',
			url : '?c=views&a=viewChange',
			data: 'desc=' + de + '&name=' + name,
			success:function(msg){
				if (msg != "成功") {
					return alert(msg);
				}
				window.location.reload();
			}
	});
}