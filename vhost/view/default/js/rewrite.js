function rewrite_add()
{
	var code = $("#code").val();
	var dst = $("#dst").val();
	var host = $("#host").val();
	if (host == "") {
		return alert("域名不能为空");
	}
	if (dst == "") {
		return alert("跳转域名不能为空");
	}
	if (code == "") {
		code = 301;
	}
	$.ajax({
			type: 'POST',
			url:'?c=rewrite&a=rewriteAdd',
			data:'host=' + host + '&dst=' + dst + '&code=' + code,
			success:function(msg) {
				if (msg != "成功") {
					alert(msg);
				}
				window.location.reload();
			}

	});
}
function rewrite_del(id)
{
	if (confirm("确定要删除?") != true) {
		return;
	}
	$.ajax({
		type:'POST',
		url:'?c=rewrite&a=rewriteDel',
		data:'id=' + id,
		success:function(msg) {
			if (msg != "成功") {
				return alert(msg);
			}
			window.location.reload();
		}
	});
}

function help()
{
	var ddlog = art.dialog({id:'id22',content:msg,icon: 'face-smile',left:'50%',top:'20%'});
	var msg = "可以将一个域名做301,302跳转到另外一个域名<br><input type='button' onclick='example();' value='例子'>";
	ddlog.content(msg);
}
function example()
{
	document.getElementById('host').value = 'www.kanglesoft.com';
	document.getElementById('dst').value = 'kanglesoft.com';
	close_msg();
}
function close_msg()
{
	document.getElementById('msg').style.display='none';
}