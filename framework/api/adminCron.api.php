<?php
class AdminCronAPI extends API
{
	const CRON_FILE = '/etc/cron.d/easypanel_cron';
	const LOG_MAX_SIZE = 262144;
	const LOG_KEEP_SIZE = 131072;

	public function ensureTable()
	{
		return daocall('adminCron', 'ensureTable', array());
	}

	public function add($params)
	{
		$this->ensureTable();
		$data = $this->normalize($params);
		if ($data === false) {
			return false;
		}
		$data['enabled'] = 1;
		$data['last_run'] = 0;
		$data['last_status'] = 0;
		if (!daocall('adminCron', 'add', array($data))) {
			setLastError('保存任务失败');
			return false;
		}
		$this->sync();
		return true;
	}

	public function update($id, $params)
	{
		$this->ensureTable();
		$id = intval($id);
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			setLastError('任务不存在');
			return false;
		}
		$data = $this->normalize($params);
		if ($data === false) {
			return false;
		}
		if (!daocall('adminCron', 'updateById', array($id, $data))) {
			setLastError('保存任务失败');
			return false;
		}
		$this->sync();
		return true;
	}

	public function del($id)
	{
		$this->ensureTable();
		$id = intval($id);
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			setLastError('任务不存在');
			return false;
		}
		if (!daocall('adminCron', 'delById', array($id))) {
			setLastError('删除任务失败');
			return false;
		}
		$dir = $this->logDir();
		@unlink($dir . 'task_' . $id . '.log');
		@unlink($dir . 'task_' . $id . '.lock');
		$this->sync();
		return true;
	}

	public function toggle($id, $enabled)
	{
		$this->ensureTable();
		$id = intval($id);
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			setLastError('任务不存在');
			return false;
		}
		$data = array('enabled' => intval($enabled) ? 1 : 0);
		if (!daocall('adminCron', 'updateById', array($id, $data))) {
			setLastError('保存任务失败');
			return false;
		}
		$this->sync();
		return true;
	}

	public function sync()
	{
		if (is_win()) {
			setLastError('仅 Linux 支持系统定时任务');
			return false;
		}
		$this->ensureTable();
		$tasks = daocall('adminCron', 'getList', array());
		$lines = '';
		if (is_array($tasks)) {
			foreach ($tasks as $task) {
				if (intval($task['enabled']) != 1) {
					continue;
				}
				if (!$this->checkCron($task['cron'])) {
					continue;
				}
				$lines .= trim($task['cron']) . ' root ' . $this->shellCommand($task['id']) . "\n";
			}
		}
		$file = self::CRON_FILE;
		if ($lines === '') {
			if (is_file($file) && !@unlink($file)) {
				setLastError('无法删除系统计划任务文件，请检查 /etc/cron.d 权限');
				return false;
			}
			return true;
		}
		if (!is_dir('/etc/cron.d') && !@mkdir('/etc/cron.d', 0755)) {
			setLastError('无法创建 /etc/cron.d 目录');
			return false;
		}
		$header = "SHELL=/bin/bash\nPATH=/sbin:/bin:/usr/sbin:/usr/bin\nHOME=/\nMAILTO=\"\"\n\n";
		$tmp = $file . '.tmp.' . getmypid();
		if (@file_put_contents($tmp, $header . $lines) === false) {
			@unlink($tmp);
			setLastError('无法写入系统计划任务文件，请检查 /etc/cron.d 权限');
			return false;
		}
		@chmod($tmp, 0644);
		if (!@rename($tmp, $file)) {
			@unlink($tmp);
			setLastError('无法更新系统计划任务文件，请检查 /etc/cron.d 权限');
			return false;
		}
		return true;
	}

	public function spawn($id)
	{
		$this->ensureTable();
		$id = intval($id);
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			setLastError('任务不存在');
			return false;
		}
		if (is_win()) {
			setLastError('仅 Linux 支持系统定时任务');
			return false;
		}
		if (!function_exists('exec')) {
			setLastError('exec 函数被禁用，无法执行任务');
			return false;
		}
		@exec('nohup ' . $this->shellCommand($id) . ' </dev/null >/dev/null 2>&1 &');
		return true;
	}

	public function run($id)
	{
		if (is_win()) {
			setLastError('仅 Linux 支持系统定时任务');
			return false;
		}
		$this->ensureTable();
		$id = intval($id);
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			setLastError('任务不存在');
			return false;
		}
		$dir = $this->logDir(true);
		if (!is_dir($dir)) {
			setLastError('无法创建日志目录 ' . $dir);
			return false;
		}
		$logfile = $dir . 'task_' . $id . '.log';
		$lock = null;
		if (!empty($task['flock'])) {
			$lockfile = $dir . 'task_' . $id . '.lock';
			$lock = @fopen($lockfile, 'c');
			if (!$lock) {
				setLastError('无法创建任务锁文件');
				return false;
			}
			if (!flock($lock, LOCK_EX | LOCK_NB)) {
				$this->appendLog($logfile, '[' . date('Y-m-d H:i:s') . "] 上次执行尚未结束，本次已跳过\n");
				fclose($lock);
				return false;
			}
		}

		$type = $task['type'] === 'url' ? 'URL 访问' : 'Shell 命令';
		$this->appendLog($logfile, '[' . date('Y-m-d H:i:s') . '] 开始执行（' . $type . "）\n");
		$ok = false;
		if ($task['type'] === 'url') {
			$ok = $this->runUrl($task['cmd'], $logfile);
		} else if ($task['type'] === 'shell') {
			$ok = $this->runShell($task['cmd'], $logfile);
		} else {
			$this->appendLog($logfile, "未知的任务类型，已跳过\n");
		}

		daocall('adminCron', 'updateById', array($id, array(
			'last_run' => time(),
			'last_status' => $ok ? 1 : 2
		)));
		if ($lock) {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return $ok;
	}

	public function getLog($id)
	{
		$file = $this->logDir(false) . 'task_' . intval($id) . '.log';
		if (!is_file($file)) {
			return '';
		}
		$size = @filesize($file);
		$max = 8192;
		$fp = @fopen($file, 'rb');
		if (!$fp) {
			return '';
		}
		if ($size > $max) {
			fseek($fp, 0 - $max, SEEK_END);
		}
		$log = fread($fp, $max);
		fclose($fp);
		$log = preg_replace('/^[\x80-\xBF]+/', '', $log);
		if ($size > $max) {
			$log = "（仅显示最后 8KB）\n" . $log;
		}
		return $log;
	}

	public function clearLog($id)
	{
		$file = $this->logDir(false) . 'task_' . intval($id) . '.log';
		if (!is_file($file)) {
			return true;
		}
		return @unlink($file);
	}

	public function getLogFull($id)
	{
		$file = $this->logDir(false) . 'task_' . intval($id) . '.log';
		if (!is_file($file)) {
			return '';
		}
		return (string) @file_get_contents($file);
	}

	public function checkCron($expr)
	{
		$parts = preg_split('/\s+/', trim($expr));
		if (count($parts) !== 5) {
			return false;
		}
		$ranges = array(array(0, 59), array(0, 23), array(1, 31), array(1, 12), array(0, 7));
		for ($i = 0; $i < 5; $i++) {
			if (!$this->checkCronField($parts[$i], $ranges[$i][0], $ranges[$i][1])) {
				return false;
			}
		}
		return true;
	}

	private function normalize($params)
	{
		$name = isset($params['name']) ? trim($params['name']) : '';
		$name = str_replace(array("\r", "\n"), ' ', $name);
		$type = isset($params['type']) ? $params['type'] : '';
		$cmd = isset($params['cmd']) ? trim($params['cmd']) : '';
		$cron = isset($params['cron']) ? preg_replace('/\s+/', ' ', trim($params['cron'])) : '';
		$flock = !empty($params['flock']) ? 1 : 0;

		$nameLen = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
		if ($nameLen < 1 || $nameLen > 100) {
			setLastError('任务名称不能为空且不能超过 100 个字符');
			return false;
		}
		if ($type !== 'shell' && $type !== 'url') {
			setLastError('任务类型不正确');
			return false;
		}
		if ($cmd === '') {
			setLastError('命令或网址不能为空');
			return false;
		}
		if (strlen($cmd) > 8000) {
			setLastError('命令内容不能超过 8000 个字符');
			return false;
		}
		if ($type === 'url') {
			$cmd = str_replace(array("\r", "\n"), '', $cmd);
			if (!preg_match('!^https?://!i', $cmd) || !filter_var($cmd, FILTER_VALIDATE_URL)) {
				setLastError('网址格式不正确，必须以 http:// 或 https:// 开头');
				return false;
			}
		}
		if (!$this->checkCron($cron)) {
			setLastError('执行周期格式不正确，请填写 5 段 crontab 表达式，例如 */5 * * * *');
			return false;
		}
		return array(
			'name' => $name,
			'type' => $type,
			'cmd' => $cmd,
			'cron' => $cron,
			'flock' => $flock
		);
	}

	private function checkCronField($field, $min, $max)
	{
		if ($field === '' || !preg_match('#^[0-9*,/-]+$#', $field)) {
			return false;
		}
		foreach (explode(',', $field) as $part) {
			if ($part === '') {
				return false;
			}
			$pos = strpos($part, '/');
			if ($pos !== false) {
				$step = substr($part, $pos + 1);
				$part = substr($part, 0, $pos);
				if (!ctype_digit($step) || intval($step) < 1) {
					return false;
				}
			}
			if ($part === '*') {
				continue;
			}
			$pos = strpos($part, '-');
			if ($pos !== false) {
				$a = substr($part, 0, $pos);
				$b = substr($part, $pos + 1);
				if (!ctype_digit($a) || !ctype_digit($b)) {
					return false;
				}
				$a = intval($a);
				$b = intval($b);
				if ($a < $min || $b > $max || $a > $b) {
					return false;
				}
			} else {
				if (!ctype_digit($part)) {
					return false;
				}
				$v = intval($part);
				if ($v < $min || $v > $max) {
					return false;
				}
			}
		}
		return true;
	}

	private function runShell($cmd, $logfile)
	{
		if (!function_exists('shell_exec')) {
			$this->appendLog($logfile, "执行失败：shell_exec 函数被禁用\n");
			return false;
		}
		$mark = '__EP_EXIT_' . uniqid() . '__';
		$script = '( ' . $cmd . "\n) 2>&1\n" . 'echo ' . $mark . '$?' . "\n";
		$raw = @shell_exec('/bin/sh -c ' . escapeshellarg($script));
		$code = -1;
		if (is_string($raw)) {
			$pos = strrpos($raw, $mark);
			if ($pos !== false) {
				$code = intval(trim(substr($raw, $pos + strlen($mark))));
				$raw = substr($raw, 0, $pos);
			}
		}
		$output = $this->tailText(is_string($raw) ? rtrim($raw, "\n") : '', 10000);
		$this->appendLog($logfile, '[' . date('Y-m-d H:i:s') . '] ' . ($code === 0 ? '执行成功' : '执行失败')
			. '：退出码 ' . $code . ($output === '' ? "，无输出\n" : "\n" . $output . "\n"));
		return $code === 0;
	}

	private function runUrl($url, $logfile)
	{
		$context = stream_context_create(array('http' => array(
			'timeout' => 30,
			'ignore_errors' => true,
			'user_agent' => 'EasyPanel-Cron/1.0'
		)));
		$body = @file_get_contents($url, false, $context);
		$code = 0;
		if (isset($http_response_header) && is_array($http_response_header)
			&& preg_match('!HTTP/\S+\s+(\d+)!', $http_response_header[0], $match)) {
			$code = intval($match[1]);
		}
		if ($body === false) {
			$this->appendLog($logfile, '[' . date('Y-m-d H:i:s') . "] 执行失败：请求失败，无响应\n");
			return false;
		}
		$ok = $code >= 200 && $code < 400;
		$this->appendLog($logfile, '[' . date('Y-m-d H:i:s') . '] ' . ($ok ? '执行成功' : '执行失败')
			. '：HTTP ' . $code . '，响应 ' . strlen($body) . " 字节\n");
		return $ok;
	}

	private function shellCommand($id)
	{
		$dir = get_panel_php_dir();
		$cmd = escapeshellarg(get_panel_php_bin($dir))
			. ' -c ' . escapeshellarg(get_panel_php_ini($dir))
			. ' ' . escapeshellarg(SYS_ROOT . '/shell.php')
			. ' admin_cron_run ' . intval($id)
			. ' >/dev/null 2>&1';
		return $cmd;
	}

	private function logDir($create = true)
	{
		$dir = $GLOBALS['safe_dir'] . 'admin_cron/';
		if ($create && !is_dir($dir)) {
			@mkdir($dir, 0700, true);
		}
		return $dir;
	}

	private function appendLog($file, $text)
	{
		@file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
		clearstatcache();
		$size = @filesize($file);
		if ($size === false || $size <= self::LOG_MAX_SIZE) {
			return;
		}
		$fp = @fopen($file, 'rb');
		if (!$fp) {
			return;
		}
		fseek($fp, 0 - self::LOG_KEEP_SIZE, SEEK_END);
		$tail = fread($fp, self::LOG_KEEP_SIZE);
		fclose($fp);
		$tail = preg_replace('/^[\x80-\xBF]+/', '', $tail);
		$pos = strpos($tail, "\n");
		if ($pos !== false) {
			$tail = substr($tail, $pos + 1);
		}
		@file_put_contents($file, $tail, LOCK_EX);
	}

	private function tailText($text, $max)
	{
		if (strlen($text) <= $max) {
			return $text;
		}
		$text = substr($text, 0 - $max);
		$text = preg_replace('/^[\x80-\xBF]+/', '', $text);
		return "（输出过长，仅保留末尾部分）\n" . $text;
	}
}
