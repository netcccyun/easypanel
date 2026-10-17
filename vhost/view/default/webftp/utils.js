var xmlhttp;
function webftp_request(action, data, refreshAfter)
{
	var loading = layer.load(2, {shade: [0.1, '#fff']});
	return jQuery.ajax({
		url: '?c=webftp&a=' + action,
		type: 'POST', data: data, dataType: 'json',
		success: function (result) {
			if (!result || result.code != 200) {
				layer.alert(result && result.msg ? result.msg : '操作失败，请重试', {icon: 2});
				return;
			}
			if (refreshAfter) { refresh(); }
			else { layer.msg(result.msg || '操作成功', {icon: 1}); }
		},
		error: function () { layer.alert('请求失败，请检查网络或重新登录后重试', {icon: 2}); },
		complete: function () { layer.close(loading); }
	});
}

function webftp_selected()
{
	var files = get_file_selected();
	if (!files.length) { layer.msg('请选择文件或目录'); }
	return files;
}

function syncaccess() { webftp_request('syncaccess', {}, true); }
function ajax_open_url(url,result_func)
{
	xmlhttp=new XMLHttpRequest();
	xmlhttp.open("GET",url,true);
	xmlhttp.onreadystatechange=result_func;
	xmlhttp.send(null);
}
function show_div(div_name,flag)
{
	var el=xxkf_obj(div_name);
	if(flag)
		el.style.display='';
	else
		el.style.display='none';
}


var cur_file_id = 0;
	var last_upload_status = false;
	var xxkf_ftlObj=new Array();
	var xxkf_ie =  (document.all) ? true : false;
	function UrlEncode(str) {
		var ret = "";
		//var strSpecial = "!\"#$%&'()*+,/:;<=>?[]^`{|}~%";
		for ( var i = 0; i < str.length; i++) {
			var chr = str.charAt(i);
			if (chr == '+') {
				ret += '%2B';
			} else {
				ret += escape(chr);
			}
		}
		return ret;
	}
	/**
	 * 文件编辑
	 * @param file
	 */

function editfrom(file) {
    if (typeof ace === 'undefined') { layer.alert('代码编辑器未能加载，请检查网络后刷新重试', {icon: 2}); return; }
    openEditorView(0, file);
}
	function addwget(dir)
	{
		var msg = '<div id="wget">';
		msg += '<form name="wget" action="?c=shell&a=wget" method="post" data-shell-command="1" >';
		msg += '下载地址:<input name="url" id="wgeturl" size=60><br><br>';
		msg += '存放文件名:<input name="filename" size=46>';
		msg += '&nbsp;断点续传:<input name="-c" type="checkbox" value="1">';
		msg += '<input name="dir" id="wgetdir" type="hidden">';
		msg += '&nbsp;&nbsp;<input type="submit" value="提交">';
		msg += '</form></div>';
		var dlog = art.dialog({id:'id22',content:'',title:'下载文件',lock:true});
		dlog.content(msg);
		$('#wgetdir').val(cwd);
		$('#wgeturl').focus();
	}
	/**
	 * 创建目录
	 */
	function mkdir()
	{
		var msg = '<form name="mkdir" onsubmit="return addmkdir()">';
			msg += '目录名: <input name="dir" id="mkdirvalue" size="20"> ';
		msg += '<input type="submit" value="确定">';
		msg += '</form>';
		var dlog = art.dialog({id:'id22',content:'',title:'创建目录',lock:true});
		dlog.content(msg);
		$('#mkdirvalue').focus();
	}
	/**
	 * 创建目录
	 */

function addmkdir() {
    var name = $('#mkdirvalue').val();
    if (!name) { layer.msg('目录名称不能为空'); return false; }
    webftp_request('mkdir', {dir: name}, true);
    return false;
}

function addrename(oldfile) {
    var name = $('#renamefile').val();
    if (!name) { layer.msg('新文件名不能为空'); return false; }
    webftp_request('rename', {oldname: oldfile, newname: name}, true);
    return false;
}

