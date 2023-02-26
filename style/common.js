var xmlhttp=false;
function xxkf_obj(id) 
{ 
		if (document.getElementById) 
			return document.getElementById(id); 
		else if (document.all)
			return document.all(id);
		return document.layers[id];
}
function $(id)
{
	return xxkf_obj(id);
}
function create_xmlhttp(){
	var obj=false;
	/*@cc_on @*/
	/*@if (@_jscript_version >= 5)
	// JScript gives us Conditional compilation, we can cope with old IE versions.
	// and security blocked creation of the objects.
	 try {
	  obj = new ActiveXObject("Msxml2.XMLHTTP");
	 } catch (e) {
	  try {
	   obj = new ActiveXObject("Microsoft.XMLHTTP");
	  } catch (E) {
	   obj = false;
	  }
	 }
	@end @*/
	if (!obj && typeof XMLHttpRequest!='undefined') {
			try {
					obj = new XMLHttpRequest();
			} catch (e) {
					obj=false;
			}
	}
	if (!obj && window.createRequest) {
			try {
					obj = window.createRequest();
			} catch (e) {
					obj=false;
			}
	}
	return obj;
}
function ajax_open_url(url,result_func)
{
	xmlhttp=create_xmlhttp();	
	xmlhttp.open("GET",url,true);
	xmlhttp.onreadystatechange=result_func;
	xmlhttp.send(null);
}
function ajax_open_url2(url,result_func)
{
	xmlhttp2=create_xmlhttp();	
	xmlhttp2.open("GET",url,true);
	xmlhttp2.onreadystatechange=function (){
		result_func(xmlhttp2);
	};
	xmlhttp2.send(null);
}
function show_div(div_name,flag)
{
	var el=xxkf_obj(div_name);
	if(flag)
		el.style.display='';
	else
		el.style.display='none';
}