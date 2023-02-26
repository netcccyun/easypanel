function antiupload_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=antiupload&a=antiuploadStart',data);
}
function antiupload_empty()
{
	var ret = confirm("确定要清除所有防上传的设置吗?");
	if (ret == false) {
		return;
	}
	var url = '?c=antiupload&a=antiuploadEmpty';
	$.ajax({
		type:'post',
		url: url,
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
function antiupload_add()
{
	var filename = $("#filename").val();
	if (filename == "") {
		return alert("文件后缀不能为空");
	}
	$.ajax({
		type:'post',
		url: '?c=antiupload&a=antiuploadAdd',
		data:'filename=' + filename,
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
function antiupload_del(id)
{
	if (confirm("确定要删除设置?") == false) {
		return;
	}
	$.ajax({
		type:'post',
		url: '?c=antiupload&a=antiuploadDel',
		data: 'id=' + id,
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