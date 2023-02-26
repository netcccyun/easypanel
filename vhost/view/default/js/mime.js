function mime_add()
{
	var file_ext = $("#file_ext").val();
	if (file_ext == "") {
		return alert("文件扩展名不能为空");
	}
	var mime_type = $("#mime_type").val();
	if (mime_type == "") {
		return alert("mime类型不能为空");
	}
	var cache_time = $("#cache_time").val();
	var gzip = 0;
	if ($("#gzip").attr('checked') == 'checked') {
		gzip = 1;
	}
	$.ajax({
		   type: "POST",
		   url: "?c=mime&a=mimeAdd",
		   data: "file_ext=" + file_ext + "&mime_type=" + mime_type + "&cache_time=" + cache_time + "&gzip=" + gzip,
		   success: function(msg){
		    	if (msg != "成功") {
					return alert(msg);
		    	}
		     	window.location.reload();
		   }
	});	
}
function close_msg()
{
	document.getElementById('msg').style.display='none';
}
function update_mime_from(fe,ty,ca,gz)
{
	$("#msg").html("");
	if (gz ==1) {
		gz = 'checked';
	}else{
		gz = "";
	}
	$("#msg").append("<form action='?c=mime&a=mimeUpdate' method='post'>");
	$("#msg").append("<div class='up_text'>文件名:</div><div class='up_input'><input readOnly='true' name='file_ext' id=fe value=" + fe + "><div>");
	$("#msg").append("<div class='up_text'>mime类型:</div><div class='up_input'><input name='mime_type' id=ty value=" + ty + "><div>");
	$("#msg").append("<div class='up_text'>缓存时间:</div><div class='up_input'><input name='cache_time' id=ca value=" + ca + "><div>");
	$("#msg").append("<div class='up_text'>是否压缩:</div><div class='up_input'><input name='gzip' type=checkbox id=gz " + gz + "><div>");
	$("#msg").append("<div><input type='button' onclick='update_mime()' value='提交'><input type='button' onclick='close_msg();' value='关闭'></div>");
	$("#msg").append("</form>");
	$("#msg").show("slow");
}
function update_mime()
{
	var fe = $("#fe").val();
	var ty = $("#ty").val();
	var ca = $("#ca").val();
	var	gz = 0;
	if($("#gz").attr('checked')=='checked'){
		gz = 1;
	}
	if (fe == "" ) {
		return alert("文件扩展名不能为空");
	}
	if (ty == "") {
		return alert("mime类型不能为空");
	}
	$.ajax({
		type:'POST',
		url:'?c=mime&a=mimeUpdate',
		data:'file_ext=' + fe + '&mime_type=' + ty + '&cache_time=' + ca + '&gzip=' + gz,
		success:function(msg) {
		close_msg();
			if(msg != "成功") {
				return	alert(msg);
			}
			window.location.reload();
		}
	});
	
}
function del_mime(val)
{
	if (confirm("确认删除?")!=true) {
		return;
	}
	$.ajax({
		type:'POST',
		url:'?c=mime&a=mimeDel',
		data:'file_ext=' + val,
		success:function (msg) {
			if (msg != "成功") {
				return alert(msg);
			}
			window.location.reload();
		}
	});
}