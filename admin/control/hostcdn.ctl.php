<?php
needRole('admin');
define(CDN_TABLE, '!cdn_table');
define(ACTION, 'table:!cdn_table');
define(BEGIN, 'BEGIN');
define(PROT, '80');
class HostcdnControl extends control
{
	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
	}

	public function hostcdn()
	{
		$this->addTable();
		$access = new Access();
		$result = $access->listChain(CDN_TABLE, 1);
		$id = 0;

		foreach ($result->children() as $chain) {
			$c = $chain->children();
			$cdns[] = array('v' => trim((string) $c[0], '|'), 'host' => (string) $c[1]['host'], 'port' => (string) $c[1]['port'], 'id' => $id++);
		}

		$this->assign('cdns', $cdns);
		return $this->_tpl->display('hostcdn/hostcdn.html');
	}

	public function addHostcdn()
	{
		$arr['action'] = 'continue';
		$v = $_REQUEST['v'];
		$host = $_REQUEST['host'];
		$port = $_REQUEST['port'] ? $_REQUEST['port'] : '80';
		if (!$host || !$v) {
			return header('Location: ?c=hostcdn&a=hostcdn');
		}

		$models['acl_host'] = array('v' => $v);
		$models['mark_host'] = array('host' => $host, 'port' => $port, 'proxy' => 1);
		$access = new Access();

		if ($access->addChain(CDN_TABLE, $arr, $models)) {
			if (!$access->findChain(BEGIN, CDN_TABLE)) {
				$access->addChain(BEGIN, array('action' => ACTION, 'name' => CDN_TABLE)) || exit('不能增加链，请重试');
			}

			return header('Location: ?c=hostcdn&a=hostcdn');
		}

		exit('增加失败');
	}

	public function delHostcdn()
	{
		$access = new Access();
		$id = $_REQUEST['id'];

		if (!$access->delChain(CDN_TABLE, $id)) {
			$this->_tpl->assign('msg', '删除失败!');
			return $this->fetch('msg.html');
		}

		return header('Location: ?c=hostcdn&a=hostcdn');
	}

	private function addTable()
	{
		$access = new Access();
		$tables = $access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == CDN_TABLE) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$access->addTable(CDN_TABLE)) {
				exit('不能增加表,请重试');
			}
		}
	}
}

?>