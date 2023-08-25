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
			$sess_key .= $base_passwd[rand() % $base_len];
			++$i;
		}

		$url = $_REQUEST['url'] . '&r=' . $sess_key;
		$_SESSION['sess_key'] = $sess_key;
		header('Location: ' . $url);
		exit();
	}

	public function login()
	{
		if ($_SESSION['sess_key'] == '') {
			exit('error,sess_key is empty');
		}

		$name = $_REQUEST['name'];
		$skey = daocall('setting', 'get', array('skey'));

		if (!$skey) {
			exit('skey error');
		}

		$str = $_REQUEST['r'] . $name . $_SESSION['sess_key'] . $skey;
		$md5str = md5($str);
		if (strtolower($md5str) === $_REQUEST['s'] && $_REQUEST['s'] != '') {
			registerRole('admin', 'admin');
			header('Location: ?c=vhost&a=showVhost&name=' . $name);
			exit();
			return NULL;
		}

		exit('login failed');
	}
}

?>