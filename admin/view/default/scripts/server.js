var appendcount=0;
function piao_server_add()
{
	//$("#msg").html("");
	var dlog = art.dialog({id:'id22',content:'', icon: 'face-smile',left:'50%',top:'20%',background:'#FF6347'});
	dlog.content('');
	var msg = "<form name='server' action='javascript:server_add();' method='post'>";
	msg += "<div class='piao_div_1'>服务器名称:</div><div class='piao_div_2'><input id='server' name='server' ></div>";
	//msg += "<div class='piao_div_1'>服务器IP:</div><div class='piao_div_2'><input id='ip' name='ip' ></div>";
	msg += "<div class='piao_div_1'>NS:</div><div class='piao_div_2'><input id='ns' name='ns' ></div>";
	msg += "<div class='piao_submit piao_center'><input type='submit'  value='提交'></div>";
	msg += "</form>";
	
	$("#aui_content").append(msg);
	//document.getElementById("msg").style.display = 'block';
}
function server_add()
{
	var s = $("#server").val();
	var n = $("#ns").val();
	if (n == '') {
		return alert('名称，NS不能为空');
	}
	var dlog = art.dialog({id:'id22',content:'域名服务器', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
		type:'get',
		url:'?c=servers&a=serverAdd',
		data:'server=' + s + '&ns=' + n ,
		success:function(msg) {
			if (msg != '成功') {
				dlog.content(msg);
				return ;
			}
			dlog.content("添加成功,请解析"+ n.substr(0,n.length-1)+ "记录");
		}
	});
	setTimeout(function(){
		window.location = window.location;
	},2000);
}
function piao_server_update(server,ns)
{
	var dlog = art.dialog({id:'id22',content:'域名服务器', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
		type:'get',
		url : '?c=slave&a=slaveGet',
		data: 'server=' + server,
		dataType:'json',
		success:function(msg){
			var count = msg['count'];
		 	var msg = "<form name='server' action='javascript:server_update(\"" + server + "\",\"" + ns + "\" );' method='post'>";
		 	if (count <= 0) {
				msg += "<div class='piao_div_1'>服务器名称:</div><div class='piao_div_2'><input id='server' name='server' value=" + server + "></div>";
		 	}
			msg += "<div class='piao_div_1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;NS:</div><div class='piao_div_2'><input id='ns' name='ns' value=" + ns + "></div>";
			msg += "<div class='piao_submit piao_center'><input type='submit'  value='提交'></div>";
			msg += "</form>";
			$("#aui_content").append(msg);
			//document.getElementById("msg").style.display = 'block';
		}	
	});
}
function server_update(oldserver,oldns)
{
	var newserver;
	if (document.getElementById("server") != null && document.getElementById("server") != undefined) {
		newserver = $("#server").val();
	}
	var ns = $("#ns").val();
	if (oldns == ns) {
		close_piao('msg');
		return ;
	}
	var data = 'ns=' + ns + '&oldserver=' + oldserver;
	if (newserver != 'undefined' && newserver !=null) {
		if (newserver != oldserver) {
			data +=  '&newserver=' + newserver;
		}	
	}
	var dlog = art.dialog({id:'id22',content:'域名服务器', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
			type: 'post',
			url : '?c=servers&a=serverUpdate',
			data: data,
			success:function(msg){
				if (msg != "成功") {
					return dlog.content(msg);
				}
				window.location.reload();
		}
	});
}

function close_piao(id)
{
	document.getElementById(id).style.display = 'none';
}

function server_del(s)
{
	if (confirm("这将导致删除该服务器下的所有辅助服务器，确定要删除吗?") === false) {
		return;
	}
	var dlog = art.dialog({id:'id22',content:'域名服务器', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
		type:'get',
		url: '?c=servers&a=serverDel',
		data:'server=' + s,
		success:function(msg) {
			if (msg != "成功") {
				dlog.content(msg);
				return ;
			}
			window.location.reload();
		}
	});
}
var step = 1;
var s = new Array(7);
	s.push('1');
	s.push('2');
	s.push('3');
	s.push('4');
	s.push('5');
	s.push('6');
	s.push('7');
var outmsg;
var busy = "<img src='/style/busy.gif'>";

function server_init()
{
	if (confirm("确定要初始化吗") === false){
		return ;
	}
	$.ajax({
		type: 'get',
		url : '?c=bind&a=getInit',
		data: null,
		dataType:'json',
		success:function(msg) {
			if (msg['init'] == 1) {
				var c = confirm("检测系统已经初始化，再次初始化有可能导致不可预料的错误，是否需要重新初始化？");
				if (c === false) {
					return;
				}
			}
			var dlog = art.dialog({id:'id22',content:'正在执行请等待...<br>', icon: 'face-smile',left:'50%',top:'20%'});
			for (i=step;i<=7;i++) {
				show_msg(i);
				init(i);
				$("#aui_content").append(outmsg + "<br>");
				if (outmsg != "成功") {
					break;
				}
			}
		}
	});
}
function show_msg(setp)
{
	switch (setp) {
		case 1:
			$("#aui_content").append("正在生成线路安全码...");
			break;
		case 2:
			$("#aui_content").append("正在创建所需目录...");
			break;
		case 3:
			$("#aui_content").append("正在生成bind配置文件...");
			break;
		case 4:
			$("#aui_content").append("正在生成域名解析文件...");
			break;
		case 5:
			$("#aui_content").append("正在重启域名服务...");
			break;
		case 6:
			$("#aui_content").append("正在同步配置...");
			break;
		case 7:
			$("#aui_content").append("正在生成域名配置文件...");
			break;
		default :
			break;
	}
}
function init(step)
{
	step = step;
	$.ajax({
		type: 'get',
		url : '?c=bind&a=init',
		data: 'step=' + step,
		async: false,
		success:function(msg) {
			outmsg = msg;
		}
	});
}
/**
 * @user server_init
 */
