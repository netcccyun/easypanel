	var sortIndex = 2;
	var sortDesc = false;
	var rqs=new Array();
		
	function $(id) 
	{ 
		if (document.getElementById) { 
			return document.getElementById(id);
		} else if (document.all){
			return document.all(id);
		}
		return document.layers[id];
	}
	function showRequest()
	{
		var s = '<table class="table table-bordered"><thead>';
			s += '<tr >';
			s += '	  <th ><a href=\'javascript:sortrq(1)\'>源地址</a></th>';
			s += '	  <th ><a href=\'javascript:sortrq(2)\'>时间</a></th>';
			s += '	  <th><a href=\'javascript:sortrq(3)\'>状态</a></th>';
			s += '	  <th><a href=\'javascript:sortrq(4)\'>请求</a></th>';
			s += '	  <th><a href=\'javascript:sortrq(5)\'>网址</a></th>';
			s += '	  <th><a href=\'javascript:sortrq(6)\'>目标地址</a></th>';
			s += '	  <th><a href=\'javascript:sortrq(7)\'>referer</a></th>';
			s += '	  <th>HTTP</th>';
			s += '	  <th>Cache</th>';
			s += '</tr></thead>';
		for(var i=0;i<rqs.length;i++){
			s +='<tr>';
			for(var j=1;j<rqs[i].length;j++){
				if (j==1) {
					s += '<td >'+rqs[i][j]+'<a href=javascript:banip("'+rqs[i][j]+'");>&nbsp;禁止</a></td>';
				}else{
					s += '<td >'+rqs[i][j]+'</td>';
				}
			}
			s += '</tr>';
		}
		s += '</table>';
		$('rq').innerHTML = s;
	}
	function banip(ip)
	{
		if(ip.indexOf(":")>0){
			ip = ip.substr(0,ip.indexOf(":"));
		}
		var life_time = prompt("禁止时间:分钟(0为永久)",'0');
		if (life_time == "") {
			life_time = 60;
		}
		var url ="?c=banip&a=addBanip&ajax=1&ip=" + ip + "&life_time=" + life_time;
		jQuery.get(url,function (msg) {
			if (msg != '成功') {
				alert(msg);
			}
			window.location.reload();
		});
	}
	function sortRequest(a,b)
	{
		if (sortIndex==2) {	
			if (sortDesc) {
				return b[sortIndex] - a[sortIndex];
			} else {
				return a[sortIndex] - b[sortIndex];
			}
		}
		if(sortDesc){
			return b[sortIndex].localeCompare(a[sortIndex]);
		}
		return a[sortIndex].localeCompare(b[sortIndex]);
	}
	function sortrq(index)
	{	
		if(sortIndex!=index){
			sortDesc = false;
			sortIndex = index;
		} else {
			sortDesc = !sortDesc;
		}
		rqs.sort(sortRequest);
		showRequest();
	}