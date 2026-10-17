function banip_add()
{
	var ip = $("#ip").val();
	if (ip == ""){
		return alert("IP不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=' + (window.epListController || 'banip') + '&a=addBanip',
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
	var url = $.trim($("#url").val());
	var meth = $("#meth").val();
	if (url === "" && meth === ""){
		return alert("URL和请求方法不能同时为空");
	}
	$.ajax({
		type:'post',
		url:'?c=' + (window.epListController || 'banip') + '&a=addBanurl',
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
		url:'?c=' + (window.epListController || 'banip') + '&a=delBanip',
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
		url:'?c=' + (window.epListController || 'banip') + '&a=delBanurl',
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
		url:'?c=' + (window.epListController || 'banip') + '&a=delBanipAll',
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
		url:'?c=' + (window.epListController || 'banip') + '&a=delBanurlAll',
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
	functoin_checkon('?c=' + (window.epListController || 'banip') + '&a=switchIp',data);
}
function banurl_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=' + (window.epListController || 'banip') + '&a=switchUrl',data);
}
