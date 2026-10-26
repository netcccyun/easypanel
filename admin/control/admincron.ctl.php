<?php
needRole('admin');
class AdmincronControl extends Control
{
	public function index()
	{
		apicall('adminCron', 'ensureTable', array());
		$keyword = trim(ep_request('keyword'));
		$all = daocall('adminCron', 'getList', array());
		$all = is_array($all) ? $all : array();
		if ($keyword !== '') {
			$matched = array();
			foreach ($all as $task) {
				if (stripos(ep_str($task['name']), $keyword) !== false
					|| stripos(ep_str($task['cmd']), $keyword) !== false
					|| stripos(ep_str($task['cron']), $keyword) !== false) {
					$matched[] = $task;
				}
			}
			$all = $matched;
		}
		$count = count($all);
		foreach ($all as $key => $task) {
			$all[$key]['cron_desc'] = $this->cronText($task['cron']);
		}
		$page_count = intval(daocall('setting', 'get', array('admin_cron_page_count')));
		if ($page_count < 5 || $page_count > 100) {
			$page_count = 15;
		}
		$total_page = max(1, (int) ceil($count / $page_count));
		$page = intval(ep_request('page'));
		if ($page < 1) {
			$page = 1;
		}
		if ($page > $total_page) {
			$page = $total_page;
		}
		$this->_tpl->assign('tasks', array_slice($all, ($page - 1) * $page_count, $page_count));
		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('keyword', $keyword);
		$this->_tpl->assign('msg', ep_request('msg'));
		return $this->_tpl->fetch('admincron/index.html');
	}

	public function get()
	{
		$task = daocall('adminCron', 'get', array(intval(ep_request('id'))));
		header('Content-Type: application/json; charset=UTF-8');
		if (!$task) {
			echo '{}';
			exit();
		}
		echo json_encode(array(
			'id' => intval($task['id']),
			'name' => ep_str($task['name']),
			'type' => ep_str($task['type']),
			'cmd' => ep_str($task['cmd']),
			'cron' => ep_str($task['cron']),
			'flock' => intval($task['flock'])
		));
		exit();
	}

	public function batch()
	{
		$op = ep_request('op');
		if (!in_array($op, array('del', 'enable', 'disable'), true)) {
			$this->back('未知的批量操作');
		}
		$ids = explode(',', ep_request('ids'));
		$done = 0;
		$failed = 0;
		foreach ($ids as $raw) {
			$id = intval($raw);
			if ($id <= 0) {
				continue;
			}
			$ok = ($op === 'del')
				? apicall('adminCron', 'del', array($id))
				: apicall('adminCron', 'toggle', array($id, $op === 'enable' ? 1 : 0));
			if ($ok === false) {
				++$failed;
			} else {
				++$done;
			}
		}
		if ($done === 0 && $failed === 0) {
			$this->back('请先选择任务');
		}
		$names = array('del' => '删除', 'enable' => '启用', 'disable' => '停用');
		$msg = $names[$op] . '完成：成功 ' . $done . ' 个';
		if ($failed > 0) {
			$msg .= '，失败 ' . $failed . ' 个';
		}
		$this->back($msg);
	}

	public function add()
	{
		$ok = apicall('adminCron', 'add', array($this->formParams()));
		if ($ok === false) {
			$this->back('添加失败：' . $this->lastError());
		}
		if (!empty($GLOBALS['last_error'])) {
			$this->back('任务已保存，但' . $GLOBALS['last_error']);
		}
		$this->back('添加成功');
	}

	public function save()
	{
		$id = intval(ep_request('id'));
		$ok = apicall('adminCron', 'update', array($id, $this->formParams()));
		if ($ok === false) {
			$this->back('保存失败：' . $this->lastError());
		}
		if (!empty($GLOBALS['last_error'])) {
			$this->back('任务已保存，但' . $GLOBALS['last_error']);
		}
		$this->back('保存成功');
	}

	public function del()
	{
		$ok = apicall('adminCron', 'del', array(intval(ep_request('id'))));
		if ($ok === false) {
			$this->back('删除失败：' . $this->lastError());
		}
		if (!empty($GLOBALS['last_error'])) {
			$this->back('任务已删除，但' . $GLOBALS['last_error']);
		}
		$this->back('删除成功');
	}

	public function toggle()
	{
		$id = intval(ep_request('id'));
		$enabled = intval(ep_request('enabled')) === 1 ? 1 : 0;
		$ok = apicall('adminCron', 'toggle', array($id, $enabled));
		if ($ok === false) {
			$this->back('操作失败：' . $this->lastError());
		}
		$msg = $enabled ? '任务已启用' : '任务已停用';
		if (!empty($GLOBALS['last_error'])) {
			$this->back($msg . '，但' . $GLOBALS['last_error']);
		}
		$this->back($msg);
	}

	public function run()
	{
		$id = intval(ep_request('id'));
		$ok = apicall('adminCron', 'spawn', array($id));
		if ($ok === false) {
			$msg = '执行失败：' . $this->lastError();
		} else {
			$msg = '已触发执行，请稍后查看日志';
		}
		if (ep_request('from') === 'log') {
			$this->backLog($id, $msg);
		}
		$this->back($msg);
	}

	public function sync()
	{
		$ok = apicall('adminCron', 'sync', array());
		if ($ok === false) {
			$this->back('重建失败：' . $this->lastError());
		}
		$this->back('系统计划任务已重建');
	}

