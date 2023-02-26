function show_sync()
{
	art.dialog({id:'show_sync_msg',content:'正在执行中,请稍后...',icon:''});
	$("#script").html("<script language='javascript' src='?c=index&a=sync'></scr"+"ipt>");
	return;
}

function functoin_checkon(url,data)
{
	if (confirm("确认要执行操作?") !== true) {
		return;
	}
	$.ajax({
		type:'post',
		url: url,
		data: data,
		success:function(msg) {
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
function ajax_getdata(url,data)
{
	$.ajax({
		type:'post',
		url: url,
		data: data,
		success:function(msg) {
			return msg;
		}
	});
	
}
function close_piao(id)
{
	document.getElementById(id).style.display = 'none';
	document.getElementById(id).innerHTML = '';
}