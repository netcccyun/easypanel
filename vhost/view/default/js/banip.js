function banip_add()
{
	var ip = $("#ip").val();
	if (ip == ""){
		return alert("IP不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=addBanip',
		data:'ip=' + encodeURIComponent(ip),
		success:function(msg) {
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
function banurl_add()
{
	var url = $("#url").val();
	var meth = $("#meth").val();
	if (url == ""){
		return alert("URL不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=addBanurl',
		data:'url=' + encodeURIComponent(url) + '&meth=' + encodeURIComponent(meth),
		success:function(msg) {
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
function banip_del(id)
{
	if (confirm("确定要删除?") == false) {
		return;
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=delBanip',
		data:'id=' + id,
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function banurl_del(id)
{
	if (confirm("确定要删除?") == false) {
		return;
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=delBanurl',
		data:'id=' + id,
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function banip_del_all()
{
	if (confirm("确定要清空全部IP?") == false) {
		return;
	}
	$.ajax({
		type:'get',
		url:'?c=banip&a=delBanipAll',
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function banurl_del_all()
{
	if (confirm("确定要清空全部URL?") == false) {
		return;
	}
	$.ajax({
		type:'get',
		url:'?c=banip&a=delBanurlAll',
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function banip_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=banip&a=switchIp',data);
}
function banurl_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=banip&a=switchUrl',data);
}
function whitelist_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=banip&a=switchWhitelist',data);
}
function whitelisturl_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=banip&a=switchWhitelistUrl',data);
}
function realip_select()
{
	if ($("#realip_common").val() == "custom") {
		$("#realip_custom").show();
	} else {
		$("#realip_custom").hide();
	}
}
function realip_save()
{
	var v = $("#realip_common").val();
	if (v == "custom") {
		v = $("#realip_custom").val();
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=saveRealip',
		data:'header=' + encodeURIComponent(v),
		success:function(msg) {
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
function whitelist_add()
{
	var ip = $("#allow_ip").val();
	if (ip == ""){
		return alert("IP不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=addWhitelist',
		data:'ip=' + encodeURIComponent(ip),
		success:function(msg) {
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
function whitelist_del(id)
{
	if (confirm("确定要删除?") == false) {
		return;
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=delWhitelist',
		data:'id=' + id,
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function whitelist_del_all()
{
	if (confirm("确定要清空全部白名单IP?") == false) {
		return;
	}
	$.ajax({
		type:'get',
		url:'?c=banip&a=delWhitelistAll',
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function whitelisturl_add()
{
	var url = $("#allow_url").val();
	var meth = $("#allow_meth").val();
	if (url == ""){
		return alert("URL不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=addWhitelistUrl',
		data:'url=' + encodeURIComponent(url) + '&meth=' + encodeURIComponent(meth),
		success:function(msg) {
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
function whitelisturl_del(id)
{
	if (confirm("确定要删除?") == false) {
		return;
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=delWhitelistUrl',
		data:'id=' + id,
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
function whitelisturl_del_all()
{
	if (confirm("确定要清空全部白名单URL?") == false) {
		return;
	}
	$.ajax({
		type:'get',
		url:'?c=banip&a=delWhitelistUrlAll',
		success:function(msg){
			if (msg != "成功") {
				alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}
