<?php
class WebappAPI extends API
{
	public function getDomainInfo($vhost)
	{
		$node = apicall('vhost', 'getNode', array($vhost));

		if (!$node) {
			return false;
		}

		$whm = apicall('nodes', 'makeWhm', array($node));
		$whmCall = new WhmCall('core.whm', 'info_domain');
		$whmCall->addParam('name', $vhost);
		return $whm->call($whmCall, 10);
	}

	public function getPhyDir($vhost, $domain, $dir)
	{
		$node = apicall('vhost', 'getNode', array($vhost));

		if (!$node) {
			return false;
		}

		$whm = apicall('nodes', 'makeWhm', array($node));
		$whmCall = new WhmCall('core.whm', 'info_vh');
		$whmCall->addParam('name', $vhost);
		$result = $whm->call($whmCall, 10);

		if (!$result) {
			return false;
		}

		$add_dir = $result->get('add_dir');
		$hosts = $result->get('host');
		$host = explode("\n", $hosts);
		$finded = false;

		foreach ($host as $h) {
			$hi = explode('|', $h);

			if (trim($hi[0]) == $domain) {
				$finded = true;
				$subdir = $hi[1];
				break;
			}
		}

		if (!$finded) {
			return false;
		}

		$phy_dir = $subdir . $add_dir;

		if ($dir[0] != '/') {
			$phy_dir .= '/';
		}

		$phy_dir .= $dir;
		return $phy_dir;
	}

	public function getInfo($appid)
	{
		$url = 'http://webapp.kanglesoft.com/admin/?c=webapp&a=info&appid=' . $appid;
		$opts = array(
			'http' => array('method' => 'GET', 'timeout' => 10)
			);
		$msg = @file_get_contents($url, false, stream_context_create($opts));

		if ($msg === FALSE) {
			$this->err_msg = 'cann\'t connect to host';
			return false;
		}

		$whm = new DOMDocument();

		if (!$whm->loadXML($msg)) {
			$this->err_msg = 'cann\'t parse whm xml';
			return false;
		}

		$result_node = $whm->getElementsByTagName('result')->item(0);
		$status = $result_node->attributes->getNamedItem('status')->nodeValue;

		if (intval($status) != 200) {
			$this->err_msg = $status;
			return false;
		}

		$nodes = $result_node->childNodes;
		$result = array();
		$i = 0;

		while (true) {
			$node = $nodes->item($i);

			if (!$node) {
				break;
			}

			if ($node->nodeType != 1) {
				continue;
			}

			$result[$node->nodeName] = $node->nodeValue;
			++$i;
		}

		return $result;
	}
}

?>