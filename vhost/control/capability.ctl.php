<?php
needRole('vhost');
class CapabilityControl extends Control
{
	private $whm;

	public function __construct()
	{
		parent::__construct();
		$this->whm = apicall('nodes', 'makeWhm', array('localhost'));
	}

	public function capabilityFrom()
	{
		$check_result = apicall('access', 'checkAccess', array('ent'));

		if ($check_result !== true) {
			$this->_tpl->assign('msg', $check_result);
			return $this->_tpl->fetch('msg.html');
		}

		return $this->_tpl->fetch('capability/capabilityfrom.html');
	}

	public function capabilityGet()
	{
		$whmCall = new WhmCall('core.whm', 'get_load');
		$whmCall->addParam('vh', getRole('vhost'));
		$result = $this->whm->call($whmCall);

		if ($result->getCode() != '200') {
			exit('error');
		}

		$ret = array('speed' => (string) $result->get('speed'), 'connect' => (string) $result->get('connect'));
		exit(json_encode($ret));
	}
}

?>