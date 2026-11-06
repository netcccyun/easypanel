function ssl_disable(form, scope)
{
	var message = scope === 'domain'
		? '确定关闭该域名的独立SSL证书？'
		: '确定关闭当前账号的默认SSL证书？';
	if (!window.confirm(message)) {
		return false;
	}
	form.elements['certificate'].value = '';
	form.elements['certificate_key'].value = '';
	form.submit();
	return false;
}

var ssl_switch_prev;

function ssl_switch_dirty()
{
	var nodes = document.querySelectorAll('textarea[name=certificate], textarea[name=certificate_key]');
	for (var i = 0; i < nodes.length; i++) {
		if (nodes[i].value !== nodes[i].defaultValue) return true;
	}
	return false;
}

function ssl_switch_go()
{
	var select = document.getElementById('ssl_switch');
	if (ssl_switch_dirty() && !window.confirm('有未提交的证书内容，切换将丢失，是否继续？')) {
		if (select) select.value = ssl_switch_prev;
		return;
	}
	var domain = select ? select.value : '';
	location.href = domain
		? '?c=ssl&a=domainSslForm&domain=' + encodeURIComponent(domain)
		: '?c=ssl&a=sslForm';
}

(function () {
	var sel = document.getElementById('ssl_switch');
	if (!sel) return;
	ssl_switch_prev = sel.value;
	sel.onchange = ssl_switch_go;
})();

function hsts_checkon(val)
{
	var data = 'status=' + val;
	var domain = document.getElementById('domain');
	if (domain && domain.value) {
		data += '&domain=' + encodeURIComponent(domain.value);
	}
	functoin_checkon('?c=ssl&a=hsts', data);
}
