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
		$vhost = getRole('vhost');
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (strpos($user['port'], 's')===false) {
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
		return $this->_tpl->fetch('ssl.html');
	}

	public function ssl()
	{
		$certificate = $_REQUEST['certificate'];
		$certificate_key = $_REQUEST['certificate_key'];
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];

		if (!empty($certificate) && !empty($certificate_key)){
			$check = $this->check_cert($certificate, $certificate_key);
			if($check !== true){
				exit("<script language='javascript'>alert('{$check}');history.go(-1);</script>");
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
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
	}

	public function sslRewrite()
	{
		$find_result = $this->access->findChain('BEGIN', '!ssl_rewrite');
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			if ($find_result == false) {
				$arr['action'] = 'continue';
				$arr['name'] = '!ssl_rewrite';
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

	public function domainSslForm()
	{
		$domain = strtolower(trim($_GET['domain']));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit("<script language='javascript'>alert('域名不存在');history.go(-1);</script>");
		}
		$info = $info[0];
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (strpos($user['port'], 's')===false) {
			exit("<script language='javascript'>alert('您的账号不支持设置SSL证书');history.go(-1);</script>");
		}
		if ($user['cdn']==0) {
			exit("<script language='javascript'>alert('虚拟主机不支持设置单域名SSL证书');history.go(-1);</script>");
		}
		if(strpos($info['value'], 'proto=tcp')){
			exit("<script language='javascript'>alert('TCP回源协议的不支持设置SSL证书');history.go(-1);</script>");
		}
		$this->_tpl->assign('domain', $domain);

		$ssl = 0;

		if (strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';') && strpos($info['value'],'.crt') && strpos($info['value'],'.key')){
			$ssl = 1;

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
				} else {
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
				} else {
					$ssl = 0;
				}
			}
		}

		$this->_tpl->assign('ssl', $ssl);
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

		return $this->_tpl->fetch('domainSsl.html');
	}

	public function domainSsl()
	{
		$domain = strtolower(trim($_POST['domain']));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit("<script language='javascript'>alert('域名不存在');history.go(-1);</script>");
		}
		$info = $info[0];
		$certificate = $_REQUEST['certificate'];
		$certificate_key = $_REQUEST['certificate_key'];
		$user = $_SESSION['user'][$vhost];

		if (!empty($certificate) && !empty($certificate_key)){
			$check = $this->check_cert($certificate, $certificate_key);
			if($check !== true){
				exit("<script language='javascript'>alert('{$check}');history.go(-1);</script>");
			}
		}
		
		if (empty($certificate) && empty($certificate_key) && strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';') && strpos($info['value'],'.crt') && strpos($info['value'],'.key')){
			$temp = explode(';',$info['value']);
			$arr['value'] = $temp[0];
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif (strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';')===false && strpos($info['value'],'.crt')===false && strpos($info['value'],'.key')===false) {
			$arr['value'] = $info['value'] . ';' . $domain . '.crt|' . $domain . '.key';
			apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}
		elseif (strncasecmp($info['value'], 'server://', 9) != 0 && strncasecmp($info['value'], 'http://', 7) == 0) {
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
		array($info['name'] . '.crt', $info['name'] . '.key')
		));
		}
		change_to_super();
		apicall('vhost', 'noticeChange', array('localhost', $vhost));
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
	}

	public function domainSslRewrite()
	{
		$domain = strtolower(trim($_POST['domain']));
		$vhost = getRole('vhost');
		$info = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if(!$info){
			exit('域名不存在');
		}
		$table_name = '!ssl_rewrite_'.substr(md5($domain),0,6);
		$find_result = $this->access->findChain('BEGIN', $table_name);
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

	public function http2()
	{
		$status = intval($_REQUEST['status']);

		$vhost = getRole('vhost');
		$arr = array('http2' => $status);
		$result = daocall('vhost', 'updateVhost', array($vhost, $arr));

		if($result){
			apicall('vhost', 'noticeChange', array('localhost', $vhost));
			exit('成功');
		}else{
			exit('失败');
		}
	}

	private function check_cert($cert, $key){
		if(!openssl_x509_read($cert)) return 'SSL证书填写错误，请检查！';
		if(!openssl_get_privatekey($key)) return 'SSL证书密钥填写错误，请检查！';
		if(!openssl_x509_check_private_key($cert, $key)) return 'SSL证书与密钥不匹配！';
		return true;
	}
}

?>