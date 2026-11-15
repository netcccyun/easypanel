<?php
class SsoControl extends Control
{
	public function hello()
	{
		header('p3p: CP="CAO DSP COR CUR ADM DEV TAI PSA PSD IVAi IVDi CONi TELo OTPi OUR DELi SAMi OTRi UNRi PUBi IND PHY ONL UNI PUR FIN COM NAV INT DEM CNT STA POL HEA PRE GOV"');
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
		$_SESSION['sess_key_role'] = 'vhost';
		header('Cache-Control: no-cache,no-store');
		header('Location: ' . ep_safe_header_url($url));
		exit();
	}

	public function login()
	{
		if (empty($_SESSION['sess_key']) || !isset($_SESSION['sess_key_role']) || $_SESSION['sess_key_role'] !== 'vhost') {
			exit('error,sess_key is empty');
		}

		// 随机串只能使用一次，无论本次验证是否通过
		$sess_key = $_SESSION['sess_key'];
		unset($_SESSION['sess_key'], $_SESSION['sess_key_role']);

		$name = trim(ep_request('name'));

		if (!$name) {
			exit('param error');
		}

		$skey = daocall('setting', 'get', array('skey'));

		if (!$skey) {
			exit('skey error');
		}

		$str = ep_request('r') . $name . $sess_key . $skey;
		$md5str = md5($str);
		$sign = ep_request('s');
		if (ep_hash_equals(strtolower($md5str), strtolower($sign)) && $sign != '') {
			registerRole('vhost', $name);
			$_SESSION['login_from'] = 'vhost';
			header('Location: index.php');
			exit();
		}

		exit('login failed');
	}
}
