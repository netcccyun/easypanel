function chche_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=cache&a=cacheCheckOn',data);
}
function cache_add()
{
	var mode = $("#mode").val();
	var cache_value = $("#cache_value").val();
	var max_age = $("#max_age").val();
	if (max_age == "") {
		return alert("缓存时间不能为空");
	}
	if (cache_value == "") {
		return alert("值不能为空");
	}
	var s = $("#static").attr('checked');
	if (s != 'checked') {
		s = "";
	}else {
		s = 1;
	}
	$.ajax({
		   type: "POST",
		   url: "?c=cache&a=cacheAdd",
		   data: "mode=" + mode + "&cache_value=" + cache_value + "&max_age=" + max_age + "&static=" + s ,
		   success: function(msg){
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
function close_msg()
{
	document.getElementById('msg').style.display='none';
}
function pro_msg(val)
{
	//document.getElementById('msg').style.display='none';
	var dlog = art.dialog({id:'id232',content:'',title:'小纸条',icon: '',top:'53%'});
	if (val == 'url') {
		dlog.content("不能多个，可以用正则表示，如<br>http://www.kanglesoft.com/.*");
	}
	if (val == 'file_ext') {
		dlog.content("可以添加多个，如<br>php|asp|jsp");
	}
	if (val == 'content-type') {
		dlog.content("不能多个，可以用正则表示，如<br>text/ht*,text/*");
	}
	//$("#msg").show("slow");
}
function cache_del(id)
{
	if (confirm("确定要删除?") != true) {
		return;
	}
	$.ajax({
		type:'POST',
		url:'?c=cache&a=cacheDel',
		data:'id=' + id,
		success:function(msg) {
			if (msg != "成功") {
				return alert(msg);
			}
			window.location.reload();
		},
		complete: function(msg){
			show_sync();
		}
	});
}