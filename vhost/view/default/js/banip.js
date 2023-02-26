function banip_add()
{
	var ip = $("#ip").val();
	var life_time = $("#life_time").val();
	if (ip == ""){
		return alert("IP不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=banip&a=addBanip',
		data:'ip=' + ip + '&life_time= ' + life_time,
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
		data:'url=' + encodeURIComponent(url) + '&meth= ' + meth,
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