function rename(file) {
    var form = $('<form><label>新文件名：<input name="newname" id="renamefile"></label> <button type="submit">确定</button></form>');
    form.on('submit', function () { return addrename(file); });
    art.dialog({id: 'id22', content: form[0], title: '重命名文件', lock: true});
    $('#renamefile').val(file).focus();
}
	function upload()
	{
		uploadFiles.init_upload_path(cwd);
		uploadFiles.upload_layer();
	}
	/**
	 * 压缩文件
	 */

function addzipfile() {
    var filename = $('#compressdst').val(), files = webftp_selected();
    if (!filename) { layer.msg('压缩文件名不能为空'); return false; }
    if (files.length) { webftp_request('compress', {dst: filename, files: files, password: $('#compresspassword').val()}, true); }
    return false;
}

function compress() {
    var files = webftp_selected();
    if (!files.length) return;
    var form = $('<form>文件：<input id="compressdst"><br><br>密码：<input id="compresspassword" placeholder="可留空"><br><br><button type="submit">压缩</button></form>');
    form.on('submit', addzipfile);
    art.dialog({id: 'id22', content: form[0], title: '压缩文件', lock: true});
    $('#compressdst').val(files[files.length - 1] + '.zip').focus();
}

function decompress(file) {
    var form = $('<form>解压目录：<input id="decompressdst"><br><br>密码：<input id="decompresspassword" placeholder="可留空"><br><br><button type="submit">解压</button></form>');
    form.on('submit', function () { return addunzipfile(file); });
    art.dialog({id: 'id22', content: form[0], title: '文件解压', lock: true});
    $('#decompressdst').val(cwd || '/').focus();
}

function addunzipfile(file) {
    var dst = $('#decompressdst').val();
    if (!dst) { layer.msg('解压目录不能为空'); return false; }
    webftp_request('decompress', {files: [file], dst: dst, password: $('#decompresspassword').val()}, true);
    return false;
}
	function file_access(file,is_dir,action)
	{
		var msg = '<form name="file_access_form" action="?c=webftp&a=fileaccess" method="post" onsubmit="return webftp_fileaccess(this);">';
			msg += '<input type="hidden" name="file" id="accessfile" />';
			msg += '<input type="hidden" name="is_dir" id="is_dir" />';
			msg += '<p><input type="radio"	name="act" id="clear" value="clear"/>清除</p>';
			msg += '<p><input type="radio"	name="act" id="deny" value="deny"/>锁定</p>';
			msg += '<p><input type="radio"	name="act" id="static" value="static"/>静态</p>';
			msg += '<p><input type="radio"  name="act" id="authuser" value="auth"/>http认证';
			msg += '<input size=32 name="auth_user"  id="auth_user" onclick="file_access_form.act[3].checked=true">(允许用户,正则)</p>';
			msg += '<p><input type="radio" name="act" id="ip" value="ip" >允许ip段';
			msg += '<input size=32 name="ip" id="ip_value" onclick="file_access_form.act[4].checked=true">(cidr格式,多个由,分开)</p>';
			msg += '<p><input type="submit" value="设置" style="width:100px;"></p>';
			msg += '</form>';
		art.dialog({id:'id22',content:msg,title:'设置文件或目录属性',lock:true});
		$('#accessfile').val(file);
		$('#is_dir').val(is_dir);
		$('#auth_user').val("");
		if (action=='') {
			$('#clear').prop("checked", true);
		} else if (action=='deny'){
			$('#deny').prop("checked", true);
		} else if (action=='static') {
			$('#static').prop("checked", true);
		} else 	if (action.substr(0,5)=='auth:') {
			$('#authuser').prop("checked", true);
			$('#auth_user').val(action.substr(5));
		} else 	if (action.substr(0,3)=='ip:') {
			$('#ip').prop("checked", true);
			$('#ip_value').val(action.substr(3));
		}
	}
	/**
	 * mysql导入
	 * @param file
	 */
	function mysqldumpin(file)
	{
		var msg = '<form name="mysqldumpin" action="?c=shell&a=mysqldumpin" method="post" data-shell-command="1">';
			msg += '数据库密码: <input name="file" id="mysqldumpinfile" type="hidden"><input name="passwd" id="mysqldumpinpasswd" size="16" autocomplete="off" required><input type="submit" value="导入">';
			msg += '</form>';
		art.dialog({id:'id22',content:msg,title:'导入数据库',lock:true});
		$('#mysqldumpinfile').val(file);
		$('#mysqldumpinpasswd').focus();
	}
	//网站恢复
	function restoreweb(file)
	{
		var msg = '<form name="restoreweb" action="?c=shell&a=restoreweb" method="post" data-shell-command="1">';
		msg += "这将导致您现在的数据丢失<input name='file' id='restorewebfile' type='hidden'><br>";
		msg += "解压密码(如无则空):<input type='text' name='password' autocomplete='off'><input name='coverfile' type='checkbox' value='1'>覆盖文件";
		msg += "<input type='submit' value='恢复'></form>";
		art.dialog({id:'id22',content:msg,title:'恢复网站文件',lock:true});
		$('#restorewebfile').val(file);
	}

