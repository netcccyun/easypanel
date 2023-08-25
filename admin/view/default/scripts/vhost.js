function ajaxSync(name) {
	//$("#sync").css('display', '');
	var dlog = art.dialog({id:'id22',icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content("请等待,正在执行中...<br>如果该空间数据量大，则有可能会超过php执行时间而失败");
	$.ajax({
		type : 'get',
		url : '?c=vhost&a=resync',
		data : 'name=' + name,
		dataType : 'json',
		success : function(ret) {
			var msg = ret['code'] == 200 ? "重建成功" : ret['msg'] ? ret['msg'] : "重建失败";
			dlog.content(msg);
			dlog.title("三秒后关闭");
			dlog.time(3);
		}
	});
}
function change_checked_status(status) {
	if (confirm("确定要对所有选中的主机进行操作吗?") === false) {
		return;
	}
	//init_div();
	var status_name = status == 1 ? "暂停 " : '开通 ';
	var dlog = art.dialog({id:'id22',icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$(":checkbox:checked").each(
					function() {
						var name = this.value;
						if (!name) {
							return;
						}
						$.ajax({
							type : 'get',
							url : '?c=vhost&a=setStatus',
							data : 'name=' + name + '&status=' + status,
							dataType : 'json',
							async : false,
							success : function(ret) {
								var msg;
								if (ret['code'] != 200) {
									msg = '<font color="#fff">';
									msg += ret['msg'] ? ret['msg']
											: status_name + name + ' 失败';
									msg += "</font>";
								} else {											
									msg = status_name + name + ' 成功';
								}
								document.getElementById('show_msg').style.height = count * 20;
								$("#aui_content").append(msg + '<br>');
								count++;
							}
						});
					});
	//setTimeout(close_showmsg, 2000);
	setTimeout(function(){
		dlog.close();
		window.location = window.location;
		dlog = null;
	}, 3000);
}
function del_checked() {
	if (confirm("确定要删除所有选中的主机吗?") === false) {
		return;
	}
	//init_div();
	var dlog = art.dialog({id:'id22',icon: 'face-smile',left:'50%',top:'20%'});
	dlog.content('');
	$(":checkbox:checked")
			.each(
					function() {
						var name = this.value;
						if (!name) {
							return;
						}
						$.ajax({
							type : 'get',
							url : '?c=vhost&a=del',
							data : 'name=' + name,
							dataType : 'json',
							async : false,
							success : function(ret) {
								var msg;
								if (ret['code'] == 200) {
									msg = '删除 ' + name + ' 成功';
								} else {
									msg = '删除 ' + name + ' 失败';
								}
								$("#aui_content").append(msg + '<br>');
								count++;
							}
						});
					});
	setTimeout(function(){
		dlog.close();
		window.location = window.location;
		dlog = null;
	}, 3000);
}
function show_piao_div() {
	var div = document.getElementById('show_msg');
	if (!div) {
		return;
	}
	if (div.style.display != 'none') {
		return;
	}
	div.style.height = 30;
	div.style.top = 260;
	div.style.right = 15;
	div.style.width = 200;
	div.style.display = 'block';
	// setTimeout(close_showmsg,1000);
}
function close_showmsg() {
	$("#show_msg").fadeOut(2000);
}
function init_div() {
	
	var div = document.getElementById('show_msg');
	if (!div) {
		return;
	}
	div.style.height = 0;
	div.style.width = 0;
	div.innterHTML = '';
	div.style.display = 'none';
}
function changePw(name) {
	var r = confirm("确定要重设密码吗?");
	if (r == false)
		return;
	$.get("?c=vhost&a=randPassword&name=" + name, function(data) {
		$("#result").html(data);
	});
}
function setStatus(name, status) {
	$.ajax({
		type : 'get',
		url : '?c=vhost&a=setStatus',
		data : 'name=' + name + '&status=' + status,
		dataType : 'json',
		success : function(ret) {
			if (ret['code'] != 200) {
				return alert(ret['msg'] ? ret['msg'] : '操作失败');
			}
			window.location.reload();
		}
	});
}
function addMonth(name) {
	var r = prompt("需要延时多少个月?", '1');
	if (r == null) {
		return;
	}
	var dlog = art.dialog({id:'id22',icon: 'face-smile',left:'50%',top:'20%',show:false});
	$.get("?c=product&a=addExpireTime&name=" + name + "&month=" + r, function(
			data) {
		dlog.show(true);
		dlog.content(data);
		setTimeout(function(){
			window.location = window.location;
		},2000);
	});
}
function sync_checked() {
	if (confirm("确定要重建所有选中的主机吗?") === false) {
		return;
	}
	//init_div();
	//$("#sync").css('display', '');
	var dlog = art.dialog({id:'id22',content:'请等待,正在执行中...<br>',icon: 'face-smile',left:'50%',top:'20%'});
	//$("#sync").html("请等待,正在执行中......<br>如果该空间数据量大，请在kangle的3311管理中将超时时间调大");
	$(":checkbox:checked").each(function() {
		var name = this.value;
		if (!name) {
			return;
		}
		$.ajax({
			type : 'get',
			url : '?c=vhost&a=resync',
			data : 'name=' + name,
			dataType : 'json',
			async : false,
			success : function(ret) {
				var msg;
				if (ret['code'] != 200) {
					msg = '<font color="#fff">';
					msg += ret['msg'] ? ret['msg'] : '重建' + name + '失败';
					msg += "</font>";
				} else {
					msg = '重建' + name + '成功';
				}
				$("#aui_content").append(msg + '<br>');
				count++;
			}
		});

	});
	setTimeout(function(){
		dlog.close();
		window.location = window.location;
		dlog = null;
	}, 3000);
}
function select_all(checked) {
	if (checked == 1) {
		$(":checkbox").attr('checked', true);
	} else {
		$.each($(":checkbox"), function(key, val) {
			val.checked = !val.checked;
		});
	}
}
function delVhost(name) {
	var r = confirm("如果要关闭空间，更改其状态即可，删除空间后数据不可恢复，确定要删除空间吗?");
	if (r == false) {
		return;
	}
	var dlog = art.dialog({id:'id22',content:'请等待,正在执行中...<br>',icon: 'face-smile',left:'50%',top:'20%'});
	$.ajax({
		type : 'post',
		url : "?c=vhost&a=del",
		data : "name=" + name,
		async : false,
		dataType : 'json',
		success : function(ret) {
			if (ret['code'] != 200) {
				dlog.content(ret['msg'] ? ret['msg'] : '删除' + name + '失败');
				return ;
			}
			window.location.reload();
		}
	});
}