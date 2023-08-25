<?php
needRole('vhost');
define(DENY_BANIP_TABLE, '!deny_vhost_ip');
define(DENY_BANURL_TABLE, '!deny_url');
define(ACTION, 'table:!deny_vhost_ip');
define(ACTION2, 'table:!deny_url');
define(BEGIN, 'BEGIN');
class BanipControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function addTableFrom()
	{
		$check_result = apicall('access', 'checkAccess', array());

		if ($check_result !== true) {
			return $this->show_msg($check_result);
		}

		$this->addTable(DENY_BANIP_TABLE);
		$this->addTable(DENY_BANURL_TABLE);
		$result = $this->access->listChain(DENY_BANIP_TABLE, 1);
		$result2 = $this->access->listChain(DENY_BANURL_TABLE, 1);

		if ($this->access->findChain(BEGIN, DENY_BANIP_TABLE) != false) {
			$this->assign('banip', 1);
		}

		if ($this->access->findChain(BEGIN, DENY_BANURL_TABLE) != false) {
			$this->assign('banurl', 1);
		}

		$id = 0;

		foreach ($result->children() as $chain) {
			$ips[] = array('expire' => $chain['expire'], 'ip' => (string) $chain->children(), 'id' => $id++);
		}

		$this->assign('ips', $ips);

		$id = 0;

		foreach ($result2->children() as $chain) {
			$meth = '全部';
			$url = null;
			foreach ($chain->children() as $name=>$ch) {
				if($name == 'acl_url'){
					$url = $ch;
				}elseif($name == 'acl_meth'){
					$meth = $ch[0];
					if((string)$ch['revers'] == '1')$meth = '!'.$meth;
				}
			}
			if($url){
				$urls[] = array('url' => $url, 'meth' => $meth, 'id' => $id++);
			}
		}

		$this->assign('urls', $urls);
		return $this->_tpl->fetch('banip/addFrom.html');
	}

	private function addTable($tablename)
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == $tablename) {
				$table_finded = true;
				break;
			}
		}

		if ($table_finded === false) {
			if (!$this->access->addTable($tablename)) {
				return $this->show_msg('不能增加表');
			}
		}

		return true;
	}

	public function switchIp()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			$this->addTable(DENY_BANIP_TABLE);
			$this->addTable(BEGIN);

			if ($this->access->findChain(BEGIN, DENY_BANIP_TABLE) == false) {
				$arr['action'] = ACTION;
				$arr['name'] = DENY_BANIP_TABLE;

				if (!$this->access->addChain(BEGIN, $arr)) {
					return $this->show_msg('不能增加链');
				}
			}

			break;

		case 2:
			$this->access->delChainByName(BEGIN, DENY_BANIP_TABLE);
			break;

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function addBanip()
	{
		$ip = trim($_REQUEST['ip']);

		if (!$ip) {
			exit('IP地址不能为空');
		}

		$lifetime = intval($_REQUEST['life_time']);

		if (0 < $lifetime) {
			$expire = time() + $lifetime * 60;
		}

		$models['acl_src'] = array('ip' => $ip);
		$arr['action'] = 'deny';
		$arr['expire'] = $expire ? $expire : $lifetime;

		if (!$this->access->addChain(DENY_BANIP_TABLE, $arr, $models)) {
			exit('增加失败');
		}

		exit('成功');
	}

	public function delBanip()
	{
		$id = intval($_REQUEST['id']);

		if (!$this->access->delChain(DENY_BANIP_TABLE, $id)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function delBanipAll()
	{
		if (!$this->access->emptyTable(DENY_BANIP_TABLE)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function switchUrl()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			$this->addTable(DENY_BANURL_TABLE);
			$this->addTable(BEGIN);

			if ($this->access->findChain(BEGIN, DENY_BANURL_TABLE) == false) {
				$arr['action'] = ACTION2;
				$arr['name'] = DENY_BANURL_TABLE;

				if (!$this->access->addChain(BEGIN, $arr)) {
					return $this->show_msg('不能增加链');
				}
			}

			break;

		case 2:
			$this->access->delChainByName(BEGIN, DENY_BANURL_TABLE);
			break;

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function addBanurl()
	{
		$url = trim($_REQUEST['url']);
		$meth = trim($_REQUEST['meth']);

		if (!$url) {
			exit('URL不能为空');
		}
		
		if(!empty($meth)){
			if(substr($meth,0,1)=='!'){
				$meth = substr($meth,1);
				$models['acl_meth'] = array('revers' => 1, 'meth' => $meth);
			}else{
				$models['acl_meth'] = array('meth' => $meth);
			}
		}
		$models['acl_url'] = array('url' => $url, 'nc' => 1);
		$arr['action'] = 'deny';

		if (!$this->access->addChain(DENY_BANURL_TABLE, $arr, $models)) {
			exit('增加失败');
		}

		exit('成功');
	}

	public function delBanurl()
	{
		$id = intval($_REQUEST['id']);

		if (!$this->access->delChain(DENY_BANURL_TABLE, $id)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function delBanurlAll()
	{
		if (!$this->access->emptyTable(DENY_BANURL_TABLE)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	private function checkIp($ip)
	{
		if (!filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			return false;
		}

		return true;
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>