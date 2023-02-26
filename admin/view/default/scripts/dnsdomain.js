var ddlog = null;
function domain_del(domain)
{
	if (confirm("确定要删除域名,这将删除该域名所有的解析?") === false) {
		return;
	}
	if (ddlog) {
		ddlog = null;
	}
	var dlog = art.dialog({id:domain,content:'', icon: 'face-smile',left:'50%',top:'20%'});
	ddlog = dlog;
	dlog.content('');
	var isclose = false;
	$.ajax({
		type:'get',
		url: '?c=dnsdomain&a=domainDel',
		data:'domain=' + domain,
		dataType:'json',
		success:function(ret) {
			if (ret['code'] != 200) {
				dlog.content(ret['msg'] ? ret['msg'] : '删除' + domain + '失败');
			}else {
				isclose = true;
			}
			dlog.content("删除" + domain + '成功');
			if (isclose) {
				setTimeout(function(){
					window.location = window.location;
				},2000);
			}
		}
	});
}
function getRandStr(len)
{
	var str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	var str_len = str.length;
	var sr = '';
	for (var i=0;i<len;i++) {
		var s = Math.floor(Math.random()*(str_len-1)+1);
		sr += str[s];
	}
	return sr;
}
function domain_init(domain)
{
	if (confirm("确定要重建该域名的解析?") === false) {
		return;
	}
	var dlog = art.dialog({content:'', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	var closetime = 2000;
	$.ajax({
		type:'post',
		url: '?c=dnsdomain&a=domainInit',
		data:'domain=' + domain,
		dataType:'json',
		success:function(ret) {
			if (ret['code'] != 200) {
				dlog.content(ret['msg'] ? ret['msg'] : '重建' + domain + '失败');
				closetime = 4000;
			}
			setTimeout(function(){
				window.location = window.location;
			},closetime);
		}
	});
}
function close_piao(id)
{
	document.getElementById(id).style.display = 'none';
}
function piao_add()
{
	//$("#msg").html("");
	var dlog = art.dialog({id:'id22',content:'', icon: 'face-smile',left:'50%',top:'20%'});
	dlog.show = false;
	dlog.content('');
	var msg = "<form name='domain' action='javascript:domain_add();' method='post'>";
	msg += "<div class='piao_div_22'>主  域名:<input name='domain' id='domain' > </div>";
	msg += "<div class='piao_div_22'>域名密码:<input id='passwd' name='passwd'></div>";
	msg += "</div><div class='piao_div_22'>解析条数:<input id='max_record' name='max_record' ></div>";
	msg += "</div><div class='piao_div_22'>随机  值:<input id='salt' name='salt'></div>";
	msg += "</div><div class='piao_div_22'>解析服务器:<span  id='select_server'></span></div>";
	msg += "<div class='piao_submit2'><input type='submit' value='提交'></div>";
	msg += "</form>";
	dlog.content(msg);
	$.ajax({
		type: 'post',
		url : '?c=servers&a=getServers',
		dataType:'json',
		data: null,
		success:function(msg) {
			var count = msg['count'];
			var servers = msg['servers'];
			var str = "<select name='server' id='server'>";
			if (count <= 0) {
				dlog.content("错误:请先行添加域名服务器");
				return ;
			}
			for (var i=0;i<count;i++) {
				str += "<option value=" + servers[i]['server'] + ">" + servers[i]['server'] + "</option>";
			}
			str += "</select>";
			$("#select_server").html(str);
			document.getElementById("salt").value =  getRandStr(16);
			//document.getElementById("msg").style.display = 'block';
		}
	});
}
function domain_add()
{
	var domain = $("#domain").val();
	var passwd = $("#passwd").val();
	var max_record = $("#max_record").val();
	var salt = null;
	var server = null;
	if(document.getElementById('salt') != null) {
		salt = $("#salt").val();
	}
	if(document.getElementById('server') != null) {
		server = $("#server").val();
	}
	if (domain == "" || passwd == "") {
		return alert('输入错误');
	}
	var data = 'domain=' + domain + '&passwd=' + passwd + '&max_record=' + max_record;
	if (salt != null && salt != undefined) {
		data += '&salt=' + salt;
	}
	if (server != null && server != undefined) {
		data += '&server=' + server;
	}
	var dlog = art.dialog({id:'id22', icon: 'face-smile',left:'50%',top:'20%'});
	//dlog.content('');
	$.ajax({
		type:'get',
		url: '?c=dnsdomain&a=domainAdd',
		data:data,
		dataType:'json',
		success:function(ret) {
			if (ret['code'] != 200) {
				dlog.content(ret['msg'] ? ret['msg'] : '增加' + domain + '失败');
				return ;
			}
			dlog.content('增加' + domain + '成功');
			setTimeout(function(){
				window.location = window.location;
			},2000);
		}
	});
	
	
}