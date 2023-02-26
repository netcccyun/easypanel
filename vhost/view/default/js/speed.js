function speed_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=speed&a=speedCheckOn',data);
}
function speed_add()
{
	var mode = $("#mode").val();
	var limit = $("#limit").val();
	if (limit == "") {
		return alert("带宽值不能为空");
	}
	var path = $("#path").val();
	if (path == document.getElementById("path").defaultValue) {
		path = "";
	}
	if (mode == 'gspeed_limit' && path == "") {
		return alert("该模式目录不能为空!");
	}
	//var min_size = $("#min_size").val();
	var data = 'mode=' + mode + '&limit=' + limit;
	if (path != "") {
		data += '&path=' + path;
	}
//	if (min_size != "") {
//		data += '&min_size=' + min_size;
//	}
	var url = '?c=speed&a=speedAdd';
	$.ajax({
		   type: "POST",
		   url: url,
		   data: data ,
		   success: function(msg){
			   if (msg != "成功"){
			     alert(msg);
			   }
		   		window.location.reload();
		   },
		  complete: function(msg){
			show_sync();
		}
	});
}
function close_msg()
{
	document.getElementById('msg').style.display='none';
}
function close_piao()
{
	$.cookie('not_piao',1);
	close_msg();
}
function piao_msg(mode)
{
	if ($.cookie('not_piao') == 1) {
		return;
	}
	var ddlog = art.dialog({id:'id22',content:msg,icon: 'face-smile',left:'50%',top:'20%'});
	switch (mode) {
		case 'speed_limit':
			//document.getElementById('size').style.display = '';
			ddlog.content("该模式可以将某个目录的<b id='red'>每次请求</b>进行带宽限制<br>");
			break;
		case 'ip_speed_limit':
			//document.getElementById('size').style.display='none';
			document.getElementById("path").value = document.getElementById("path").defaultValue;
			ddlog.content("该模式可以将<b id='red'>每个IP</b>的连接带宽进行限制<br>");
			break;
		case 'gspeed_limit':
			//document.getElementById('size').style.display='none';
			document.getElementById("path").value='';
			ddlog.content("该模式可以将某个目录的<b id='red'>所有请求</b>分为一组，对这一组的总连接带宽进行限制<br>");
			break;
		default:
			ddlog.content("错误哦!!!!!!");
			break;
	}
	$("#red").addClass('red');
}
function speed_del(id)
{
	if (confirm("确认要删除?") !== true) {
		return;
	}
	$.ajax({
		type:'POST',
		url: '?c=speed&a=speedDel',
		data:'id=' + id,
		success:function(msg) {
			if (msg != "成功") {
				 return alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function check_onfocus_value(id)
{
	document.from.path.style.color="#000000"
	document.getElementById(id).value = "";
}
function check_onblur_value(id)
{
	if (document.getElementById(id).value == "" ) {
		document.from.path.style.color="#999999"
		document.getElementById(id).value = document.getElementById(id).defaultValue;
	}
}
$(document).ready(function(){
//	var min = document.getElementById("min_size");
//	min.onfocus = function(){
//		min.value = '';
//	}
//	min.onblur = function(){
//		if (min.value == "") {
//			min.value = min.defaultValue;
//		}
//	}

	var limit = document.getElementById("limit");
	limit.onblur = function(){
		if (isNaN(limit.value)) {//是字符串
			alert("输入错误，输入值必需是数字");
		}
		
	}
		
});