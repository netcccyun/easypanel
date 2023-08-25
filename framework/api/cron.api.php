<?php
class CronAPI extends API
{
	public function add($vhost, $params)
	{
		$max_cron = 0 - 1;

		if ($vhost) {
			$user = daocall('vhost', 'getVhost', array($vhost));

			if (!$user) {
				trigger_error('cannot get vhost');
				return false;
			}

			$max_cron = $user['cron'];
		}

		if ($max_cron == 0) {
			trigger_error('vhost not support cron');
			return false;
		}

		if (isset($params['t'])) {
			$t = preg_split('/\\s+/', $params['t']);

			if (count($t) != 5) {
				trigger_error('time format is error(like crontab)');
				return false;
			}

			$params['min'] = $t[0];
			$params['hour'] = $t[1];
			$params['day'] = $t[2];
			$params['month'] = $t[3];
			$params['mday'] = $t[4];
		}

		if (!isset($params['min']) || !isset($params['hour']) || !isset($params['day']) || !isset($params['month']) || !isset($params['mday'])) {
			trigger_error('min hour day month mday is missing');
			return false;
		}

		if (daocall('cron', 'add', array($vhost, $params, $max_cron))) {
			return $this->sync($user);
		}

		return false;
	}

	public function del($vhost, $id)
	{
		if (daocall('cron', 'del', array($vhost, $id))) {
			return $this->sync($vhost);
		}

		return false;
	}

	public function delAll($vhost)
	{
		daocall('cron', 'delAll', array($vhost));
		$filename = $GLOBALS['safe_dir'] . 'cron.d/cron_' . $vhost . '.xml';
		@unlink($filename);
		return true;
	}

	/**
	 * 同步计划任务
	 * @param  $vhost 可以为数组，也可以为网站名
	 * @return boolean
	 */
	public function sync($vhost)
	{
		if (is_array($vhost)) {
			$user = $vhost;
			$vhost = $user['name'];
		}
		else {
			$user = daocall('vhost', 'getVhost', array($vhost));

			if (!$user) {
				@unlink($this->getFilename($vhost));
				return true;
			}
		}

		$crons = daocall('cron', 'get', array($vhost));

		if (!$crons) {
			@unlink($this->getFilename($vhost));
			return true;
		}

		$str = "<config>\r\n";
		$wget = dirname($GLOBALS['safe_dir']) . '/bin/wget';

		if (is_win()) {
			$wget .= '.exe -O -';
		}

		foreach ($crons as $cron) {
			switch ($cron['cmd_type']) {
			case 0:
				$str .= '	<cron time=\'';
				$str .= $cron['min'] . ' ';
				$str .= $cron['hour'] . ' ';
				$str .= $cron['day'] . ' ';
				$str .= $cron['month'] . ' ';
				$str .= $cron['mday'];
				$str .= '\' stdout=\'+';
				$str .= $user['doc_root'] . '/logs/cron.log';
				$str .= '\' runas=\'' . $vhost . "'>\r\n";
				$str .= '		<command stderr_as_out=\'1\'>' . $wget . ' ' . apicall('utils', 'xmlencode', array($cron['cmd'])) . "</command>\r\n";
				$str .= "\t</cron>\r\n";
			default:
			}
		}

		$str .= "</config>\r\n<!--cron_file_is_ok-->";
		$fp = @fopen($this->getFilename($vhost), 'wb');

		if ($fp === false) {
			mkdir($GLOBALS['safe_dir'] . 'cron.d');
			$fp = @fopen($this->getFilename($vhost), 'wb');
		}

		if ($fp) {
			fwrite($fp, $str);
			fclose($fp);
		}

		return true;
	}

	/**
	 * 安装系统计划任务
	 */
	public function install_system_cron()
	{
		$cron = array('circle' => 'min', 'step' => 5, 'type' => 'php', 'cmd' => SYS_ROOT . '/shell.php sync_flow');
		return $this->system_add('ep_sync_flow', null, $cron);
	}

	/**
	 * 增加 系统计划任务
	 * @param string $name 计划任务名称
	 * @param array $vh 运行身份(null为系统身份)
	 * @param array $commands 命令 array('type'=>'php|cmd',command=>'command',circle=>'min|hour|day|week',step=>'step','start_time'=>'start_time')
	 */
	public function system_add($name, $vh, $cron)
	{
		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			return $this->winadd($name, $vh, $cron);
		}

		return $this->unixadd($name, $vh, $cron);
	}

	/**
	 * 删除系统计划任务
	 * @param string $name 计划任务名称
	 */
	public function system_del($name)
	{
		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			return $this->windel($name);
		}

		return $this->unixdel($name);
	}

	private function getFilename($vhost)
	{
		return $GLOBALS['safe_dir'] . 'cron.d/cron_' . $vhost . '.xml';
	}

	private function winadd($name, $vh, $cron)
	{
		$map_circle = array('min' => 'MINUTE', 'hour' => 'HOURLY', 'day' => 'DAILY', 'week' => 'WEEKLY');
		$cmd = 'schtasks /create /RU ';

		if (!$vh) {
			$cmd .= 'SYSTEM';
		}
		else {
			$cmd .= $vh['uid'] . ' /RP ' . $vh['gid'];
		}

		$cmd .= ' /TN ' . $name;
		$cmd .= ' /SC ' . $map_circle[$cron['circle']];
		$cmd .= ' /MO ' . $cron['step'];
		$cmd .= ' /TR "';

		if ($cron['type'] == 'php') {
			$cmd .= '\\"' . $GLOBALS['safe_dir'] . '../bin/phpcron.bat\\"';
		}

		$cmd .= '"';
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$GLOBALS['cmd_run_error'] = 'cmd=' . $cmd . ' exec status=' . $status;
			return false;
		}

		return true;
	}

	private function unixadd($name, $vh, $cron)
	{
		$cron_dir = '/etc/cron.d/';

		if (!file_exists($cron_dir)) {
			mkdir($cron_dir);
		}

		$cronfile = $cron_dir . $name;
		$fp = fopen($cronfile, 'wt');

		if ($fp === false) {
			echo 'cann\'t open file : ' . $cronfile . "\n";
			return false;
		}

		$msg = "SHELL=/bin/bash\n";
		$msg .= "PATH=/sbin:/bin:/usr/sbin:/usr/bin\n";
		$msg .= "HOME=/\n";
		$msg .= '*/' . $cron['step'] . ' * * * * root /vhs/kangle/ext/php56/bin/php -c /vhs/kangle/ext/php56/etc/php-node.ini ' . $cron['cmd'];
		$msg .= "\n";
		fwrite($fp, $msg);
		fclose($fp);
		return true;
	}

	private function windel($name)
	{
		$cmd = 'schtasks /delete ';
		$cmd .= '/tn ' . $name;
		$cmd .= ' /f';
		exec($cmd);
	}

	private function unixdel($name)
	{
		unlink('/etc/cron.d/ep_sync_flow');
	}
}

?>