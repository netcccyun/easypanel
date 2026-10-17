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
		$enabled = (bool) $this->access->findChain('BEGIN', self::ACTION);
		$this->_tpl->assign('enabled', $enabled ? 1 : 0);
		return $this->_tpl->fetch('webwaf/webwaf.html');
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
			if (!$this->addRule()) {
				exit('初始化Web安全防护设置失败');
			}
			if (!$this->linkBegin()) {
				exit('开启Web安全防护失败');
			}
		} elseif ($status === 2 && $enabled) {
			if (!$this->access->delChainByName('BEGIN', self::ACTION)) {
				exit('关闭Web安全防护失败');
			}
		}
		$this->sync();
		exit('成功');
	}

	private function addRule()
	{
		$models = array();
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
