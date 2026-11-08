<?php
needRole('vhost');
class CronControl extends Control
{
	public function index()
	{
		$crons = daocall('cron', 'get', array(getRole('vhost')));
		$crons = is_array($crons) ? $crons : array();

		foreach ($crons as $key => $cron) {
			$expr = $cron['min'] . ' ' . $cron['hour'] . ' ' . $cron['day'] . ' ' . $cron['month'] . ' ' . $cron['mday'];
			$crons[$key]['expr'] = $expr;
			$crons[$key]['desc'] = apicall('adminCron', 'describe', array($expr));
		}

		$this->_tpl->assign('crons', $crons);
		$this->_tpl->assign('count', count($crons));
		$this->_tpl->assign('msg', ep_request('msg'));
		$this->_tpl->assign('err', intval(ep_request('err')));
		return $this->_tpl->fetch('cron/index.html');
	}

	public function add()
	{
		if (apicall('cron', 'add', array(getRole('vhost'), $_REQUEST))) {
			$this->back('计划任务已添加');
		}

		$this->back('添加失败：' . $this->lastError('请检查执行周期和网址'), true);
	}

	public function del()
	{
		if (apicall('cron', 'del', array(getRole('vhost'), intval(ep_request('id'))))) {
			$this->back('计划任务已删除');
		}

		$this->back('删除失败：任务不存在或已被删除', true);
	}

	public function sync()
	{
		apicall('cron', 'sync', array(getRole('vhost')));
		$this->back('计划任务已重建');
	}

	private function lastError($default)
	{
		return !empty($GLOBALS['last_error']) ? $GLOBALS['last_error'] : $default;
	}

	private function back($msg, $err = false)
	{
		header('Location: ?c=cron&a=index&msg=' . urlencode($msg) . ($err ? '&err=1' : ''));
		exit();
	}
}