	public function log()
	{
		apicall('adminCron', 'ensureTable', array());
		$id = intval(ep_request('id'));
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			$this->back('任务不存在');
		}
		$log = apicall('adminCron', 'getLog', array($id));
		if ($log === '') {
			$log = '暂无执行日志';
		}
		$this->_tpl->assign('task', $task);
		$this->_tpl->assign('msg', ep_request('msg'));
		$this->_tpl->assign('log', htmlspecialchars($log, ENT_QUOTES, 'UTF-8'));
		return $this->_tpl->fetch('admincron/log.html');
	}

	public function logdata()
	{
		$id = intval(ep_request('id'));
		$log = apicall('adminCron', 'getLog', array($id));
		header('Content-Type: text/plain; charset=UTF-8');
		header('Cache-Control: no-cache');
		echo $log === '' ? '暂无执行日志' : $log;
		exit();
	}

	public function clear()
	{
		apicall('adminCron', 'ensureTable', array());
		$id = intval(ep_request('id'));
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			$this->back('任务不存在');
		}
		if (apicall('adminCron', 'clearLog', array($id)) === false) {
			header('Location: ?c=admincron&a=log&id=' . $id . '&msg=' . urlencode('清空日志失败，请检查日志目录权限'));
			exit();
		}
		header('Location: ?c=admincron&a=log&id=' . $id . '&msg=' . urlencode('日志已清空'));
		exit();
	}

	public function download()
	{
		apicall('adminCron', 'ensureTable', array());
		$id = intval(ep_request('id'));
		$task = daocall('adminCron', 'get', array($id));
		if (!$task) {
			$this->back('任务不存在');
		}
		$log = apicall('adminCron', 'getLogFull', array($id));
		if ($log === '') {
			header('Location: ?c=admincron&a=log&id=' . $id . '&msg=' . urlencode('暂无执行日志可下载'));
			exit();
		}
		header('Content-Type: text/plain; charset=UTF-8');
		header('Content-Disposition: attachment; filename="cron_' . $id . '_' . date('YmdHis') . '.log"');
		header('Content-Length: ' . strlen($log));
		echo $log;
		exit();
	}

	private function formParams()
	{
		return array(
			'name' => ep_request('name'),
			'type' => ep_request('type'),
			'cmd' => ep_request('cmd'),
			'cron' => ep_request('cron'),
			'flock' => ep_request('flock') === '1' ? 1 : 0
		);
	}

	private function cronText($cron)
	{
		$cron = trim(preg_replace('/\s+/', ' ', ep_str($cron)));
		$p = explode(' ', $cron);
		if (count($p) !== 5) {
			return $cron;
		}
		list($mi, $h, $d, $mo, $w) = $p;
		$time = sprintf('%02d:%02d', intval($h), intval($mi));
		if (preg_match('/^\d+$/', $mi) && preg_match('/^\d+$/', $h) && $d === '*' && $mo === '*' && $w === '*') {
			return '每天 ' . $time . ' 执行';
		}
		if ($mi === '*' && $h === '*' && $d === '*' && $mo === '*' && $w === '*') {
			return '每分钟执行';
		}
		if (preg_match('#^\*/(\d+)$#', $mi, $m) && $h === '*' && $d === '*' && $mo === '*' && $w === '*') {
			return '每 ' . intval($m[1]) . ' 分钟执行';
		}
		if (preg_match('/^\d+$/', $mi) && preg_match('#^\*/(\d+)$#', $h, $m) && $d === '*' && $mo === '*' && $w === '*') {
			return '每 ' . intval($m[1]) . ' 小时的第 ' . intval($mi) . ' 分钟执行';
		}
		if (preg_match('/^\d+$/', $mi) && preg_match('/^\d+$/', $h) && preg_match('#^\*/(\d+)$#', $d, $m) && $mo === '*' && $w === '*') {
			return '每 ' . intval($m[1]) . ' 天的 ' . $time . ' 执行';
		}
		if (preg_match('/^\d+$/', $mi) && $h === '*' && $d === '*' && $mo === '*' && $w === '*') {
			return '每小时的第 ' . intval($mi) . ' 分钟执行';
		}
		if (preg_match('/^\d+$/', $mi) && preg_match('/^\d+$/', $h) && $d === '*' && $mo === '*' && preg_match('/^[0-7]$/', $w)) {
			$names = array('日', '一', '二', '三', '四', '五', '六');
			$idx = $w === '7' ? 0 : intval($w);
			return '每周' . $names[$idx] . ' ' . $time . ' 执行';
		}
		if (preg_match('/^\d+$/', $mi) && preg_match('/^\d+$/', $h) && preg_match('/^\d+$/', $d) && $mo === '*' && $w === '*') {
			return '每月 ' . intval($d) . ' 日 ' . $time . ' 执行';
		}
		if (preg_match('/^\d+$/', $mi) && preg_match('/^\d+$/', $h) && preg_match('/^\d+$/', $d) && preg_match('/^\d+$/', $mo) && $w === '*') {
			return '每年 ' . intval($mo) . ' 月 ' . intval($d) . ' 日 ' . $time . ' 执行';
		}
		return $cron;
	}

	private function lastError()
	{
		return !empty($GLOBALS['last_error']) ? $GLOBALS['last_error'] : '未知错误';
	}

	private function back($msg)
	{
		$url = '?c=admincron&a=index&msg=' . urlencode($msg);
		$keyword = trim(ep_request('keyword'));
		if ($keyword !== '') {
			$url .= '&keyword=' . urlencode($keyword);
		}
		$page = intval(ep_request('page'));
		if ($page > 1) {
			$url .= '&page=' . $page;
		}
		header('Location: ' . $url);
		exit();
	}

	private function backLog($id, $msg)
	{
		header('Location: ?c=admincron&a=log&id=' . intval($id) . '&msg=' . urlencode($msg));
		exit();
	}
}
