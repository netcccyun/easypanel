<?php
needRole('admin');
class FuncControl extends control
{
	/**
	 * 本节点数据导入到主控vhms
	 * 只使用在vhms第一次安装时
	 */
	public function syncVhostFrom()
	{
		return $this->_tpl->fetch('func/syncvhost.html');
	}

	public function syncVhost()
	{
		$db_host = $_REQUEST['db_host'];
		$db_user = $_REQUEST['db_user'];
		$db_passwd = $_REQUEST['db_passwd'];
		$db_name = $_REQUEST['db_name'];
		$username = $_REQUEST['username'];
		@apicall('utils', 'syncVhost', array($db_host, $db_user, $db_passwd, $db_name, $username));
	}

	public function resetCrontab()
	{
		$json['code'] = 200;
		unset($GLOBALS['cmd_run_error']);
		@apicall('cron', 'system_del', array('ep_sync_flow'));

		if (@!apicall('cron', 'install_system_cron', array())) {
			$json['code'] = 400;

			if (is_win()) {
				$json['msg'] = $GLOBALS['cmd_run_error'] . '请到控制面板的计划任务中删除名称为ep_sync_flow的计划任务,再执行本次操作';
			}
			else {
				$json['msg'] = $GLOBALS['cmd_run_error'] . '请删除/etc/cron.d/ep_sync_flow的计划任务,再执行本次操作';
			}
		}

		exit(json_encode($json));
	}

	public function delSmartyCache()
	{
		@apicall('utils', 'delTempleteFile', array());
		$json['code'] = 200;
		exit(json_encode($json));
	}

	/**
	 * 修改所有日志分析
	 */
	public function changeLoghandle()
	{
		$log = intval($_REQUEST['log_handle']);
		$where_arr['log_handle'] = $log == 1 ? 0 : 1;
		$arr['log_handle'] = $log;
		$json['code'] = 400;

		if (@apicall('vhost', 'updateAll', array($arr, $where_arr))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function changeFtp()
	{
		$ftp = intval($_REQUEST['ftp']);
		$where_arr['ftp'] = $ftp = 1 ? 1 : 0;
		$arr['ftp'] = $ftp;
		$json['code'] = 400;

		if (@apicall('vhost', 'updateAll', array($arr, $where_arr))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function changeFtpConnect()
	{
		$ftp_connect = intval($_REQUEST['ftp_connect']);
		$arr['ftp_connect'] = $ftp_connect;
		$where_arr = null;
		$json['code'] = 400;

		if (@apicall('vhost', 'updateAll', array($arr, $where_arr))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function changeFtpusl()
	{
		$ftp_usl = intval($_REQUEST['ftp_usl']);
		$arr['ftp_usl'] = $ftp_usl;
		$where_arr = null;
		$json['code'] = 400;

		if (@apicall('vhost', 'updateAll', array($arr, $where_arr))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function changeFtpdsl()
	{
		$ftp_dsl = intval($_REQUEST['ftp_dsl']);
		$arr['ftp_dsl'] = $ftp_dsl;
		$where_arr = null;
		$json['code'] = 400;

		if (@apicall('vhost', 'updateAll', array($arr, $where_arr))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function changeHtaccess()
	{
		$htaccess = intval($_REQUEST['htaccess']) == 1 ? '.htaccess' : null;
		$arr['htaccess'] = $htaccess;
		$where_arr = null;
		$json['code'] = 400;

		if (@apicall('vhost', 'updateAll', array($arr, $where_arr))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}
}

?>