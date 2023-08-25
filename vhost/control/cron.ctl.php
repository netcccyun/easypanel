<?php
needRole('vhost');
class CronControl extends Control
{
	public function index()
	{
		$crons = daocall('cron', 'get', array(getRole('vhost')));
		$this->_tpl->assign('crons', $crons);
		return $this->_tpl->fetch('cron/index.html');
	}

	public function add()
	{
		$vhost = getRole('vhost');
		apicall('cron', 'add', array($vhost, $_REQUEST));
		return $this->index();
	}

	public function del()
	{
		apicall('cron', 'del', array(getRole('vhost'), $_REQUEST['id']));
		return $this->index();
	}

	public function sync()
	{
		apicall('cron', 'sync', array(getRole('vhost')));
		return $this->index();
	}
}

?>