function protection_add()
{
	var path = $("#path").val();
	var referer = $("#referer").val();
	var redirect = $("#redirect").val();
	var fileExt = $("#file_ext").val();
	var allowEmpty = $("#allow_empty").is(':checked') ? '1' : '0';
	if (path == "") {
		return alert("保护路径不能为空");
	}
	$.ajax({
		type:'post',
		url:'?c=protection&a=addPath',
		data: {path: path, referer: referer, redirect: redirect, file_ext: fileExt, allow_empty: allowEmpty},
		success:function(msg) {
			if (msg != "成功"){
				return alert(msg);
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
