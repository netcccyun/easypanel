<?php
needRole('vhost');
class SslControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		load_lib('pub:ssl');
		$this->access = new Access(getRole('vhost'));
	}

	public function sslForm()
	{
		$vhost = getRole('vhost');
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user) || !isset($user['port']) || strpos($user['port'], 's')===false) {
			exit("<script language='javascript'>alert('您的账号不支持设置SSL证书');history.go(-1);</script>");
		}
		change_to_user($user['uid'], $user['gid']);

		$certificate = EpSsl::readFile($user['doc_root'], ep_str($user['certificate']));
		$certificate_key = EpSsl::readFile($user['doc_root'], ep_str($user['certificate_key']));
		$this->assignCertificate($certificate, $certificate_key);
		$this->_tpl->assign('ssl_configured', !empty($user['certificate']) || !empty($user['certificate_key']));

		$ssl = apicall('vhost', 'check_ssl', array($vhost));
		$this->_tpl->assign('ssl', $ssl);
		$this->_tpl->assign('http3', isset($user['http3']) ? intval($user['http3']) : 0);
		if($ssl){
			$this->_tpl->assign('http2', $user['http2']);
		}

		$find_result = $this->access->findChain('BEGIN', '!ssl_rewrite');
		if ($find_result) {
			if($ssl==0){
				$this->access->delChainByName('BEGIN', '!ssl_rewrite');
			}else{
				$this->_tpl->assign('ssl_rewrite', 1);
			}
		}

		change_to_super();
		$this->_tpl->assign('hsts', $this->hstsEnabled() ? 1 : 0);
		$this->_tpl->assign('support_http3', apicall('nodes', 'supportsHttp3', array('localhost')));
		return $this->_tpl->fetch('ssl.html');
	}

	public function ssl()
	{
		$certificate = ep_str(isset($_REQUEST['certificate']) ? $_REQUEST['certificate'] : '');
		$certificate_key = ep_str(isset($_REQUEST['certificate_key']) ? $_REQUEST['certificate_key'] : '');
		$vhost = getRole('vhost');
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user) || strpos(ep_str($user['port']), 's') === false) {
			$this->sslMessage('您的账号不支持设置SSL证书');
		}
		$remove = empty($certificate) && empty($certificate_key);

		if (!empty($certificate) && !empty($certificate_key)){
			$check = $this->check_cert($certificate, $certificate_key);
			if($check !== true){
				exit("<script language='javascript'>alert('".ep_js_str($check)."');history.go(-1);</script>");
			}
		}

		if ($remove) {
			$arr = array('certificate' => null, 'certificate_key' => null);
			if (!daocall('vhost', 'updateVhost', array($vhost, $arr))) {
				$this->sslMessage('证书配置保存失败');
			}
		}
		elseif (!$user['certificate'] || !$user['certificate_key']) {
			$user['certificate'] = 'ssl.crt';
			$user['certificate_key'] = 'ssl.key';
			$arr = array('certificate' => $user['certificate'], 'certificate_key' => $user['certificate_key']);
			daocall('vhost', 'updateVhost', array($vhost, $arr));
		}

		change_to_user($user['uid'], $user['gid']);
		$crt_file = $user['doc_root'] . '/' . $user['certificate'];
		$key_file = $user['doc_root'] . '/' . $user['certificate_key'];

		if (is_link($crt_file)) {
			unlink($crt_file);
		}

		if (is_link($key_file)) {
			unlink($key_file);
		}

		if (empty($certificate) && empty($certificate_key)){
			@unlink($crt_file);
			@unlink($key_file);
		}else{

			$fp = @fopen($crt_file, 'wb');
			$fp2 = @fopen($key_file, 'wb');

			if ($fp) {
				fwrite($fp, $certificate);
				fclose($fp);
			}

			if ($fp2) {
				fwrite($fp2, $certificate_key);
				fclose($fp2);
			}

			apicall('vhost', 'setSystemFile', array(
		$vhost,
		$user['doc_root'],
		array($user['certificate'], $user['certificate_key'])
		));
		}
		change_to_super();
		if ($remove && $this->access->findChain('BEGIN', '!ssl_rewrite')) {
			if (!$this->access->delChainByName('BEGIN', '!ssl_rewrite')) {
				$this->sslMessage('证书已移除，但关闭HTTP跳转HTTPS失败，请重试');
			}
		}
		if ($remove && !$this->setHsts(false)) {
			$this->sslMessage('证书已移除，但关闭HSTS失败，请重试');
		}
		if (!apicall('vhost', 'noticeChange', array('localhost', $vhost))) {
			$this->sslMessage('配置已保存，但加载失败，请重试');
		}
		notice_cdn_changed();
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
	}

	public function sslRewrite()
	{
		$find_result = $this->access->findChain('BEGIN', '!ssl_rewrite');
		$result = false;
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr = array();
				$arr['action'] = 'continue';
				$arr['name'] = '!ssl_rewrite';
				$models = array();
				$models['mark_url_rewrite'] = array('url' => '^http://(.*)$', 'dst' => 'https://$1', 'nc' => '1', 'code' => '301');
				$result = $this->access->addChain('BEGIN', $arr, $models);
				break;
			}
		case 2:
			if ($find_result != null) {
				$result = $this->access->delChainByName('BEGIN', '!ssl_rewrite');
				break;
			}
		default:
			break;
		}
		if($result){
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}else{
			exit('失败');
		}
	}

	private function domainContext()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$vhost = getRole('vhost');
		if ($domain === '' || ep_safe_name($domain) !== $domain || strpos($domain, ';') !== false || strpos($domain, '|') !== false) {
			$this->sslMessage('域名不合法');
		}
		// getInfo's optional type filter does not filter type=0; select domains explicitly.
		$list = daocall('vhostinfo', 'getDomain', array($vhost));
		$info = false;
		foreach (ep_iter($list) as $row) {
			if ($row['name'] === $domain) {
				$info = $row;
				break;
			}
		}
		if (!$info) {
			$this->sslMessage('域名不存在');
		}
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user) || strpos(ep_str($user['port']), 's') === false) {
			$this->sslMessage('您的账号不支持设置SSL证书');
		}
		$binding = EpSsl::splitBinding($info['value']);
		if (!empty($user['cdn']) && strpos($binding['dir'], 'proto=tcp') !== false) {
			$this->sslMessage('TCP回源协议的不支持设置SSL证书');
		}
		return array($vhost, $domain, $user, $binding);
	}

	private function assignCertificate($certificate, $key, $domain = null)
	{
		$this->_tpl->assign('certificate', $certificate);
		$this->_tpl->assign('certificate_key', $key);
		$this->_tpl->assign('certificate_info', EpSsl::certificateInfo($certificate, null, $domain));
	}

	private function sslMessage($message)
	{
		exit("<script language='javascript'>alert('" . ep_js_str($message) . "');history.go(-1);</script>");
	}

	public function domainSslForm()
	{
		list($vhost, $domain, $user, $binding) = $this->domainContext();
		$this->_tpl->assign('domain', $domain);
		$certificate = '';
		$key = '';
		change_to_user($user['uid'], $user['gid']);
		if ($binding['ssl']) {
			$certificate = EpSsl::readFile($user['doc_root'], $binding['certificate']);
			$key = EpSsl::readFile($user['doc_root'], $binding['key']);
		}
		change_to_super();
		$this->assignCertificate($certificate, $key, $domain);
		$this->_tpl->assign('ssl_configured', $binding['ssl']);
		$ssl = $binding['ssl'] && $certificate !== '' && $key !== '' && $this->check_cert($certificate, $key) === true;
		$this->_tpl->assign('ssl', $ssl ? 1 : 0);
		$this->_tpl->assign('http2', isset($user['http2']) ? intval($user['http2']) : 0);
		$this->_tpl->assign('http3', isset($user['http3']) ? intval($user['http3']) : 0);
		$table_name = '!ssl_rewrite_' . substr(md5($domain), 0, 6);
		$this->_tpl->assign('ssl_rewrite', $this->access->findChain('BEGIN', $table_name) ? 1 : 0);
		$this->_tpl->assign('hsts', $this->hstsEnabled($domain) ? 1 : 0);
		$this->_tpl->assign('support_http3', apicall('nodes', 'supportsHttp3', array('localhost')));
		return $this->_tpl->fetch('domainSsl.html');
	}

	public function domainSsl()
	{
		list($vhost, $domain, $user, $binding) = $this->domainContext();
		$certificate = trim(ep_request('certificate'));
		$key = trim(ep_request('certificate_key'));
		$remove = $certificate === '' && $key === '';
		if (!$remove) {
			$check = $this->check_cert($certificate, $key);
			if ($check !== true) {
				$this->sslMessage($check);
			}
			change_to_user($user['uid'], $user['gid']);
			$saved = EpSsl::writePair($user['doc_root'], $domain . '.crt', $domain . '.key', $certificate, $key);
			if ($saved) {
				apicall('vhost', 'setSystemFile', array($vhost, $user['doc_root'], array($domain . '.crt', $domain . '.key')));
			}
			change_to_super();
			if (!$saved) {
				$this->sslMessage('证书文件保存失败，请检查目录权限');
			}
		}
		// Keep the local directory or the complete CDN upstream configuration intact.
		$value = $binding['dir'];
		if (!$remove) {
			$value .= ';' . $domain . '.crt|' . $domain . '.key';
		}
		if (!daocall('vhostinfo', 'updateInfo', array($vhost, $domain, array('value' => $value), 0))) {
			$this->sslMessage('证书配置保存失败');
		}
		// Force a reload even when renewing files under the same names.
		if (!apicall('vhost', 'noticeChange', array('localhost', $vhost))) {
			$this->sslMessage('配置已保存，但加载失败，请检查证书文件后重试');
		}
		if ($remove) {
			change_to_user($user['uid'], $user['gid']);
			@unlink($user['doc_root'] . '/' . $domain . '.crt');
			@unlink($user['doc_root'] . '/' . $domain . '.key');
			change_to_super();
			$table_name = '!ssl_rewrite_' . substr(md5($domain), 0, 6);
			if ($this->access->findChain('BEGIN', $table_name)) {
				$this->access->delChainByName('BEGIN', $table_name);
			}
			if (!$this->setHsts(false, $domain)) {
				$this->sslMessage('证书已移除，但关闭HSTS失败，请重试');
			}
		}
		notice_cdn_changed();
		$this->sslMessage('保存成功');
	}

	public function domainSslRewrite()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit('域名不存在');
		}
		$table_name = '!ssl_rewrite_'.substr(md5($domain),0,6);
		$find_result = $this->access->findChain('BEGIN', $table_name);
		$result = false;
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr['action'] = 'continue';
				$arr['name'] = $table_name;
				$models['acl_wide_host'] = array('v' => $domain.'|');
				$models['mark_url_rewrite'] = array('url' => '^http://(.*)$', 'dst' => 'https://$1', 'nc' => '1', 'code' => '301');
				$result = $this->access->addChain('BEGIN', $arr, $models);
				break;
			}
		case 2:
			if ($find_result != null) {
				$result = $this->access->delChainByName('BEGIN', $table_name);
				break;
			}
		default:
			break;
		}
		if($result){
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}else{
			exit('失败');
		}
	}

	private function hstsChainName($domain = '')
	{
		return $domain === '' ? '!ssl_hsts' : '!ssl_hsts_' . substr(md5($domain), 0, 6);
	}

	private function hstsEnabled($domain = '')
	{
		$access = new Access(getRole('vhost'), 'request');
		return (bool) $access->findChain('BEGIN', $this->hstsChainName($domain));
	}

	private function setHsts($enabled, $domain = '')
	{
		$access = new Access(getRole('vhost'), 'request');
		$name = $this->hstsChainName($domain);
		$exists = $access->findChain('BEGIN', $name);
		if (!$enabled) {
			return !$exists || $access->delChainByName('BEGIN', $name);
		}
		if ($exists) {
			return true;
		}
		$models = array();
		if ($domain !== '') {
			$models['acl_wide_host'] = array('v' => $domain . '|');
		}
		// WHM form names prefix control-action modules with "mark_".
		$models['mark_add_response_header'] = array('attr' => 'Strict-Transport-Security', 'val' => 'max-age=31536000');
		return $access->addChain('BEGIN', array('action' => 'continue', 'name' => $name), $models);
	}

	public function hsts()
	{
		$status = ep_request('status');
		if ($status !== '0' && $status !== '1') {
			exit('参数错误');
		}
		$domain = strtolower(trim(ep_request('domain')));
		if ($domain !== '') {
			// Check ownership and reject TCP upstreams just like domain SSL uploads.
			$this->domainContext();
		} else {
			$user = daocall('vhost', 'getVhost', array(getRole('vhost')));
			if (!$user || ($status === '1' && strpos(ep_str($user['port']), 's') === false)) {
				exit('您的账号不支持设置SSL证书');
			}
		}
		if (!$this->setHsts($status === '1', $domain)) {
			exit('失败');
		}
		notice_cdn_changed();
		exit('成功');
	}

	public function http2()
	{
		$status = intval($_REQUEST['status']);

		$vhost = getRole('vhost');
		$arr = array('http2' => $status);
		$result = daocall('vhost', 'updateVhost', array($vhost, $arr));

		if($result){
			apicall('vhost', 'noticeChange', array('localhost', $vhost));
			notice_cdn_changed();
			exit('成功');
		}else{
			exit('失败');
		}
	}

	public function http3()
	{
		$status = ep_request('status');
		if ($status !== '0' && $status !== '1') {
			exit('参数错误');
		}
		if ($status === '1' && !apicall('nodes', 'supportsHttp3', array('localhost'))) {
			exit('当前服务器不支持HTTP3或无法获取HTTP3支持状态');
		}
		$vhost = getRole('vhost');
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!$user || ($status === '1' && strpos(ep_str($user['port']), 's') === false)) {
			exit('您的账号不支持设置SSL证书');
		}
		$result = daocall('vhost', 'updateVhost', array($vhost, array('http3' => intval($status))));
		if (!$result) {
			exit('失败');
		}
		if (!apicall('vhost', 'noticeChange', array('localhost', $vhost))) {
			// Keep the saved switch consistent with the running configuration.
			daocall('vhost', 'updateVhost', array($vhost, array('http3' => isset($user['http3']) ? intval($user['http3']) : 0)));
			apicall('vhost', 'noticeChange', array('localhost', $vhost));
			exit('配置加载失败，请重试');
		}
		notice_cdn_changed();
		exit('成功');
	}

	private function check_cert($cert, $key){
		if(!@openssl_x509_read($cert)) return 'SSL证书填写错误，请检查！';
		if(!@openssl_get_privatekey($key)) return 'SSL证书密钥填写错误，请检查！';
		if(!@openssl_x509_check_private_key($cert, $key)) return 'SSL证书与密钥不匹配！';
		return true;
	}
}