function rmfile(file) {
    layer.confirm('确定要删除该文件或目录吗？', {icon: 3, title: '确认删除'}, function (index) {
        layer.close(index); webftp_request('rm', {files: [file]}, true);
    });
}

	function opfile_result() {
		if (xmlhttp.readyState == 4) {
			try {
				if (xmlhttp.status == 200) {
					var dataArray = xmlhttp.responseXML
							.getElementsByTagName('result');
					var code = dataArray[0].getAttribute('code');
					var dlog = art.dialog({id:'id22',content:msg,title:'',left:'65%'});
					if (code == 200) {
						dlog.content('操作成功，请选择相应目录后点粘贴完成操作!');
					} else {
						dlog.content('操作失败');
					}
					dlog.time(2);
				}
			} catch (e) {
				alert(xmlhttp.responseText);
			}
		}
	}
	function getfile(file, dir) {
		window.location = '?c=webftp&a=getfile&dir=' + dir + '&file='
				+ UrlEncode(file);
	}

function cutfile(file) { webftp_request('cut', {files: [file]}, false); }

function copyfile(file) { webftp_request('copy', {files: [file]}, false); }
	function selectAll() {
		var aa = $('input[name="files[]"]');
		for ( var i = 0; i < aa.length; i++) {
			aa[i].checked = true;
		}
	}
	function reversSelectAll() {
		var aa = $('input[name="files[]"]');
		for ( var i = 0; i < aa.length; i++) {
			aa[i].checked = !aa[i].checked;
		}
	}

function readonly(ro) {
    var files = webftp_selected();
    if (files.length) webftp_request('readonly', {ro: ro, files: files}, true);
}

function rmall() {
    var files = webftp_selected();
    if (!files.length) return;
    layer.confirm('确定要删除选中的文件或目录吗？', {icon: 3, title: '确认删除'}, function (index) {
        layer.close(index); webftp_request('rm', {files: files}, true);
    });
}
	function get_file_selected() {
		var files = [];
		var aa = $('input[name="files[]"]');
		for ( var i = 0; i < aa.length; i++) {
			if (aa[i].checked) {
				files.push(aa[i].value);
			}
		}
		return files;
	}

function cutall() {
    var files = webftp_selected();
    if (files.length) webftp_request('cut', {files: files}, false);
}

function copyall() {
    var files = webftp_selected();
    if (files.length) webftp_request('copy', {files: files}, false);
}

function parseall() { webftp_request('parse', {}, true); }
	function keypress(evt) {
		evt = evt ? evt : (window.event ? window.event : null);
		var keyCode = evt.keyCode;
		if (window.event) {
			if (keyCode == 22) {
				parseall();
			} else if (keyCode == 24) {
				cutall();
			}
		}
	}
	function keyup(evt) {
		evt = evt ? evt : (window.event ? window.event : null);
		var keyCode = evt.keyCode;
		if (keyCode == 67) {
			copyall();
		}
		if (window.event == null) {
			if (keyCode == 88) {
				cutall();
			} else if (keyCode == 86) {
				parseall();
			}
		}
	}
	function refresh() {
		window.location = '?c=webftp&a=index';
	}

function webftp_fileaccess(form) {
    webftp_request('fileaccess', $(form).serialize(), true);
    return false;
}
