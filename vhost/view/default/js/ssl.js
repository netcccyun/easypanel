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

function hsts_checkon(val)
{
	var data = 'status=' + val;
	var domain = document.getElementById('domain');
	if (domain && domain.value) {
		data += '&domain=' + encodeURIComponent(domain.value);
	}
	functoin_checkon('?c=ssl&a=hsts', data);
}
