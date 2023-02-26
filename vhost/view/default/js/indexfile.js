function indexfile_add()
{
	var file = $("#file").val();
	if (file == "") {
		return alert("文件名不能为空");
	}
	var id = $("#id").val();
	$.ajax({
		type:'POST',
		url: '?c=indexfile&a=indexfileAdd',
		data:'file=' + file + '&id=' + id,
		success:function(msg){
			if (msg != "成功") {
				return alert(msg);
			}
			window.location.reload();
		}
	});
}
function indexfile_del(val)
{
	if (confirm("确定要删除?") !== true) {
		return;
	}
	$.ajax({
		type:'POST',
		url: '?c=indexfile&a=indexfileDel',
		data:'file=' + val,
		success:function(msg) {
			if (msg != '成功') {
				return alert(msg);
			}
			window.location.reload();
		}
			
	});
}