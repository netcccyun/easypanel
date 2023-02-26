<?php
needRole('vhost');
class IndexfileControl extends Control
{
	private $whm;

	public function __construct()
	{
		parent::__construct();
		$this->whm = apicall('nodes', 'makeWhm', array('localhost'));
	}

	public function indexfileFrom()
	{
		$result = $this->indexfileList();
		$in = daocall('vhostinfo', 'getInfo', array(getRole('vhost'), 2));
		$this->_tpl->assign('in', $in);
		return $this->_tpl->fetch('indexfile/indexfilefrom.html');
	}

	/**
	 * 文件名必需有个.
	 * Enter description here ...
	 */
	public function indexfileAdd()
	{
		$id = intval($_REQUEST['id']);
		$file = filterParam($_REQUEST['file']);

		if (!$id) {
			$id = 100;
		}

		if ($file == '') {
			exit('文件名不能为空');
		}

		if (apicall('vhost', 'addInfo', array(getRole('vhost'), $file, 2, $id))) {
			exit('成功');
		}

		exit('添加失败');
	}

	public function indexfileDel()
	{
		$file = filterParam($_REQUEST['file']);

		if (apicall('vhost', 'delInfo', array(getRole('vhost'), $file, 2, null))) {
			exit('成功');
		}

		exit('删除失败');
	}

	private function indexfileList()
	{
		$whmCall = new WhmCall('core.whm', 'list_index');
		$whmCall->addParam('vh', getRole('vhost'));
		return $this->whm->call($whmCall);
	}
}

?>