function server_init2()
{
	if (confirm("确定要初始化吗") === false){
		return;
	}
	$.ajax({
		type: 'get',
		url : '?c=bind&a=getInit',
		data: null,
		success:function(msg) {
			if (msg == 1) {
				var c = confirm("检测系统已经初始化，再次初始化有可能导致不可预料的错误，是否需要重新初始化？");
				if (c === false) {
					return;
				}
			}
			var m = document.getElementById("msg");
			if (m instanceof Object) {
				m.innerHTML = "<font color='red'>正在执行请等待</font><img src='/style/ajax-loader.gif'>";
				m.style.background = '#fffff';
				m.style.display = 'block';
			}
			$.ajax({
				type:'get',
				url :'?c=bind&a=bindInit',
				data:null,
				success:function(msg) {
					$("#msg").html("");
					document.getElementById("msg").style.display = 'none';
					return alert(msg);
				}
			});
		}	

	});
}
function slave_del(s,sl)
{
	if (confirm("确定要删除?") === false) {
		return;
	}
	var dlog = art.dialog({id:'id22',content:'域名服务器', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
		type:'post',
		url: '?c=slave&a=slaveDel',
		data:'server=' + s + '&slave=' + sl,
		success:function(msg) {
			if (msg != "成功") {
				dlog.content(msg);
				return;
			}
			window.location.reload();
		}
	});
}
function piao_slave_add(server)
{
	//$("#msg").html("");
	var dlog = art.dialog({id:'id22',content:'', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	var msg = "<form name='slave' action='javascript:slave_add();' method='post'>";
		msg += "<div class='piao_div_1'>服务器名称:</div><div class='piao_div_2'><input id='server' name='server' value=" + server + "></div>";
		msg += "<div class='piao_div_1'>服务器IP:</div><div class='piao_div_2' ><input name='slave' id='slave'></div>";
		msg += "<div class='piao_div_1'>NS:</div><div class='piao_div_2'><input id='ns' name='ns'></div>";
		msg += "<div class='piao_div_1'>安全码:</div><div class='piao_div_2'><input id='skey' name='skey' ></div>";
		msg += "<div class='piao_submit piao_center'><input type='submit'  value='提交'></div>";
		msg += "</form>";
	$("#aui_content").append(msg);
	//document.getElementById("server").readOnly = true;
	//document.getElementById("msg").style.display = 'block';
}
var slaveupdate = 0;
function piao_slave_update(server,slave,ns,skey)
{
	//$("#msg").html("");
	var dlog = art.dialog({id:'id22',content:'', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	var msg = "<form name='slave' action='javascript:slave_update(\"" + server + "\",\"" + slave + "\");' method='post'>";
		msg += "<div class='piao_div_1'>服务器IP:</div><div class='piao_div_2' ><input name='slave' id='slave' value=" + slave + "> </div>";
		msg += "<div class='piao_div_1'>NS:</div><div class='piao_div_2'><input id='ns' name='ns' value=" + ns + "></div>";
		msg += "<div class='piao_div_1'>安全码:</div><div class='piao_div_2'><input id='skey' name='skey' value=" + skey + "></div>";
		msg += "<div class='piao_submit'><input type='submit'  value='提交'></div>";
		msg += "</form>";
	$("#aui_content").append(msg);
	//document.getElementById("server").readOnly = true;
	//document.getElementById("msg").style.display = 'block';
}

function slave_update(server,oldslave)
{
	var newslave = $("#slave").val();
	var ns = $("#ns").val();
	var skey = $("#skey").val();
	if (server == "" || oldslave == ""){
		return alert('程序错误');
	}
	if (newslave == "" || ns == "" || skey == "") {
		return alert("输入各项参数不能为空");
	}
	var data = 'server=' + server +'&slave=' + newslave + '&ns=' + ns + '&skey=' + skey + '&oldslave=' +oldslave;
	var url = '?c=slave&a=slaveUpdate';
	var dlog = art.dialog({id:'id22',content:'', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
		type: 'post',
		url : url,
		data: data,
		success:function(msg) {
			if (msg != '成功') {
				dlog.content(msg);
				return;
				//return alert(msg);
			}
			window.location.reload();
		}
	});
}
function slave_add()
{
	var	s= $("#server").val();
	var sk = $("#skey").val();
	var i = $("#slave").val();
	var n = $("#ns").val();
	if (s == "" || sk == "" || i == "" || n == "") {
		return alert("各项参数不能为空");
	}
	var url = '?c=slave&a=slaveAdd';
	var data = 'server=' + s +'&slave=' + i + '&ns=' + n + '&skey=' + sk;
	var dlog = art.dialog({id:'id22',content:'', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$.ajax({
		type: 'post',
		url : url,
		data: data,
		success:function(msg) {
			if (msg != '成功') {
				dlog.content(msg);
				return;
			}
			//alert("添加成功,请解析" + n.substr(0,n.length-1) + "记录");
		}
	});
	setTimeout(function(){
		window.location = window.location;
	},2000);
}