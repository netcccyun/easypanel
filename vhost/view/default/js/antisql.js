function antisql_checkon(val)
{
	var data = 'status=' + val;
	functoin_checkon('?c=antisql&a=antisqlCheckOn',data);
}

function antisql_del(id)
{
	if (confirm("确定要删除?") != true) {
		return;
	}
	var url='?c=antisql&a=antisqlDel&id=' + id;
	$.ajax({
		   type: "POST",
		   url: url,
		   success: function(msg){
		   		if(msg != "成功") { 
		     		return alert(msg);
		   		}
		     	window.location.reload();
		   },
		   complete: function(msg){
				show_sync();
			}
	});
	
}
function antisql_add()
{
	var param = $("#param").val();
	if (param == "") {
		return alert("参数值不能为空");
	}
	var charset = $("#charset").val();
	$.ajax({
		   type: "POST",
		   url: '?c=antisql&a=antisqlAdd',
		   data: "param_value=" + param + "&charset=" + charset,
		   success: function(msg){
		   		if(msg != "成功") { 
		     		return alert(msg);
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
function copy_code()
{
	document.getElementById('param').value = "'.*[; ]?((or)|(insert)|(select)|(union)|(update)|(delete)|(replace)|(create)|(drop)|(alter)|(grant)|(load)|(show)|(exec))[\\s(]";
	close_msg();
}
function piao_msg()
{
	var ddlog = art.dialog({id:'id22',content:msg,icon: 'face-smile',left:'50%',top:'20%'});
	var msg = "正则输入，如需防sql注入，可用以下正则:<br>";
		msg += "'.*[; ]?((or)|(insert)|(select)|(union)|(update)|(delete)|(replace)|(create)|(drop)|(alter)|(grant)|(load)|(show)|(exec))[\\s(]<br>";
		msg += "<input type='button' onclick='copy_code();' value='复制代码'>";
	ddlog.content(msg);
	//$("#msg").html(msg);
	//$("#msg").show("slow");
}