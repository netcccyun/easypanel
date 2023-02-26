function protection_add()
{
	var path = $("#path").val();
	var referer = $("#referer").val();
	var redirect = $("#redirect").val();
	if (path == "") {
		return alert("目前不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=protection&a=addPath',
		data:'path=' + path + '&referer=' + referer + '&redirect=' + redirect,
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
function protection_del(id)
{
	var data = 'id=' + id;
	functoin_checkon('?c=protection&a=delPath',data);
}
function protection_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=protection&a=startHotlinking',data);
}
function protection_empty()
{
	functoin_checkon('?c=protection&a=emptyAllSettin',null);
}