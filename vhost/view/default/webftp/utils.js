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
	function editfrom(files)
	{
		var editurl = "?c=webftp&a=editfrom";
		var posturl = "file="+files;
		var msg = '<div class="main webftp"  id="editfile" >';
		msg += '<textarea name="content" id="content" >正在载入...</textarea>';
		msg += '<br><input align="center" class="btn btn-warning" value="保存" style="width:160px;height:28px;" onClick="editsave(\'on\')" type="button">';
		msg += '</div>';
		var dlog = art.dialog({id:'id22',content:'',title:'编辑'+files+'文件',width:'800px',height:'auto',lock:true,top:'0px'});
		//dlog.position("200px",0);
		dlog.content(msg);
		var div = jQuery("#editfile");
		jq.ajax({url:editurl,type:'get',dataType:"json",data:posturl,
			success:function(ret){
				if (ret['code'] != 200) {
					dlog.close();
					alert(ret['msg'] ? ret['msg'] : '编辑文件异常');
					return;
				}
				var filename = ret['filename'];
				var charset = ret['charset'];
				div.append('<input name="charset" type="hidden" id="charset" value="' +charset + '">');
				div.append('<input name="filecwd" id="filecwd" type="hidden" value="' + filename + '">');
				var content = ret['content'];
				var c = div.find("#content");
				//c.css('width',jQuery(window).width()-300).css('height',window.screen.height-400);
				c.css('width',"1000px").css('height',"760px");
				var ret = decodeURIComponent(content);
				c.val(ret);
			},
			error:function(e) {
				dlog.close();
				alert(e.responseText);
			}
		});
	}
	/**
	 * 文件保存
	 * @param sta
	 */
	function editsave(sta)
	{
		var jq = jQuery.noConflict();
		var content = jq("#content").val();
		var charset = jq("#charset").val();
		var filename = jq("#filecwd").val();
		var editurl = "?c=webftp&a=editsave";
		var posturl = "content=" + encodeURIComponent(content) + "&charset=" + charset + "&filename=" + filename;
		jq.ajax({url:editurl,type:'post',dataType:'html',data:posturl,
			success:function(data){
				alert(data);
		}
		});
	}
	/**
	 * 远程下载
	 * @param dir
	 */
	function addwget(dir)
	{
		var msg = '<div id="wget">';
		msg += '<form name="wget" action="?c=shell&a=wget" method="post" target="_blank" >';
		msg += '下载地址:<input name="url" id="wgeturl" size=60><br><br>';
		msg += '新文件名:<input name="filename" size=46><br><br>';
		msg += '断点续传:<input name="-c" type="checkbox" value="1"><br><br>';
		msg += '<input name="dir" id="wgetdir" type="hidden">';
		msg += '<input type="submit" style="width:100px" value="提交">';
		msg += '</form></div>';
		var dlog = art.dialog({id:'id22',content:'',title:'下载文件',lock:true});
		dlog.content(msg);
		document.getElementById('wgetdir').value= cwd;
		document.getElementById('wgeturl').focus();
	}
	/**
	 * 创建目录
	 */
	function mkdir() 
	{
		var msg = '<form name="mkdir" onSubmit="return addmkdir()">';
			msg += '目录名: <input name="dir" id="mkdirvalue" size="20">';
		msg += '<input value="确定" style="width:100px"	onClick="addmkdir()" type="button">';
		msg += '</form>';
		var dlog = art.dialog({id:'id22',content:'',title:'创建目录',lock:true});
		dlog.content(msg);
		document.getElementById('mkdirvalue').focus();
	}
	/**
	 * 创建目录
	 */
	function addmkdir() 
	{
		var dirvalue = document.getElementById('mkdirvalue').value;
		if (dirvalue == '') {
			alert("目录名称不能为空");
			return ;
		}
		window.location = '?c=webftp&a=mkdir&dir=' + UrlEncode(dirvalue);
	}
	/**
	 * 文件重命名
	 * @param oldfile
	 * @returns {Boolean}
	 */
	function addrename(oldfile)
	{
		var newfile = document.getElementById('renamefile').value;
		window.location = '?c=webftp&a=rename&oldname=' + UrlEncode(oldfile)
				+ '&newname=' + UrlEncode(newfile);
		return false;
	}
	/**
	 * 文件件重命名
	 * @param file
	 */
	function rename(file) 
	{
		var msg = '<form name="rename" action="javascript:addrename(\'' + file + '\')">';
			msg += '新文件名:<input style="height:32px;line-height:32px;padding:0px;font-size:16px" name="newname" id="renamefile" >';
			msg += '<input value="确定" style="width:80px;height:32px;" onClick="addrename(\'' + file + '\')" type="button">';
			msg += '</form>';
		var dlog = art.dialog({id:'id22',content:msg,title:'',lock:true});
		dlog.title('重命名文件' + file);
		document.getElementById('renamefile').focus();
		document.getElementById('renamefile').value = file;
		var leng = file.length;
		jQuery('#renamefile').css('width',leng*13+120);
	}
	function show_upload_msg()
	{
		var msg = "可将多个文件或目录压缩成zip或7z格式(rar不支持)上传<br>上传后在文件后面会提解压的按钮，点击解压即可.";
		art.dialog({id:'show_upload_msg',content:msg,title:'小纸条'});
	}
	/**
	 * 文件上传
	 */
	function upload() 
	{
		var msg = '<div id="upload">';
		msg += "<p><i>可以上传压缩包文件后解压，当前支持格式为<b>.7z</b>和<b>.zip</b></i></p>";
		msg += '<form name="upform" enctype="multipart/form-data" action="?c=webftp&a=upsave" method="post">';
		msg += '本地文件:<input name="myfile" size=50 type="file">&nbsp;&nbsp;<input style="width:100px" value="上传" type="Submit"><br><br>';
		msg += '</form></div>';
		var dlog = art.dialog({id:'id22',content:'',title:'',lock:true});
		dlog.content(msg);
	}
	/**
	 * 压缩文件
	 */
	function addzipfile() {
		var filename = document.getElementById('compressdst').value;
		if (filename == '') {
			alert("压缩文件名不能为空");
			return;
		}
		var url = '';
		var aa = document.getElementsByName('files[]');
		for ( var i = 0; i < aa.length; i++) {
			if (aa[i].checked) {
				url += '&files[]=' + UrlEncode(aa[i].value);
			}
		}
		if (url == '') {
			alert('请选择你要操作的文件');
			return;
		}
		var password = document.getElementById('compresspassword').value;
		var ajax_url = '?c=webftp&a=compress&password=' + password + '&dst=' + UrlEncode(filename) + url;
		ajax_open_url(ajax_url, zipop_result);
		return false;
	}
	/**
	 * 压缩文件
	 */
	function compress() 
	{
		var last_file = '';
		var url = '';
		var aa = document.getElementsByName('files[]');
		for ( var i = 0; i < aa.length; i++) {
			if (aa[i].checked) {
				last_file = aa[i].value;
				url += '&files[]=' + UrlEncode(aa[i].value);
			}
		}
		if (last_file == '') {
			alert('请选择你要操作的文件');
			return;
		}
		var zipfilename = last_file + ".zip";
		var msg = '<form name="compress" action="javascript:addzipfile()">';
		msg += '文件:<input name="dst" id="compressdst" value=' + zipfilename + '><br><br>';
		msg += '密码:<input name="password" id="compresspassword" ><br><br>';
		msg += '<input style="width:100px;" value="压缩" onClick="addzipfile()" type="button">';
		msg += '</form>';
		art.dialog({id:'id22',content:msg,title:'压缩文件',lock:true});
		document.getElementById('compresspassword').focus();
		jQuery('#compressdst').css('width',(zipfilename.length * 12));
	}
	/**
	 * 文件解压
	 * @param file
	 */
	function decompress(file)
	{
		var msg = '<form name="decompress" onSubmit="return addunzipfile(\'' + file + '\')">';
			msg += '<div >解压目录:<input name="dst" id="decompressdst"></div>';
			msg += '<div style="margin-top:10px;">密&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;码:<input name="password" id="decompresspassword" placeholder="没有请留空"></div>';
			msg += '<div style="margin-top:10px;"><input value="解压" style="margin-left:60px;width:160px;" onClick="addunzipfile(\'' + file + '\')" type="button">';
			msg += '</form>';
		art.dialog({id:'id22',content:msg,title:'',lock:true});
		document.getElementById('decompressdst').value = cwd ? cwd : '/'; 
		document.getElementById('decompresspassword').focus();
		jQuery("#decompressdst").css('width',(cwd.length*8)+120);
	}
	/**
	 * 文件解压
	 * @param file
	 * @returns {Boolean}
	 */
	function addunzipfile(file) 
	{
		var password = document.getElementById('decompresspassword').value;
		var dst = document.getElementById('decompressdst').value;
		ajax_open_url('?c=webftp&a=decompress&password=' + password + '&files[]=' + UrlEncode(file) + '&dst='+ UrlEncode(dst), zipop_result);
		return false;
	}
	/**
	 * 设置目录或文件属性
	 * @param file
	 * @param is_dir
	 * @param action
	 */
	function file_access(file,is_dir,action)
	{
		var msg = '<form name="file_access_form" action="?c=webftp&a=fileaccess" method="post">';
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
		document.getElementById('accessfile').value = file;
		document.getElementById('is_dir').value = is_dir;
		document.getElementById('auth_user').value = "";
		if (action=='') {
			document.getElementById('clear').checked = true;
		} else if (action=='deny'){
			document.getElementById('deny').checked = true;
		} else if (action=='static') {
			document.getElementById('static').checked = true;
		} else 	if (action.substr(0,5)=='auth:') {
			document.getElementById('authuser').checked = true;
			document.getElementById('auth_user').value = action.substr(5);
		} else 	if (action.substr(0,3)=='ip:') {
			document.getElementById('ip').checked = true;
			document.getElementById('ip_value').value = action.substr(3);
		}
		//show_div('file_access', true);
	}
	/**
	 * mysql导入
	 * @param file
	 */
	function mysqldumpin(file)
	{
		var msg = '<form name="mysqldumpin" action="?c=shell&a=mysqldumpin" method="post" target="_blank">';
			msg += '数据库密码: <input name="file" id="mysqldumpinfile" type="hidden"><input name="passwd" id="mysqldumpinpasswd" size="16"><input type="submit" value="导入">';
			msg += '</form>';
		art.dialog({id:'id22',content:msg,title:'导入数据库',lock:true});	
		document.getElementById('mysqldumpinfile').value = file;
		document.getElementById('mysqldumpinpasswd').focus();
	}
	//网站恢复
	function restoreweb(file)
	{
		var msg = '<form name="restoreweb" action="?c=shell&a=restoreweb" method="post"	target="_blank">';
		msg += "这将导致您现在的数据丢失<input name='file' id='restorewebfile' type='hidden'><br>";
		msg += "解压密码(如无则空):<input name='password'><input name='coverfile' type='checkbox' value='1'>覆盖文件";
		msg += "<input type='submit' value='恢复'></form>";
		art.dialog({id:'id22',content:msg,title:'恢复网站文件',lock:true});
		document.getElementById('restorewebfile').value = file;
	}
	//sta div开关，on是提交，off是取消
	function addmysqldumpin(sta,file,mysqlpasswd)
	{
		if(sta=='off'){
			show_div('mysqldumpin', false);
		}
		return false;
	}
	function rmfile(file) 
	{
		if (confirm('你真得要删除该文件或目录吗?')) {
			window.location = '?c=webftp&a=rm&files[]=' + UrlEncode(file);
		}
	}
	
	function zipop_result() 
	{
		if (xmlhttp.readyState == 4) {
			try {
				if (xmlhttp.status == 200) {
					var dataArray = xmlhttp.responseXML
							.getElementsByTagName('result');
					var code = dataArray[0].getAttribute('code');
					var dlog = art.dialog({id:'id22',content:msg,title:''});
					if (code == 200) {
						// $('msg').innerHTML = '操作成功,点刷新即可!';
						refresh();
					} else {
						dlog.content("操作失败");
						dlog.time(2);
					}
				}
			} catch (e) {
				alert(xmlhttp.responseText);
			}
		}
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
	function cutfile(file) {
		ajax_open_url('?c=webftp&a=cut&files[]=' + UrlEncode(file), opfile_result);
	}
	function copyfile(file) {
		ajax_open_url('?c=webftp&a=copy&files[]=' + UrlEncode(file), opfile_result);
	}
	function selectAll() {
		var aa = document.getElementsByName('files[]');
		for ( var i = 0; i < aa.length; i++) {
			aa[i].checked = true;
		}
	}
	
	function reversSelectAll() {
		var aa = document.getElementsByName('files[]');
		for ( var i = 0; i < aa.length; i++) {
			aa[i].checked = !aa[i].checked;
		}
	}
	function operator_all(op) 
	{
		var url = get_file_selected();
		if (url == '') {
			var dlog = art.dialog({id:'id22',content:'',time:2, title:'提示',icon: 'warning'});
			dlog.content('没有选择目录或文件');
			return;
		}
		window.location = '?c=webftp&a=' + op + url;
	}
	function readonly(ro)
	{
		var url = get_file_selected();
		if (url == '') {
			var dlog = art.dialog({id:'id22',content:'',time:2,title:'提示',icon: 'warning'});
			dlog.content('没有选择目录或文件');
			dlog.time(2);
			return ;
		}
		window.location = '?c=webftp&ro=' + ro + '&a=readonly' + url;
	}
	function rmall() {
		if (confirm('确定要删除选定的文件或目录吗?')) {
			operator_all('rm');
		}
	}
	function get_file_selected() {
		var url = '';
		var aa = document.getElementsByName('files[]');
		for ( var i = 0; i < aa.length; i++) {
			if (aa[i].checked) {
				url += '&files[]=' + UrlEncode(aa[i].value);
			}
		}
		return url;
	}
	function cutall() 
	{
		var url = get_file_selected();
		if (url == '') {
			var dlog = art.dialog({id:'id22',content:'',time:2,title:'提示',icon: 'warning'});
			dlog.content('没有选择目录或文件');
			return;
		}
		ajax_open_url('?c=webftp&a=cut' + url, opfile_result);
	}
	function copyall() 
	{
		var url = get_file_selected();
		if (url == '') {
			var dlog = art.dialog({id:'id22',content:'',time:2,title:'提示',icon: 'warning'});
			dlog.content('没有选择目录或文件');
			return;
		}
		ajax_open_url('?c=webftp&a=copy' + url, opfile_result);
	}

	function parseall() {
		window.location = '?c=webftp&a=parse';
	}
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
	var xxkf_oncaptured = false;
	var xxkf_moved=false;
	function xxkf_JSFX_FloatTopDiv(layer_obj,startX,startY)
	{
	        function xxkf_ml(el)
	        {
				//var el=xxkf_obj(id);	
				el.sP=function(x,y){
					this.style.left=x+'px';
					this.style.top=y+'px';
					//alert(this.style.top);
				};			
				el.x=startX;
				el.y=startY;  				
	            return el;        
	        }
	        window.xxkf_stayTopleft=function()
	        {
					var pY=0;
					var pX=0;
					if(!xxkf_oncaptured){
						if(xxkf_ie){
							if(document.documentElement&&document.documentElement.scrollTop){
								pY=document.documentElement.scrollTop;
								pX=document.documentElement.scrollLeft;
							}else if(document.body){
								pY=document.body.scrollTop;
								pX=document.body.scrollLeft;
							}					
						}else{
							pY = pageYOffset;
							pX = pageXOffset;
						}
						var total_x=0;
						var total_y=0;
						if(xxkf_ie){
							if(document.documentElement&&document.documentElement.clientWidth){
									total_x=document.documentElement.clientWidth;
									total_y=document.documentElement.clientHeight;
								//	alert(total_y);
							/*		if(document.body){
										total_x=Math.min(total_x,document.body.clientWidth);
										total_y=Math.min(total_y,document.body.clientHeight);
									}
								*/
							}else if(document.body){
									total_x=document.body.clientWidth;
									total_y=document.body.clientHeight;
									//	alert(total_y);
							}
						}else{
							total_x=window.innerWidth-15;
							total_y=window.innerHeight;

						}
						
						for(var i=0;i<xxkf_ftlObj.length;i++){
							var x=xxkf_ftlObj[i].x;
							var y=xxkf_ftlObj[i].y;	
							if(x<0 && !xxkf_moved)
								x+=(total_x-xxkf_ftlObj[i].offsetWidth);
							if(y<0 && !xxkf_moved)
								y+=(total_y-xxkf_ftlObj[i].offsetHeight);						
							if(x<0) x=0;
							if(y<0) y=0;
						
							if(x>total_x-xxkf_ftlObj[i].offsetWidth) x=total_x-xxkf_ftlObj[i].offsetWidth;
							if(y>total_y-xxkf_ftlObj[i].offsetHeight) y=total_y-xxkf_ftlObj[i].offsetHeight;
						
							xxkf_ftlObj[i].sP(pX+x,pY+y);
						}
					}
					setTimeout("xxkf_stayTopleft()", 500);				
	        };
	        xxkf_ftlObj.push(xxkf_ml(layer_obj));
	        xxkf_stayTopleft();
	}
	
	