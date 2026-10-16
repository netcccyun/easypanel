<?php
needRole('vhost');
class SslControl extends Control
{
	private $access;

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

		if (empty($certificate) && empty($certificate_key) && ($user['certificate'] || $user['certificate_key'])){
			$arr = array('certificate' => null, 'certificate_key' => null);
			daocall('vhost', 'updateVhost', array($vhost, $arr));
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
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
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
				$models = array();
				$models['add_response_header'] = array('attr' => 'Strict-Transport-Security', 'val' => 'max-age=31536000');
				$result = $this->access->addChain('BEGIN', $arr, $models);
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

		// 证书文件存在即回显（含已关闭但未删除的情况），重新开启时直接提交即可
		$file = $user['doc_root'] . '/' . $info['name'] . '.crt';

		if (is_link($file)) {
			unlink($file);
		}
		else {
			$fp = @fopen($file, 'rb');

			if ($fp) {
				$certificate = fread($fp, 1024000);
				fclose($fp);
				$this->_tpl->assign('certificate', $certificate);
			} else if ($ssl) {
				$ssl = 0;
			}
		}

		$keyfile = $user['doc_root'] . '/' . $info['name'] . '.key';

		if (is_link($keyfile)) {
			unlink($keyfile);
		}
		else {
			$fp = @fopen($keyfile, 'rb');

			if ($fp) {
				$certificate_key = fread($fp, 1024000);
				fclose($fp);
				$this->_tpl->assign('certificate_key', $certificate_key);
			} else if ($ssl) {
				$ssl = 0;
			}
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
		
		if (empty($certificate) && empty($certificate_key) && strpos($info['value'],';') !== false && strpos($info['value'],'.crt') !== false && strpos($info['value'],'.key') !== false){
			$temp = explode(';',$info['value']);
			$arr['value'] = $temp[0];
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
			// 关闭证书后该域名可能无法 https，同步清理域名级的HTTP跳转与HSTS，避免站点不可访问
			$this->access->delChainByName('BEGIN', '!ssl_rewrite_'.substr(md5($domain),0,6));
			$this->access->delChainByName('BEGIN', '!hsts_'.substr(md5($domain),0,6));
		}
		elseif (strncasecmp($info['value'], 'http://', 7) != 0 && strncasecmp($info['value'], 'https://', 8) != 0 && strpos($info['value'],';')===false && strpos($info['value'],'.crt')===false && strpos($info['value'],'.key')===false) {
			$arr['value'] = $info['value'] . ';' . $domain . '.crt|' . $domain . '.key';
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif (strncasecmp($info['value'], 'server://', 9) != 0 && strncasecmp($info['value'], 'http://', 7) == 0 && strpos($info['value'],';')===false) {
			$ip = substr($info['value'], 7);
			$ip = trim($ip, '/');
			$arr['value'] = 'server://proto=http/nodes=' . $ip . ':80:0:1' . ';' . $domain . '.crt|' . $domain . '.key';
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}

		change_to_user($user['uid'], $user['gid']);
		$crt_file = $user['doc_root'] . '/' . $info['name'] . '.crt';
		$key_file = $user['doc_root'] . '/' . $info['name'] . '.key';

		if (is_link($crt_file)) {
			unlink($crt_file);
		}

		if (is_link($key_file)) {
			unlink($key_file);
		}

		if (empty($certificate) && empty($certificate_key)){
			// 关闭=停用：保留证书文件，仅剥离 value 后缀并清理域名级跳转/HSTS，重新开启时提交回显内容即可
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
		array($info['name'] . '.crt', $info['name'] . '.key')
		));
		}
		change_to_super();
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
		notice_cdn_changed();
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
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
				$models['acl_wide_host'] = array('v' => $domain.'|');
				$models['add_response_header'] = array('attr' => 'Strict-Transport-Security', 'val' => 'max-age=31536000');
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
