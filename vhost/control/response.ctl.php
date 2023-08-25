<?php
needRole('vhost');
define(GZIPNAME, 'gzip');
class ResponseControl extends control
{
	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
	}

	public function gzipFrom()
	{
		$check_result = apicall('access', 'checkAccess', array());

		if ($check_result !== true) {
			return $this->show_msg($check_result);
		}

		return $this->_tpl->fetch('response/gzip.html');
	}

	/**
	 * ajax
	 * Enter description here ...
	 */
	public function addGzip()
	{
		$val = $gzip = $_REQUEST['gzip'] ? filterParam($_REQUEST['gzip']) : false;

		if ($gzip == false) {
			exit('增加失败');
		}

		$vhost = getRole('vhost');
		$arr['action'] = 'continue';
		$arr['name'] = GZIPNAME;
		$header = 'Content-Type';
		$models['acl_header'] = array('header' => $header, 'val' => $val, 'regex' => 1);
		$models['mark_response_flag'] = array('flagvalue' => 'gzip');
		$access = new Access($vhost, 'response');

		if (!$access->findChain('BEGIN', GZIPNAME)) {
			if ($access->addChain('BEGIN', $arr, $models)) {
				apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
				exit('增加成功');
			}

			exit('增加失败');
		}

		exit('该功能已经存在，不可重复添加');
	}

	/**
	 * ajax
	 * Enter description here ...
	 */
	public function delGzip()
	{
		$vhost = getRole('vhost');
		$access = new Access($vhost, 'response');

		if (!$access->delChainByName('BEGIN', GZIPNAME)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('删除成功');
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>