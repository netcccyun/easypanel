<?php
class CrontabAPI extends API
{
	/**
	 * 每日运行
	 */
	public function runDay()
	{
		daocall('vhost', 'expireUser');
		$nodes = daocall('nodes', 'listNodes');
		$i = 0;

		while ($i < count($nodes)) {
			$this->reloadNode($nodes[$i]);
			++$i;
		}
	}

	public function runHour()
	{
	}

	private function reloadNode($node)
	{
		$whm = apicall('nodes', 'makeWhm2', array($node['host'], $node['port'], $node['user'], $node['passwd']));
		$whmCall = new WhmCall('core.whm', 'reload_all_vh');
		return $whm->call($whmCall, 10);
	}
}

?>