<?php
needRole('vhost');
class InfoControl extends Control
{
	public function showErrorPage()
	{
		$list = daocall('vhostinfo', 'getInfo', array(getRole('vhost'), 1));
		$sum = count($list);
		$this->_tpl->assign('sum', $sum);
		$this->_tpl->assign('list', $list);
		$this->_tpl->assign('action', 'add');
		return $this->_tpl->fetch('info/showErrorPage.html');
	}

	public function addErrorPageForm()
	{
		$this->_tpl->assign('action', 'add');
		return $this->_tpl->fetch('info/addErrorPage.html');
	}

	public function addErrorPage()
	{
		$vhost = getRole('vhost');
		$name = intval($_REQUEST['name']);
		$value = filterParam($_REQUEST['value'], 'file');

		if (!apicall('vhost', 'addInfo', array($vhost, $name, 1, $value, false))) {
			trigger_error('增加自定义错误失败');
		}

		return $this->showErrorPage();
	}

	public function delErrorPage()
	{
		if (!apicall('vhost', 'delInfo', array(getRole('vhost'), filterParam($_REQUEST['name']), 1, null))) {
			trigger_error('删除自定义错误失败');
		}

		return $this->showErrorPage();
	}
}

?>