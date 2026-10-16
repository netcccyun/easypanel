<?php
needRole('vhost');

class WebwafControl extends Control
{
	const TABLENAME = 'table:!safeline_waf';
	const ACTION = '!safeline_waf';
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function webwafFrom()
	{
		$tables = $this->access->listTable();
		if ($tables === false) {
			return $this->showMessage('读取请求控制规则失败');
		}
		$whiteIps = '';
		$whiteUrls = array();
		if (in_array(self::ACTION, $tables, true)) {
			$whitelists = $this->readWhitelists();
			if ($whitelists === false) {
				return $this->showMessage('读取Web安全防护设置失败');
			}
			$whiteIps = implode("\n", $whitelists[0]);
			$whiteUrls = $whitelists[1];
		}
		$enabled = (bool) $this->access->findChain('BEGIN', self::ACTION);
		$this->_tpl->assign('enabled', $enabled ? 1 : 0);
		$this->_tpl->assign('whiteip', htmlspecialchars($whiteIps, ENT_QUOTES, 'UTF-8'));
		$this->_tpl->assign('whiteurl', htmlspecialchars(implode("\n", $whiteUrls), ENT_QUOTES, 'UTF-8'));
		return $this->_tpl->fetch('webwaf/webwaf.html');
	}

	public function webwafSave()
	{
		if (apicall('access', 'checkAccess', array('ent')) !== true) {
			exit('您的空间不支持该功能，请联系管理员');
		}
		$ips = $this->parseIps(isset($_POST['whiteip']) ? $_POST['whiteip'] : '');
		$urls = $this->parseUrls(isset($_POST['whiteurl']) ? $_POST['whiteurl'] : '');
		if ($ips === false || $urls === false) {
			exit('白名单格式有误或条目过多');
		}
		if (!$this->ensureTable()) {
			exit('不能增加Web安全防护规则表');
		}
		if (!$this->addRule($ips, $urls)) {
			exit('保存Web安全防护设置失败');
		}
		if ($this->access->findChain('BEGIN', self::ACTION) &&
			!$this->linkBegin()) {
			exit('白名单已保存，但更新BEGIN链失败');
		}
		$this->sync();
		exit('成功');
	}

	public function webwafSwitch()
	{
		if (apicall('access', 'checkAccess', array('ent')) !== true) {
			exit('您的空间不支持该功能，请联系管理员');
		}
		$status = isset($_POST['status']) ? (int) $_POST['status'] : 0;
		if ($status !== 1 && $status !== 2) {
			exit('开关状态无效');
		}
		$enabled = (bool) $this->access->findChain('BEGIN', self::ACTION);
		if ($status === 1) {
			if (!file_exists('/data/safeline/resources/detector/snserver.sock')) {
				exit('请先安装并启动雷池WAF');
			}
			if (!$this->ensureTable()) {
				exit('创建Web安全防护规则表失败');
			}
			if (!$this->hasRule() && !$this->addRule(array(), array())) {
				exit('初始化Web安全防护设置失败');
			}
			if (!$this->linkBegin()) {
				exit('开启Web安全防护失败');
			}
			$this->access->sortBeginChains();
		} elseif ($status === 2 && $enabled) {
			if (!$this->access->delChainByName('BEGIN', self::ACTION)) {
				exit('关闭Web安全防护失败');
			}
		}
		$this->sync();
		exit('成功');
	}

	private function addRule($ips, $urls)
	{
		$models = array();
		if ($ips) {
			$models['acl_srcs'] = array('revers' => 1, 'split' => '|', 'v' => implode('|', $ips));
		}
		foreach ($urls as $index => $url) {
			$models['acl_url#' . $index] = array('revers' => 1, 'nc' => 1, 'url' => $url);
		}
		$models['acl_safeline_waf'] = array('timeout_ms' => 1000);
		$models['mark_status_code'] = array('code' => 451);
		// WHM's edit_chain upserts by name, so updating an active rule is atomic.
		return $this->access->editChain(self::ACTION, array('action' => 'allow', 'name' => self::ACTION), $models);
	}

	private function linkBegin()
	{
		return $this->access->editChain('BEGIN', array('action' => self::TABLENAME, 'name' => self::ACTION));
	}

	private function ensureTable()
	{
		$tables = $this->access->listTable();
		return $tables !== false && (in_array(self::ACTION, $tables, true) || $this->access->addTable(self::ACTION));
	}

	private function hasRule()
	{
		return (bool) $this->access->findChain(self::ACTION, self::ACTION);
	}

	private function readWhitelists()
	{
		$chains = $this->access->listChain(self::ACTION);
		if ($chains === false) {
			return false;
		}
		$ips = array();
		$urls = array();
		foreach ($chains->children() as $chain) {
			if ((string) $chain['name'] !== self::ACTION) {
				continue;
			}
			foreach ($chain->children() as $model) {
				if ($model->getName() === 'acl_srcs') {
					$ips = explode('|', (string) $model['v']);
				} elseif ($model->getName() === 'acl_url') {
					$urls[] = (string) $model['url'];
				}
			}
			break;
		}
		return array($ips, $urls);
	}

	private function parseIps($input)
	{
		if (!is_string($input) || strlen($input) > 8192) {
			return false;
		}
		$result = array();
		foreach (preg_split('/\r\n|\r|\n/', $input) as $line) {
			if (preg_match('/[\x00-\x1f\x7f]/', $line)) {
				return false;
			}
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$parts = explode('-', $line);
			if (count($parts) === 2) {
				$valid = filter_var($parts[0], FILTER_VALIDATE_IP) && filter_var($parts[1], FILTER_VALIDATE_IP)
					&& ((strpos($parts[0], ':') !== false) === (strpos($parts[1], ':') !== false));
			} elseif (strpos($line, '/') !== false) {
				$parts = explode('/', $line);
				$valid = count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP) && ctype_digit($parts[1]) && (int) $parts[1] <= (strpos($parts[0], ':') === false ? 32 : 128);
			} else {
				$valid = (bool) filter_var($line, FILTER_VALIDATE_IP);
			}
			if (!$valid || count($result) >= 100) {
				return false;
			}
			$result[] = $line;
		}
		return $result;
	}

	private function parseUrls($input)
	{
		if (!is_string($input) || strlen($input) > 8192) {
			return false;
		}
		$result = array();
		foreach (preg_split('/\r\n|\r|\n/', $input) as $line) {
			if (preg_match('/[\x00-\x1f\x7f]/', $line)) {
				return false;
			}
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			if (strlen($line) > 512 || count($result) >= 100) {
				return false;
			}
			$result[] = $line;
		}
		return $result;
	}

	private function sync()
	{
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
	}

	private function showMessage($message)
	{
		$this->_tpl->assign('msg', $message);
		return $this->_tpl->fetch('msg.html');
	}
}
