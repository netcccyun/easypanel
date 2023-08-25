<?php
class WhmAPI extends API
{
	public function __construct()
	{
		load_lib('pub:whm');
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function getTemplete($node)
	{
	}

	public function refreshTemplete($host)
	{
		$node = daocall('nodes', 'getNode', array($host));
		if (!$node || $node == null) {
			return false;
		}

		return $this->refreshHostTemplete($node);
	}

	protected function refreshHostTemplete($node)
	{
		daocall('vhosttemplete', 'updateNodeState', array($node['name']));
		$whm = new WhmClient();
		$whm->setAuth($node['user'], $node['passwd']);
		$whm->setWhmUrl('http://' . $node['host'] . ':' . $node['port'] . '/core.whm');
		$call = new WhmCall('list_tvh');
		$result = $whm->call($call);

		if (!$result) {
			exit('failed');
			return false;
		}

		$templete = array();
		$i = 0;

		while (true) {
			$value = $result->get('name', $i);

			if (!$value) {
				break;
			}

			$templete[] = $value;
			++$i;
		}

		daocall('vhosttemplete', 'updateNodeTemplete', array($node['name'], $templete));
	}
}

?>