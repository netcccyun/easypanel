<?php
needRole('vhost');
define('DENY_BANIP_TABLE', '!cdn');
define('ACTION', 'table:!cdn');
define('BEGIN', 'BEGIN');
class CdnControl extends Control
{
	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
	}

	public function addTableFrom()
	{
		$this->addTable(DENY_BANIP_TABLE);
		$access = new Access(getRole('vhost'));
		$result = $access->listChain(DENY_BANIP_TABLE, 1);

		if ($access->findChain(BEGIN, DENY_BANIP_TABLE) != null) {
			$at = 1;
		}
		else {
			$at = 0;
		}

		$id = 0;
		$ips = array();

		if ($result) {
			foreach ($result->children() as $chain) {
				$ips[] = array('ip' => (string) $chain->children(), 'id' => $id++);
			}
		}

		$this->assign('at', $at);
		$this->assign('ips', $ips);
		return $this->_tpl->fetch('cdn/addFrom.html');
	}

	private function addTable($tablename)
	{
		$access = new Access(getRole('vhost'));
		$tables = $access->listTable();
		$table_finded = false;

		foreach (ep_iter($tables) as $table) {
			if ($table == $tablename) {
				$table_finded = true;
				break;
			}
		}

		if ($table_finded === false) {
			if (!$access->addTable($tablename)) {
				exit('不能增加表');
			}
		}

		return true;
	}

	public function switchIp()
	{
		$status = intval($_REQUEST['status']);
		$access = new Access(getRole('vhost'));

		if (empty($status)) {
			return false;
		}

		if ($status == 1) {
			$this->addTable(DENY_BANIP_TABLE);
			$this->addTable(BEGIN);

			if ($access->findChain(BEGIN, DENY_BANIP_TABLE) == null) {
				$arr = array();
				$arr['action'] = ACTION;
				$arr['name'] = DENY_BANIP_TABLE;

				if (!$access->addChain(BEGIN, $arr)) {
					exit('不能增加链');
				}

				$access->sortBeginChains();
			}

			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			return header('Location: ?c=cdn&a=addTableFrom');
		}

		if ($status == 2) {
			if (!$access->delChainByName(BEGIN, DENY_BANIP_TABLE)) {
				exit('关闭失败');
			}
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			return header('Location: ?c=cdn&a=addTableFrom');
		}

		return false;
	}

	public function addBanip()
	{
		$ip = trim(ep_request('ip'));

		if (!$this->checkIp($ip)) {
			exit('请输入正确的IP地址');
		}

		$models = array();
		$models['acl_src'] = array('ip' => $ip);
		$arr['action'] = 'deny';
		$access = new Access(getRole('vhost'));

		if (!$access->addChain(DENY_BANIP_TABLE, $arr, $models)) {
			return false;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		return $this->addTableFrom();
	}

	public function delBanip()
	{
		$id = intval($_REQUEST['id']);
		$access = new Access(getRole('vhost'));

		if (!$access->delChain(DENY_BANIP_TABLE, $id)) {
			$this->_tpl->assign('msg', '删除失败');
			return $this->fetch('msg.html');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		return $this->addTableFrom();
	}

	private function checkIp($str)
	{
		$parts = explode('/', $str, 2);
		if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			return false;
		}

		return count($parts) === 1 || ($parts[1] !== '' && ctype_digit($parts[1]) && intval($parts[1]) <= 32);
	}
}
