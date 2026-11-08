<?php
class CronAPI extends API
{
	public function add($vhost, $params)
	{
		$max_cron = 0 - 1;
		$user = null;

		if ($vhost) {
			$user = daocall('vhost', 'getVhost', array($vhost));

			if (!$user) {
				setLastError('网站不存在');
				return false;
			}

			$max_cron = intval($user['cron']);
		}

		if ($max_cron == 0) {
			setLastError('当前网站未开通计划任务');
			return false;
		}

		$fields = array('min', 'hour', 'day', 'month', 'mday');

		if (isset($params['t'])) {
			$t = preg_split('/\\s+/', trim(ep_str($params['t'])));

			if (count($t) != 5) {
				setLastError('执行周期必须是 5 段 crontab 表达式，例如 */5 * * * *');
				return false;
			}

			foreach ($fields as $i => $field) {
				$params[$field] = $t[$i];
			}
		}

		$time = array();

		foreach ($fields as $field) {
			if (!isset($params[$field])) {
				setLastError('执行周期不完整');
				return false;
			}

			$time[$field] = trim(ep_str($params[$field]));
		}

		// 时间字段原样写入 cron XML 的 time 属性，必须先校验，防止注入
		if (!apicall('adminCron', 'checkCron', array(implode(' ', $time)))) {
			setLastError('执行周期格式不正确，请填写 5 段 crontab 表达式，例如 */5 * * * *');
			return false;
		}

		// 网址作为 wget 参数执行，不允许空白和引号，避免被拆成额外参数
		$cmd = trim(ep_str(isset($params['cmd']) ? $params['cmd'] : ''));

		if (strlen($cmd) > 2000 || !preg_match('!^https?://[^\\s\'"<>`]+$!i', $cmd) || !filter_var($cmd, FILTER_VALIDATE_URL)) {
			setLastError('网址格式不正确，必须以 http:// 或 https:// 开头，且不能包含空格或引号');
			return false;
		}

		$time['cmd'] = $cmd;
		$time['cmd_type'] = 0;

		if (daocall('cron', 'add', array($vhost, $time, $max_cron))) {
			return $this->sync($user ? $user : $vhost);
		}

		if (0 < $max_cron && $max_cron <= daocall('cron', 'getCount', array($vhost))) {
			setLastError('已达到计划任务数量上限（' . $max_cron . ' 条）');
		}
		else {
			setLastError('保存计划任务失败');
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
		$filename = $GLOBALS['safe_dir'] . 'cron.d/cron_' . ep_safe_name($vhost) . '.xml';
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
		else {
			$wget .= ' -O /dev/null --tries=1 --timeout=30 --max-redirect=1';
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
		return $GLOBALS['safe_dir'] . 'cron.d/cron_' . ep_safe_name($vhost) . '.xml';
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
		$out = array();
		$status = 0;
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
		$msg .= "MAILTO=\"\"\n";
		$ext = get_panel_php_dir('/vhs/kangle/ext/');
		$msg .= '*/' . $cron['step'] . ' * * * * root ' . get_panel_php_bin($ext) . ' -c ' . get_panel_php_ini($ext) . ' ' . $cron['cmd'];
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
