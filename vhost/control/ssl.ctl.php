<?php
needRole('vhost');
load_api('acme');
class SslControl extends Control
{
	private $access;

	// 证书统一存放子目录（doc_root 相对），与 AcmeAPI::CERT_DIR 一致
	const CERT_SUBDIR = 'cert/';

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function sslForm()
	{
		$domain = strtolower(trim(ep_request('domain')));
		if ($domain !== '') {
			return $this->domainSslForm();
		}
		$vhost = getRole('vhost');
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user) || !isset($user['port']) || strpos($user['port'], 's')===false) {
			exit("<script language='javascript'>alert('您的账号不支持设置SSL证书');history.go(-1);</script>");
		}
		change_to_user($user['uid'], $user['gid']);

		if ($user['certificate']) {
			$file = $user['doc_root'] . '/' . $user['certificate'];

			if (is_link($file)) {
				unlink($file);
			}
			else {
				$fp = @fopen($file, 'rb');

				if ($fp) {
					$certificate = fread($fp, 1024000);
					fclose($fp);
					$this->_tpl->assign('certificate', $certificate);
				}
			}
		}
		else {
			// 已关闭但文件保留：回显内容，重新开启时直接提交即可
			$certificate = AcmeAPI::readCertFile($user['doc_root'], 'ssl.crt');
			if ($certificate !== false) {
				$this->_tpl->assign('certificate', $certificate);
			}
		}

		if ($user['certificate_key']) {
			$keyfile = $user['doc_root'] . '/' . $user['certificate_key'];

			if (is_link($keyfile)) {
				unlink($keyfile);
			}
			else {
				$fp = @fopen($keyfile, 'rb');

				if ($fp) {
					$certificate_key = fread($fp, 1024000);
					fclose($fp);
					$this->_tpl->assign('certificate_key', $certificate_key);
				}
			}
		}
		else {
			$certificate_key = AcmeAPI::readCertFile($user['doc_root'], 'ssl.key');
			if ($certificate_key !== false) {
				$this->_tpl->assign('certificate_key', $certificate_key);
			}
		}

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

		$find_result = $this->access->findChain('BEGIN', '!hsts');
		if ($find_result) {
			if($ssl==0){
				$this->access->delChainByName('BEGIN', '!hsts');
			}else{
				$this->_tpl->assign('hsts', 1);
			}
		}

		change_to_super();
		$this->assignDomainOptions($vhost);
		return $this->_tpl->fetch('ssl.html');
	}

	/**
	 * 域名下拉选项（sslForm/domainSslForm 按域名切换配置用）
	 */
	private function assignDomainOptions($vhost)
	{
		$list = daocall('vhostinfo', 'getDomain', array($vhost));
		$domains = array();
		foreach (ep_iter($list) as $d) {
			$domains[] = $d['name'];
		}
		$this->_tpl->assign('domains', $domains);
	}

	public function ssl()
	{
		$certificate = ep_str(isset($_REQUEST['certificate']) ? $_REQUEST['certificate'] : '');
		$certificate_key = ep_str(isset($_REQUEST['certificate_key']) ? $_REQUEST['certificate_key'] : '');
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];

		if (!empty($certificate) && !empty($certificate_key)){
			$check = $this->check_cert($certificate, $certificate_key);
			if($check !== true){
				exit("<script language='javascript'>alert('".ep_js_str($check)."');history.go(-1);</script>");
			}
		}

		// 统一使用 cert/ 子目录存放证书；旧版散放根目录的证书在保存时规范化（并清理旧文件）
		$crt_name = self::CERT_SUBDIR . 'ssl.crt';
		$key_name = self::CERT_SUBDIR . 'ssl.key';
		if (empty($certificate) && empty($certificate_key) && ($user['certificate'] || $user['certificate_key'])){
			$arr = array('certificate' => null, 'certificate_key' => null);
			daocall('vhost', 'updateVhost', array($vhost, $arr));
		}
		elseif (!$user['certificate'] || !$user['certificate_key'] || strpos($user['certificate'], self::CERT_SUBDIR) !== 0) {
			$user['certificate'] = $crt_name;
			$user['certificate_key'] = $key_name;
			$arr = array('certificate' => $user['certificate'], 'certificate_key' => $user['certificate_key']);
			daocall('vhost', 'updateVhost', array($vhost, $arr));
		}

		change_to_user($user['uid'], $user['gid']);

		if (empty($certificate) && empty($certificate_key)){
			// 关闭=停用：清空字段引用，保留 cert/ 下文件，重新开启时提交回显内容即可
		}else{
			AcmeAPI::writeCertFile($user['doc_root'], basename($user['certificate']), $certificate);
			AcmeAPI::writeCertFile($user['doc_root'], basename($user['certificate_key']), $certificate_key);
			// 旧版证书文件规范化迁移后清理
			if (strpos($user['certificate'], self::CERT_SUBDIR) === 0) {
				@unlink($user['doc_root'] . '/ssl.crt');
				@unlink($user['doc_root'] . '/ssl.key');
			}

			apicall('vhost', 'setSystemFile', array(
		$vhost,
		$user['doc_root'],
		array($user['certificate'], $user['certificate_key'])
		));
		}
		change_to_super();
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
		notice_cdn_changed();
		// 跳转而非 history.go(-1)：后者从浏览器缓存恢复旧页面，状态与内容不实时
		exit("<script language='javascript'>alert('保存成功');location.href='?c=ssl&a=sslForm';</script>");
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
				if ($result) {
					$this->access->sortBeginChains();
				}
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

	public function hsts()
	{
		$find_result = $this->access->findChain('BEGIN', '!hsts');
		$result = false;
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr = array();
				$arr['action'] = 'continue';
				$arr['name'] = '!hsts';
				// ponytail: HTTP 响应中的 Strict-Transport-Security 头会被浏览器按 RFC 6797 忽略，故无条件添加；仅 max-age 固定一年，includeSubDomains 未提供
				// kangle WHM 协议要求模型键带 mark_ 前缀（KChain::to_xml 只识别 acl_/mark_ 前缀并剥掉得模块名）
				$models = array();
				$models['mark_add_response_header'] = array('attr' => 'Strict-Transport-Security', 'val' => 'max-age=31536000');
				$result = $this->access->addChain('BEGIN', $arr, $models);
				if ($result) {
					$this->access->sortBeginChains();
				}
				break;
			}
		case 2:
			if ($find_result != null) {
				$result = $this->access->delChainByName('BEGIN', '!hsts');
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

	public function domainSslForm()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit("<script language='javascript'>alert('域名不存在');history.go(-1);</script>");
		}
		$info = $info[0];
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user) || !isset($user['port']) || strpos($user['port'], 's')===false) {
			exit("<script language='javascript'>alert('您的账号不支持设置SSL证书');history.go(-1);</script>");
		}
		if(strpos($info['value'], 'proto=tcp') !== false){
			exit("<script language='javascript'>alert('TCP回源协议的不支持设置SSL证书');history.go(-1);</script>");
		}
		$this->_tpl->assign('domain', $domain);
		$this->assignDomainOptions($vhost);

		$ssl = 0;

		// 启用状态以 value 的证书后缀为准
		if (strpos($info['value'],';') !== false && strpos($info['value'],'.crt') !== false && strpos($info['value'],'.key') !== false){
			$ssl = 1;
		}

		// 证书文件存在即回显（含已关闭但未删除的情况），重新开启时直接提交即可；cert/ 优先，回退旧版根目录路径
		$certificate = AcmeAPI::readCertFile($user['doc_root'], $info['name'] . '.crt');
		if ($certificate !== false) {
			$this->_tpl->assign('certificate', $certificate);
		} else if ($ssl) {
			$ssl = 0;
		}

		$certificate_key = AcmeAPI::readCertFile($user['doc_root'], $info['name'] . '.key');
		if ($certificate_key !== false) {
			$this->_tpl->assign('certificate_key', $certificate_key);
		} else if ($ssl) {
			$ssl = 0;
		}

		$this->_tpl->assign('ssl', $ssl);
		$this->_tpl->assign('http3', isset($user['http3']) ? intval($user['http3']) : 0);
		if($ssl){
			$this->_tpl->assign('http2', $user['http2']);
		}

		$table_name = '!ssl_rewrite_'.substr(md5($domain),0,6);

		$find_result = $this->access->findChain('BEGIN', $table_name);
		if ($find_result) {
			if($ssl==0){
				$this->access->delChainByName('BEGIN', $table_name);
			}else{
				$this->_tpl->assign('ssl_rewrite', 1);
			}
		}

		$table_name = '!hsts_'.substr(md5($domain),0,6);

		$find_result = $this->access->findChain('BEGIN', $table_name);
		if ($find_result) {
			if($ssl==0){
				$this->access->delChainByName('BEGIN', $table_name);
			}else{
				$this->_tpl->assign('hsts', 1);
			}
		}

		return $this->_tpl->fetch('domainSsl.html');
	}

	public function domainSsl()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit("<script language='javascript'>alert('域名不存在');history.go(-1);</script>");
		}
		$info = $info[0];
		$certificate = ep_str(isset($_REQUEST['certificate']) ? $_REQUEST['certificate'] : '');
		$certificate_key = ep_str(isset($_REQUEST['certificate_key']) ? $_REQUEST['certificate_key'] : '');
		$user = $_SESSION['user'][$vhost];

		if (!empty($certificate) && !empty($certificate_key)){
			$check = $this->check_cert($certificate, $certificate_key);
			if($check !== true){
				exit("<script language='javascript'>alert('".ep_js_str($check)."');history.go(-1);</script>");
			}
		}
		
		// 统一使用 cert/ 子目录后缀；旧版后缀（无 cert/ 前缀）在保存时规范化
		$newSuffix = ';' . self::CERT_SUBDIR . $domain . '.crt|' . self::CERT_SUBDIR . $domain . '.key';
		$p = strpos($info['value'], ';');
		if (empty($certificate) && empty($certificate_key) && $p !== false && strpos($info['value'],'.crt') !== false && strpos($info['value'],'.key') !== false){
			$arr['value'] = substr($info['value'], 0, $p);
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
			// 关闭证书后该域名可能无法 https，同步清理域名级的HTTP跳转与HSTS，避免站点不可访问
			$this->access->delChainByName('BEGIN', '!ssl_rewrite_'.substr(md5($domain),0,6));
			$this->access->delChainByName('BEGIN', '!hsts_'.substr(md5($domain),0,6));
		}
		elseif (strncasecmp($info['value'], 'http://', 7) != 0 && strncasecmp($info['value'], 'https://', 8) != 0 && $p === false) {
			$arr['value'] = $info['value'] . $newSuffix;
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif (strncasecmp($info['value'], 'http://', 7) == 0 && $p === false) {
			$ip = substr($info['value'], 7);
			$ip = trim($ip, '/');
			$arr['value'] = 'server://proto=http/nodes=' . $ip . ':80:0:1' . $newSuffix;
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif ($p !== false && !empty($certificate) && strpos($info['value'],'.crt') !== false && strpos($info['value'], self::CERT_SUBDIR) === false) {
			// 旧版后缀（散放根目录）在保存时规范化到 cert/ 子目录
			$arr['value'] = substr($info['value'], 0, $p) . $newSuffix;
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
			$legacyCertCleanup = true;
		}

		change_to_user($user['uid'], $user['gid']);

		if (empty($certificate) && empty($certificate_key)){
			// 关闭=停用：保留证书文件，仅剥离 value 后缀并清理域名级跳转/HSTS，重新开启时提交回显内容即可
		}else{
			AcmeAPI::writeCertFile($user['doc_root'], $info['name'] . '.crt', $certificate);
			AcmeAPI::writeCertFile($user['doc_root'], $info['name'] . '.key', $certificate_key);

			apicall('vhost', 'setSystemFile', array(
		$vhost,
		$user['doc_root'],
		array(self::CERT_SUBDIR . $info['name'] . '.crt', self::CERT_SUBDIR . $info['name'] . '.key')
		));
		}
		if (!empty($legacyCertCleanup)) {
			// 内容已写入 cert/，清理旧版散放在根目录的证书文件
			@unlink($user['doc_root'] . '/' . $info['name'] . '.crt');
			@unlink($user['doc_root'] . '/' . $info['name'] . '.key');
		}
		change_to_super();
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
		notice_cdn_changed();
		// 跳转而非 history.go(-1)：后者从浏览器缓存恢复旧页面，状态与内容不实时
		exit("<script language='javascript'>alert('保存成功');location.href='?c=ssl&a=sslForm&domain=" . rawurlencode($domain) . "';</script>");
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
				if ($result) {
					$this->access->sortBeginChains();
				}
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

	public function domainHsts()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit('域名不存在');
		}
		$table_name = '!hsts_'.substr(md5($domain),0,6);
		$find_result = $this->access->findChain('BEGIN', $table_name);
		$result = false;
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr['action'] = 'continue';
				$arr['name'] = $table_name;
				// ponytail: HTTP 响应中的 HSTS 头会被浏览器按 RFC 6797 忽略，故无条件添加；仅 max-age 固定一年
				// kangle WHM 协议要求模型键带 mark_ 前缀（KChain::to_xml 只识别 acl_/mark_ 前缀并剥掉得模块名）
				$models['acl_wide_host'] = array('v' => $domain.'|');
				$models['mark_add_response_header'] = array('attr' => 'Strict-Transport-Security', 'val' => 'max-age=31536000');
				$result = $this->access->addChain('BEGIN', $arr, $models);
				if ($result) {
					$this->access->sortBeginChains();
				}
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
		if(!openssl_x509_read($cert)) return 'SSL证书填写错误，请检查！';
		if(!openssl_get_privatekey($key)) return 'SSL证书密钥填写错误，请检查！';
		if(!openssl_x509_check_private_key($cert, $key)) return 'SSL证书与密钥不匹配！';
		return true;
	}
}
