<?php
needRole('vhost');
define(VHOST_INFO_ENV_TYPE, 100);
class TplenvControl extends Control
{
	public function index()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$this->assign('env', apicall('tplenv', 'getEnv', array($user['templete'], $user['subtemplete'])));
		$info = daocall('vhostinfo', 'getInfo', array($vhost, VHOST_INFO_ENV_TYPE));
		$i = 0;

		while ($i < count($info)) {
			$val[$info[$i]['name']] = $info[$i]['value'];
			++$i;
		}

		$this->assign('val', $val);
		return $this->fetch('tplenv.html');
	}

	public function setEnv()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$ret = apicall('tplenv', 'setEnv', array($vhost, $user['templete'], $user['subtemplete'], $_REQUEST['name'], $_REQUEST[$_REQUEST['name']]));

		if ($ret) {
			$this->assign('msg', '设置成功');
		}
		else {
			$this->assign('msg', '设置失败');
		}

		return $this->index();
	}
}

?>