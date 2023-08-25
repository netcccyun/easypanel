<?php
needRole('vhost');
define(DENY_BANIP_TABLE, '!cdn');
define(ACTION, 'table:!cdn');
define(BEGIN, 'BEGIN');
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

		foreach ($result->children() as $chain) {
			$ips[] = array('expire' => $chain['expire'], 'ip' => (string) $chain->children(), 'id' => $id++);
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

		foreach ($tables as $table) {
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
				$arr['action'] = ACTION;
				$arr['name'] = DENY_BANIP_TABLE;

				if (!$access->addChain(BEGIN, $arr)) {
					exit('不能增加链');
				}
			}

			return header('Location: ?c=cdn&a=addTableFrom');
		}

		if ($status == 2) {
			$access->delChainByName(BEGIN, DENY_BANIP_TABLE);
			return header('Location: ?c=cdn&a=addTableFrom');
		}

		return false;
	}

	public function addBanip()
	{
		$ip = $_REQUEST['ip'];

		if (!$this->checkIp($ip)) {
			exit('请输入正确的IP地址');
		}

		$lifetime = $_REQUEST['life_time'];

		if (0 < $lifetime) {
			$expire = time() + $lifetime * 60;
		}

		$models['acl_src'] = array('ip' => $ip);
		$arr['action'] = 'deny';
		$arr['expire'] = $expire;
		$access = new Access(getRole('vhost'));

		if (!$access->addChain(DENY_BANIP_TABLE, $arr, $models)) {
			return false;
		}

		return $this->addTableFrom();
	}

	public function delBanip()
	{
		$id = intval($_REQUEST['id']);
		$access = new Access(getRole('vhost'));

		if (!$access->delChain(DENY_BANIP_TABLE, $id)) {
			$this->_tpl->assign('msg', '删除失败');
			return $this->fecth('msg.html');
		}

		return $this->addTableFrom();
	}

	private function checkIp($str)
	{
		$strs = explode('.', $str);
		$count = count($strs);

		if ($count != 4) {
			return false;
		}

		$i = 0;

		while ($i < $count - 1) {
			if (!is_numeric($strs[$i])) {
				return false;
			}

			++$i;
		}

		if (strpos($strs[3], '/')) {
			$strr = explode('/', $strs[3]);
			if (!is_numeric($strr[0]) || !is_numeric($strr[1])) {
				return false;
			}
		}
		else {
			if (!is_numeric($strs[3])) {
				return false;
			}
		}

		return true;
	}
}

?>