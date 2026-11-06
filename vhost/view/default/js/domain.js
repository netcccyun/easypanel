var replace = 0;
function domain_subdir_value()
{
	if ($("#subdir_base").length == 0) {
		return $("#subdir").val();
	}
	var base = $.trim($("#subdir_base").val());
	var second = $.trim($("#subdir_second").val()).replace(/^\/+|\/+$/g, '');
	if (base == '') {
		return second;
	}
	return second == '' ? base : base + '/' + second;
}
function domain_subdir_set(value)
{
	value = value || '';
	if ($("#subdir_base").length == 0) {
		$("#subdir").val(value).focus();
		return;
	}
	var idx = value.indexOf('/');
	var base = idx == -1 ? value : value.substring(0, idx);
	var second = idx == -1 ? '' : value.substring(idx + 1);
	$("#subdir_base").val(base);
	$("#subdir_second").val(second).focus();
	domain_subdir_refresh();
}
// 二级候选：按当前一级目录从服务端取子目录，一级为空取根目录候选
function domain_subdir_refresh()
{
	var list = $("#subdir_second_list");
	if (list.length == 0) return;
	clearTimeout(domain_subdir_refresh.timer);
	domain_subdir_refresh.timer = setTimeout(function () {
		$.get('?c=domain&a=subdirs', {base: $.trim($("#subdir_base").val())}, function (data) {
			if (!data || data.code !== 0) return;
			list.empty();
			$.each(data.list || [], function (i, name) {
				list.append($("<option>").val(name));
			});
		}, 'json');
	}, 200);
}
// 目录候选下拉：自绘弹层替代浏览器原生 datalist 下拉（原生弹层内部样式不可控）
var ep_combo = { $in: null, $box: null, index: -1 };
function ep_combo_close()
{
	if (ep_combo.$box) {
		ep_combo.$box.hide().empty();
	}
	if (ep_combo.$in) {
		ep_combo.$in.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
	}
	ep_combo.$in = null;
	ep_combo.index = -1;
}
function ep_combo_mark()
{
	var $items = ep_combo.$box.children('.ep-combo-item');
	$items.removeClass('is-on');
	var $cur = $items.eq(ep_combo.index).addClass('is-on');
	ep_combo.$in.attr('aria-activedescendant', $cur.attr('id'));
	var box = ep_combo.$box[0];
	var top = $cur[0].offsetTop;
	if (top < box.scrollTop) {
		box.scrollTop = top;
	} else if (top + $cur[0].offsetHeight > box.scrollTop + box.clientHeight) {
		box.scrollTop = top + $cur[0].offsetHeight - box.clientHeight;
	}
}
function ep_combo_open($in, filter)
{
	ep_combo_close();
	var val = $.trim($in.val()).toLowerCase();
	var values = [];
	$in.siblings('datalist').find('option').each(function () {
		var v = String($(this).val());
		if (v === '') return;
		if (!filter || val === '' || v.toLowerCase().indexOf(val) !== -1) {
			values.push(v);
		}
	});
	if (!values.length) return;
	if (!ep_combo.$box) {
		ep_combo.$box = $('<div id="ep_combo_list" class="ep-combo-list" role="listbox"></div>').appendTo('body');
	}
	ep_combo.$in = $in;
	$.each(values, function (i, v) {
		ep_combo.$box.append($('<div class="ep-combo-item" role="option"></div>').attr('id', 'ep_combo_opt_' + i).attr('title', v).text(v));
	});
	ep_combo.index = 0;
	$in.attr({'aria-expanded': 'true', 'aria-activedescendant': 'ep_combo_opt_0'});
	ep_combo.$box.show();
	ep_combo_place();
	ep_combo_mark();
}
function ep_combo_place()
{
	// 优先输入框下方，空间不足翻转到上方；滚动/缩放时保持跟随而不是关闭
	if (!ep_combo.$in || !ep_combo.$box) return;
	var r = ep_combo.$in[0].getBoundingClientRect();
	var h = ep_combo.$box[0].offsetHeight;
	var top = r.bottom + 4;
	if (r.bottom + 4 + h > window.innerHeight && r.top - 4 - h > 0) {
		top = r.top - 4 - h;
	}
	ep_combo.$box.css({left: r.left + 'px', top: top + 'px', width: r.width + 'px'});
}
function ep_combo_pick(v)
{
	var $in = ep_combo.$in;
	ep_combo_close();
	if ($in) {
		$in.val(v).trigger('change').focus();
	}
}
function ep_combo_init()
{
	$('.ep-combo').each(function () {
		var $wrap = $(this);
		var $in = $wrap.find('input').first();
		if (!$in.length || !$wrap.find('datalist').length) return;
		$in.attr({'aria-haspopup': 'listbox', 'aria-expanded': 'false'});
		$in.on('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				if (!ep_combo.$in || ep_combo.$in[0] !== this) {
					ep_combo_open($in, false);
					return;
				}
				var n = ep_combo.$box.children('.ep-combo-item').length;
				ep_combo.index = (ep_combo.index + (e.key === 'ArrowDown' ? 1 : -1) + n) % n;
				ep_combo_mark();
			} else if (e.key === 'Enter') {
				if (ep_combo.$in && ep_combo.$in[0] === this) {
					e.preventDefault();
					ep_combo_pick(ep_combo.$box.children('.ep-combo-item').eq(ep_combo.index).text());
				}
			} else if (e.key === 'Escape') {
				if (ep_combo.$in) {
					e.preventDefault();
					e.stopPropagation();
					ep_combo_close();
				}
			}
		});
		// 输入时按内容过滤候选，无匹配则收起（可继续自由输入）
		$in.on('input', function () {
			ep_combo_open($in, true);
		});
		$in.on('blur', ep_combo_close);
		// 点箭头：阻止默认聚焦行为，避免先 blur 关闭导致开关状态判断错乱
		$wrap.on('mousedown', '.ep-combo-arrow', function (e) {
			e.preventDefault();
		});
		$wrap.on('click', '.ep-combo-arrow', function () {
			$in.focus();
			if (ep_combo.$in && ep_combo.$in[0] === $in[0]) {
				ep_combo_close();
			} else {
				ep_combo_open($in, false);
			}
		});
	});
	// 选择项：mousedown 先于 blur 执行并阻止失焦，click 兼容触屏
	$(document).on('mousedown click', '.ep-combo-item', function (e) {
		e.preventDefault();
		ep_combo_pick($(this).text());
	});
	$(document).on('mousedown', function (e) {
		if (ep_combo.$box && !$(e.target).closest('.ep-combo, .ep-combo-list').length) {
			ep_combo_close();
		}
	});
	$(window).on('scroll resize', ep_combo_place);
}
function domain_add()
{
	var domain = $("#domain").val();
	var subdir = domain_subdir_value();
	var proto = $("#proto").val();
	if(domain == ''){
		return alert('域名不能为空');
	}
	if($("#proto").length>0 && subdir==''){
		return alert('源站IP不能为空');
	}
	$.ajax({
		type:'POST',
		url: '?c=domain&a=add',
		data:'domain=' + domain + '&subdir=' + subdir + '&proto=' + proto + '&replace=' + replace,
		async:false,
		success:function(msg){
			if (msg != "成功"){
				//document.getElementById("button").disabled=true;
				return alert(msg);
			}
		},
		complete:function(msg){
			show_sync();
			window.location = window.location;
		}
	});
}
var dialog = null;
function piao_domain_import()
{
	var html = '<div>覆盖:<input id="replace" name="replace" type=checkbox value=1 checked></div>';
		html += '<div class="piao_div">一行一个，域名和IP(或目录)用空格分割</div>';
		html += '<div class="piao_div"><textarea id="domain_import_value" rows=10 cols=60 placeholder="www.cdn.com 192.168.1.2"></textarea>';
		html += '<div><input type=button class="btn btn-warning" value="导入" onclick="domain_import()"></div>';
		var d = art.dialog({id:'domain_import'});
		d.title('批量添加域名绑定');
		d.content(html);
		dialog = d;
}
function domain_import()
{
	var values = $("#domain_import_value").val();
	var replace = $("#replace").attr('checked');
	if (!values) {
		return false;
	}
	dialog.close();
	var addmsg ='';
	var last = null;
	var rows = values.split("\n");
	for( var i in rows) {
		var row = rows[i].split(' ');
		var domain = row[0];
		if (!domain) {
			break;
		}
		var ip = row[1];
		if (!ip) {
			if (last) {
				ip = last;
			}else {
				addmsg += domain + '没有值,增加失败<br>';
				continue;
			}
		}
		$.ajax({
			url:'?c=domain&a=add',
			type:'get',
			data:{
				domain:domain,subdir:ip,replace:replace?1:!1
			},
			async:false,
			success:function(a) {
				addmsg += "<b";
				if (a =='成功') {
					last = ip;
					addmsg += ' class="green">';
				}else {
					addmsg +=' class="red">';
				}
				addmsg += domain + a + '</b><br>';
			}
		});
		
	}
	if (addmsg) {
		var pd = art.dialog({id:'domain_import_msg',title:'批量导入',esc:false});
		pd.content(addmsg);
		$(".aui_close").bind("click",function(){
			window.location = window.location;
		});
	}
}
function domain_sync()
{
}
function domain_del(val,id)
{
	if (confirm("确定要删除?") !=true) {
		return;
	}
	$.ajax({
		type:'POST',
		url: '?c=domain&a=del',
		data:'domain=' + val,
		async:false,
		success:function(msg){
			if(msg != "成功"){
				return alert(msg);
			}
			 //$("#" + id +"t" ).fadeOut(1000);//cdn未实现
			
		},
		complete:function(msg){
			show_sync();
			window.location.reload();
		}
	});
	
}
function domain_edit(val)
{
	$.ajax({
		type:'GET',
		url: '?c=domain&a=info&domain=' + val,
		dataType: "json",
		success:function(data){
			if(data.code == 0){
				$("#domain").val(val);
				$("#domain").attr("disabled", true);
				domain_subdir_set(data.subdir);
				$("#proto").val(data.proto);
				$("#button_import").hide();
				$("#button_cancel").show();
				replace = 1;
			}else{
				return alert(data.msg);
			}
		}
	});
}
function cancel_edit(val)
{
	$("#domain").val("");
	$("#domain").attr("disabled", false);
	if($("#proto").length>0){
		$("#subdir").val("");
		$("#proto").val('http');
	}
	domain_subdir_set(subdir_base_default || '');
	$("#button_import").show();
	$("#button_cancel").hide();
	replace = 0;
}
var subdir_base_default = null;
$(function () {
	ep_combo_init();
	subdir_base_default = $("#subdir_base").val();
	// 初始加载二级候选（一级为默认目录时）
	domain_subdir_refresh();
	// 一级变化时刷新二级候选
	$("#subdir_base").on('input change', function () {
		domain_subdir_refresh();
	});
});