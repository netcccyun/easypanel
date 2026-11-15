<?php
class SsoControl extends Control
{
	public function hello()
	{
		session_unset();
		$base_passwd = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_0123456789';
		$base_len = strlen($base_passwd);
		$len = 16;
		$sess_key = '';
		$i = 0;

		while ($i < $len) {
			$sess_key .= $base_passwd[function_exists('random_int') ? random_int(0, $base_len - 1) : mt_rand(0, $base_len - 1)];
			++$i;
		}

		$url = ep_safe_header_url(isset($_REQUEST['url']) ? $_REQUEST['url'] : '');

		if ($url === '') {
			exit('invalid url');
		}

		$url = $url . '&r=' . $sess_key;
		$_SESSION['sess_key'] = $sess_key;
		// 记录签发入口：管理端和用户端共用一个会话，一个入口签发的随机串不能拿到另一个入口登录
		$_SESSION['sess_key_role'] = 'admin';
		header('Location: ' . $url);
		exit();
	}

	public function login()
	{
		if (empty($_SESSION['sess_key']) || !isset($_SESSION['sess_key_role']) || $_SESSION['sess_key_role'] !== 'admin') {
			exit('error,sess_key is empty');
		}

		// 随机串只能使用一次，无论本次验证是否通过
		$sess_key = $_SESSION['sess_key'];
		unset($_SESSION['sess_key'], $_SESSION['sess_key_role']);

		$name = ep_request('name');
		$skey = daocall('setting', 'get', array('skey'));

		if (!$skey) {
			exit('skey error');
		}

		$str = ep_request('r') . $name . $sess_key . $skey;
		$md5str = md5($str);
		$sign = ep_request('s');
		if (ep_hash_equals(strtolower($md5str), strtolower($sign)) && $sign != '') {
			registerRole('admin', 'admin');
			header('Location: index.php');
			exit();
			return NULL;
		}

		exit('login failed');
	}
}
