<?php
needRole('vhost');
define(DENY_PATH_TABLE, '!deny_referer');
define(ACTION, 'table:!deny_referer');
define(BEGIN, 'BEGIN');
class ProtectionControl extends Control
{
	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
	}

	public function addProtectionTableFrom()
	{
		$check_result = apicall('access', 'checkAccess', array());

		if ($check_result !== true) {
			return $this->show_msg($check_result);
		}

		$this->addProtectionTable();
		$access = new Access(getRole('vhost'));
		$access->addTable(BEGIN);
		$result = $access->listChain(DENY_PATH_TABLE, 1);
		$id = 0;

		if ($result) {
			foreach ($result->children() as $chain) {
				foreach ($chain as $ch) {
					if ($ch->getName() == 'acl_reg_path') {
						$paths[] = array('path' => (string) $ch['path'], 'referer' => (string) $ch['referer'], 'id' => $id++);
					}
				}
			}
		}

		$chains = $access->findChain(BEGIN, DENY_PATH_TABLE);

		if ($chains) {
			$this->assign('name', DENY_PATH_TABLE);
		}

		$this->assign('paths', $paths);
		return $this->fetch('protection/add.html');
	}

	private function addProtectionTable()
	{
		$access = new Access(getRole('vhost'));
		$tables = $access->listTable();
		$table_finded = false;

		if ($tables) {
			foreach ($tables as $table) {
				if ($table == BEGIN) {
					if ($table == DENY_PATH_TABLE) {
						$table_finded = true;
						break;
					}
				}
			}
		}

		if (!$table_finded) {
			$access->addTable(BEGIN);
			$access->addTable(DENY_PATH_TABLE);
			return true;
		}

		return false;
	}

	public function delPath()
	{
		$access = new Access(getRole('vhost'));
		$id = intval($_REQUEST['id']);

		if (!$access->delChain(DENY_PATH_TABLE, $id)) {
			exit('删除规则失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function addPath()
	{
		$path = filterParam($_REQUEST['path'], 'path');

		if ($path == '') {
			return $this->show_msg('目录不能为空');
		}

		$models['acl_reg_path'] = array('path' => $path);

		if ($_REQUEST['referer'] == '') {
			$models['acl_referer'] = array('referer' => 'EqHost', 'revers' => 1);
		}
		else {
			$models['acl_header'] = array('header' => 'Referer', 'val' => $_REQUEST['referer'], 'regex' => 1, 'revers' => 1);
		}

		if ($_REQUEST['redirect']) {
			$models['mark_redirect'] = array('dst' => $_REQUEST['redirect'], 'internal' => 0, 'code' => 302);
		}

		$arr['action'] = 'deny';
		$access = new Access(getRole('vhost'));

		if (!$access->addChain(DENY_PATH_TABLE, $arr, $models)) {
			exit('增加规则失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function emptyAllSettin()
	{
		$access = new Access(getRole('vhost'));
		$access->emptyTable(DENY_PATH_TABLE);
		$access->delChainByName(BEGIN, DENY_PATH_TABLE);
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function startHotlinking()
	{
		$this->addProtectionTable();
		$access = new Access(getRole('vhost'));
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 0:
			$access->delChainByName(BEGIN, DENY_PATH_TABLE);
			break;

		case 1:
			if ($access->findChain(BEGIN, DENY_PATH_TABLE) == false) {
				$arr['action'] = ACTION;
				$arr['name'] = DENY_PATH_TABLE;

				if (!$access->addChain(BEGIN, $arr)) {
					exit('不能增加链');
				}

				break;
			}

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>