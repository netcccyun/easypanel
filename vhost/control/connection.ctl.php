<?php
needRole('vhost');
class ConnectionControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'), null);
	}

	public function connectionFrom()
	{
		$check_result = apicall('access', 'checkAccess', array('ent', '2.9.6'));

		if ($check_result !== true) {
			return $this->showMsg($check_result);
		}

		$result = $this->connectionGet();

		if (!$result) {
			return $this->showMsg('获取连接信息失败，请联系管理员');
		}

		$con = $result->get('connection');
		$this->_tpl->assign('con', $con);
		return $this->_tpl->fetch('connection/connectionfrom.html');
	}

	public function connectionGet()
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));

		if (!$whm) {
			exit('make whm error');
		}

		$whmCall = new WhmCall('core.whm', 'get_connection');
		$whmCall->addParam('vh', getRole('vhost'));
		return $whm->call($whmCall);
	}

	private function showMsg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>