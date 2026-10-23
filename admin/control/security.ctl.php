<?php
needRole('admin');
class SecurityControl extends Control
{
	/**
	 *2013-5-21
	 */
	public function addFrom()
	{
		if ($_REQUEST['success'] == 1) {
			$this->_tpl->assign('msg', '更新设置成功');
		}

		if (!function_exists('imageCreate')) {
			$this->_tpl->assign('msg', 'php的gd扩展未打开,不能使用图片码验证功能');
		}

		$setting = daocall('setting', 'getAll', array());
		$this->_tpl->assign('setting', $setting);
		return $this->_tpl->fetch('security/add.html');
	}

	/**
	 *2013-5-21
	 */
	public function add()
	{
		daocall('setting', 'add', array('allow_login_ip', trim(ep_request('allow_login_ip'))));
		daocall('setting', 'add', array('allow_login_time', trim(ep_request('allow_login_time'))));
		daocall('setting', 'add', array('admin_login_img', trim(ep_request('admin_login_img'))));
		daocall('setting', 'add', array('admin_login_img_sum', trim(ep_request('admin_login_img_sum'))));
		daocall('setting', 'add', array('vhost_login_img', trim(ep_request('vhost_login_img'))));
		daocall('setting', 'add', array('vhost_login_img_sum', trim(ep_request('vhost_login_img_sum'))));
		header('Location:?c=security&a=addFrom&success=1');
		exit();
	}

	public function sslForm()
	{
		load_lib('pub:ssl');
		$crtfile = $GLOBALS['safe_dir'] . 'server.crt';
		$keyfile = $GLOBALS['safe_dir'] . 'server.key';
		$crt = file_exists($crtfile) ? file_get_contents($crtfile) : '';
		$key = file_exists($keyfile) ? file_get_contents($keyfile) : '';

		$ssl = 0;
		if($crt && $key) {
			$ssl = 1;
		}
		$this->_tpl->assign('ssl', $ssl);
		$this->_tpl->assign('crt', $crt);
		$this->_tpl->assign('key', $key);
		$this->_tpl->assign('certificate_info', EpSsl::certificateInfo($crt));

		return $this->_tpl->fetch('security/ssl.html');
	}

	public function ssl()
	{
		$crt = ep_str(isset($_REQUEST['crt']) ? $_REQUEST['crt'] : '');
		$key = ep_str(isset($_REQUEST['key']) ? $_REQUEST['key'] : '');

		if(empty($crt) || empty($key)){
			exit("<script language='javascript'>alert('SSL证书不能为空');history.go(-1);</script>");
		}

		$check = $this->check_cert($crt, $key);
		if($check !== true){
			exit("<script language='javascript'>alert('".ep_js_str($check)."');history.go(-1);</script>");
		}

		$crtfile = $GLOBALS['safe_dir'] . 'server.crt';
		$keyfile = $GLOBALS['safe_dir'] . 'server.key';

		file_put_contents($crtfile, $crt);
		file_put_contents($keyfile, $key);

		apicall('nodes', 'reboot', array('localhost'));
		
		exit("<script language='javascript'>alert('保存成功');history.go(-1);</script>");
	}

	private function check_cert($cert, $key){
		if(!openssl_x509_read($cert)) return 'SSL证书填写错误，请检查！';
		if(!openssl_get_privatekey($key)) return 'SSL证书密钥填写错误，请检查！';
		if(!openssl_x509_check_private_key($cert, $key)) return 'SSL证书与密钥不匹配！';
		return true;
	}
}
