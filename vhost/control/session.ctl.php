<?php
class SessionControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function loginForm()
	{
		if (daocall('setting', 'get', array('vhost_login_img'))) {
			$this->_tpl->assign('img', 1);
		}

		return $this->_tpl->fetch('login.html');
	}

	public function sso()
	{
		$name = getRole('vhost');

		if ($name) {
			$sign = md5($name . $_REQUEST['r'] . $GLOBALS['skey']);
			$url = $_REQUEST['url'];

			if (strchr($url, '?')) {
				$url .= '&';
			}
			else {
				$url .= '?';
			}

			$url .= 'action=login&name=' . $name . '&s=' . $sign;
			header('Location: ' . $url);
			exit();
		}

		exit('no login');
	}

	/**
	 *2013-5-21
	 *生成验证图片
	 */
	public function initImg()
	{
		$img_height = 40;
		$setting = daocall('setting', 'getAll', array());
		$number = $setting['vhost_login_img_sum'] ? $setting['vhost_login_img_sum'] : 6;
		$img_width = $number * 25;
		$display_str = '123456789abcdefghijkmnpqrstuvwxyz';

		if ($_REQUEST['action'] == 'init') {
			$i = 0;
			$str = '';

			while ($i < $number) {
				$str .= $display_str[rand() % strlen($display_str)];
				++$i;
			}

			$_SESSION['vhost_img_number'] = $str;
			$aimg = imageCreate($img_width, $img_height);
			$i = 1;

			while ($i <= 100) {
				imageString($aimg, 1, mt_rand(1, $img_width), mt_rand(1, $img_height), '*', imageColorAllocate($aimg, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255)));
				++$i;
			}

			$i = 0;

			while ($i < $number) {
				imageString($aimg, mt_rand(3, 5), $i * $img_width / $number + mt_rand(1, 10), mt_rand(1, $img_height / 2), $_SESSION['vhost_img_number'][$i], imageColorAllocate($aimg, mt_rand(0, 100), mt_rand(0, 150), mt_rand(0, 200)));
				++$i;
			}

			Header('Content-type: image/png');
			ImagePng($aimg);
			ImageDestroy($aimg);
		}
	}

	public function login()
	{
		session_start();
		$setting = daocall('setting', 'getAll', array());

		if ($setting['vhost_login_img']) {
			if (empty($_SESSION['vhost_img_number']) || $_REQUEST['imgnumber'] != $_SESSION['vhost_img_number']) {
				$tpl = tpl::singleton();
				$tpl->assign('errormsg', '验证码错误');
				$html = $tpl->fetch('loginerror.html');
				exit($html);
			}
		}

		$user = apicall('vhost', 'checkPassword', array(filterParam($_REQUEST['username']), filterParam($_REQUEST['passwd'])));

		if (!$user) {
			$tpl = tpl::singleton();
			$tpl->assign('errormsg', '账号密码错误');
			$html = $tpl->fetch('loginerror.html');
			exit($html);
		}

		registerRole('vhost', $user['name']);
		$_SESSION['login_from'] = 'vhost';
		header('Location: index.php');
	}

	public function foot2()
	{
		$setting = daocall('setting', 'getAll', array());
		$this->assign('setting', $setting);
		return $this->_tpl->fetch('common/foot.html');
	}

	public function logout()
	{
		session_unset();
		session_destroy();
		return $this->loginForm();
	}

	public function changePasswordForm()
	{
		needRole('vhost');
		$vhost = getRole('vhost');
		$this->_tpl->assign('db_limit', $_SESSION['quota'][$vhost]['db_limit']);
		return $this->_tpl->fetch('changePassword.html');
	}

	public function changePassword()
	{
		needRole('vhost');
		$_REQUEST['passwd'] = filterParam($_REQUEST['passwd']);
		$strlen = strlen($_REQUEST['passwd']);
		if (16 < $strlen || $strlen < 4) {
			exit('失败:密码长度不对，4-16');
		}

		if (apicall('vhost', 'changePassword', array('localhost', getRole('vhost'), filterParam($_REQUEST['passwd'])))) {
			exit('修改密码成功，新密码:' . $_REQUEST['passwd']);
			return NULL;
		}

		exit('修改密码失败');
	}

	public function changeDbPassword()
	{
		needRole('vhost');
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];

		if ($user['db_quota'] == 0) {
			exit('该产品没有数据库');
			return NULL;
		}

		$_REQUEST['passwd'] = filterParam($_REQUEST['passwd']);
		$strlen = strlen($_REQUEST['passwd']);
		if ($strlen < 4 || 16 < $strlen) {
			exit('密码长度不对，长度4-16');
		}

		$db = apicall('nodes', 'makeDbProduct', array('localhost', $user['db_type']));
		if ($db && $db->password($user['db_name'], $_REQUEST['passwd'])) {
			exit('修改数据库密码成功,新密码:' . $_REQUEST['passwd']);
			return NULL;
		}

		exit('修改数据库密码失败');
	}
}

?>