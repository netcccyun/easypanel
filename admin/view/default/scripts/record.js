function close_piao(id)
{
	document.getElementById(id).style.display = 'none';
}
function record_del(id,domain,name)
{
	if (confirm("确定要删除?") === false) {
		return;
	}
	if (domain == "") {
		return alert("参数错误");
	}	
	$.ajax({
		type:'post',
		url: '?c=record&a=recordDel',
		data:'id=' + id + '&domain=' + domain + '&name=' + name,
		success:function(msg) {
			if (msg != "成功") {
				return alert(msg);
			}
			window.location.reload();
		}
	});
}
function domain_dig(domain,name)
{
	$.ajax({
		type:'get',
		url: '?c=record&a=domainDig',
		data:'domain=' + domain + '&name=' + name,
		dataType:'json',
		success:function(msg){
				if (msg['code'] == 404) {
					return alert('程序错误');
				}
				var m = document.getElementById('msg');
				if (msg['code'] != 200) {
					//$("#msg").html("查询出错");
					m.innterHTML = '查询出错';
					m.style.display = 'block';
					return;
				}
				$("#msg").html(msg['dig']);
				var o = "<input type=button value=关闭 onclick=close_piao('msg')>";
				$("#msg").append(o);
				m.style.display = 'block';
		}
	
	});
}