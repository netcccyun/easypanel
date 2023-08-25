<?php
class DnssyncAPI extends API
{
	private $bind_dir = '/vhs/bind/';
	private $ip_file = '/vhs/bind/etc/ip.conf';
	private $sync_host = 'http://www.kanglesoft.com/dns';

	public function syncViewAndIp()
	{
		$url = $this->sync_host . '/sync_ep_dns.php';
		$dns_sync_number = daocall('setting', 'get', array('dns_sync_number'));

		if (!$dns_sync_number) {
			$dns_sync_number = 0;
		}

		$post_arr['number'] = $dns_sync_number;
		$post_arr['ep_version'] = EASYPANEL_VERSION;
		$opts = array(
			'http' => array('method' => 'post', 'timeout' => 5, 'content' => http_build_query($post_arr))
			);
		$result = file_get_contents($url, false, stream_context_create($opts));

		if ($result === false) {
			trigger_error('网络错误 ' . $url . ' line ' . 27);
			return false;
		}

		if (trim($result) == '') {
			trigger_error('网络错误2 line ' . 31);
			return false;
		}

		$json = (array) json_decode($result);

		if (intval($json['status']) == 200) {
			if ($json['view']) {
				daocall('views', 'viewsDel', array());

				foreach ($json['view'] as $view) {
					$view = (array) $view;

					if (!daocall('views', 'viewsAdd', array($view))) {
						trigger_error('数据库无法更新  line ' . 41);
						return false;
					}
				}
			}

			if ($json['ip']) {
				if (file_exists($this->ip_file)) {
					$ip_tmp_file = $this->ip_file . '.tmp';
					$fp = fopen($ip_tmp_file, 'wt');

					if (!$fp) {
						trigger_error('无法写入临时文件' . $ip_tmp_file . ' line ' . 51);
						return false;
					}

					fwrite($fp, $json['ip']);
					fclose($fp);
					$check = apicall('bind', 'checkConf', array($ip_tmp_file));

					if ($check !== true) {
						exit(print_r($check));
					}

					@unlink($this->ip_file);

					if (!rename($ip_tmp_file, $this->ip_file)) {
						trigger_error('无法重命名文件' . $ip_tmp_file . ' line ' . 62);
						return false;
					}
				}
				else {
					$fp = fopen($this->ip_file, 'wt');

					if (!$fp) {
						trigger_error('无法写入文件 line ' . 68);
						return false;
					}

					fwrite($fp, $json['ip']);
					fclose($fp);
					$check = apicall('bind', 'checkConf', array($this->ip_file));

					if ($check !== true) {
						if ($check !== false) {
							trigger_error(@implode(' ', $check));
						}

						return false;
					}
				}
			}
		}

		daocall('setting', 'add', array('dns_sync_number', intval($json['number'])));
		return true;
	}

	public function getVersion()
	{
		$url = $this->sync_host . '/get_version.php';
		$result = file_get_contents($url, false);

		if ($result === false) {
			$json['code'] = 500;
			$json['error'] = '查询同步版本信息出错';
			return $json;
		}

		$result = (array) json_decode($result);

		if ($result['version'] == 0) {
			$json['code'] = 200;
			return $json;
		}

		$local_ver = daocall('setting', 'get', array('dns_sync_number'));
		if (!$local_ver || $local_ver < $result['version']) {
			$json['code'] = 200;
			return $json;
		}

		if ($local_ver == $result['version']) {
			$json['code'] = 400;
			$json['error'] = '已经是最新版本，不再需要更新';
		}

		return $json;
	}

	public function syncAllNOdeDns()
	{
		$nodes = daocall('slaves', 'slavesGet', array());

		if (count($nodes) <= 0) {
			return true;
		}

		foreach ($nodes as $node) {
			if (!$this->syncNodeDns($node)) {
				return false;
			}
		}

		return true;
	}

	public function syncNodeDns($node)
	{
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['slave'], '3312', $node['skey']));

		if (!$whm) {
			trigger_error('make whm faile:' . $node['slave']);
			return false;
		}

		$a = 'sync_record';
		$whmCall = $this->getWhmUrl($a);
		$info['domains'] = daocall('domains', 'domainList', array());

		if (count($info['domains']) <= 0) {
			return true;
		}

		$info = base64_encode(json_encode($info));
		$whmCall->addParam('info', $info);
		$result = $whm->callEpanel($whmCall, 5);
		if (!$result || $result->getCode() != 200) {
			if ($result) {
				trigger_error(@implode(' ', $result) . ' line ' . 145);
			}

			return false;
		}

		return true;
	}

	public function syncAllInit()
	{
		$views = daocall('views', 'viewsList', array());
		$nodes = daocall('slaves', 'slavesGet', array());

		if (count($nodes) <= 0) {
			return true;
		}

		foreach ($nodes as $node) {
			if (!$this->syncNodeInit($node, $views)) {
				return false;
			}
		}

		return true;
	}

	private function syncNodeInit($node, $views)
	{
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['slave'], '3312', $node['skey']));

		if (!$whm) {
			trigger_error('whm is make failed line ' . 169);
			return false;
		}

		$a = 'sync_init';
		$whmCall = $this->getWhmUrl($a);
		$info['views'] = $views;

		if (file_exists($this->ip_file)) {
			$info['ip'] = @file_get_contents($this->ip_file);
		}

		$info = base64_encode(json_encode($info));
		$whmCall->addParam('info', $info);
		$result = $whm->callEpanel($whmCall, 10);

		if (!$result) {
			trigger_error('make epanel call is false');
			return false;
		}

		if ($result->getCode() != 200) {
			trigger_error(@implode("\n", $result));
			return false;
		}

		return true;
	}

	private function getWhmUrl($a)
	{
		$whmCall = new WhmCall('api/', $a);
		$whmCall->addParam('c', 'dns');
		$whmCall->addParam('t', time());
		return $whmCall;
	}

	public function test_dns($node)
	{
		$a = 'test_dns';
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], 3312, $node['skey']));

		if (!$whm) {
			trigger_error('make whm is failed');
			return false;
		}

		$whmCall = $this->getWhmUrl($a);

		if ($whmCall === false) {
			trigger_error('whmcall is make error');
			return false;
		}

		$result = $whm->callEpanel($whmCall, 10);

		if ($result === false) {
			trigger_error('make epanel call is false');
			return false;
		}

		return $result;
	}
}

?>