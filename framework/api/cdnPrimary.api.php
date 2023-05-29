<?php
class CdnPrimaryAPI extends API
{
	private $setting;
	private $cdn_prefix = '@';
	private $t;
	private $month;
	private $day;
	private $hour;
	private $local_vhs = null;
	private $local_vhs_loaded = false;
	private $loaded_vhs_info = array();
	private $acess_dir = '/vhs/kangle/cdn/';
	static private $sync_vhost_fields = array('name', 'doc_root', 'uid', 'status', 'subdir', 'web_quota', 'log_file', 'access', 'speed_limit', 'cdn', 'sync_seq', 'ip', 'port', 'certificate', 'certificate_key', 'http2');

	public function __construct()
	{
		load_lib('pub:whm');
		load_lib('pub:flow');
		$this->setting = daocall('setting', 'getAll', array());
		$this->t = date('YmdH', time());
		$this->month = substr($this->t, 0, 6);
		$this->day = substr($this->t, 0, 8);
		$this->hour = $this->t;
		parent::__construct();
	}

	/**
	 * 后台的方式同步一个虚拟主机
	 * 注意:不要直接用此方法，此方法由vhost.api.php里面调用。
	 * 直接用notice_cdn_changed()方法。
	 * @param $name name可以用(+-开头)
	 */
	public function daemon_sync($name)
	{
		$result = apicall('process', 'daemon', array(
	'cdn_sync',
	array('arg1' => $name),
	$name . "\n"
	));
	}

	/**
	 * 同步任意个操作,注意，不要直接使用此方法，此方法应该由shell.api.php调用
	 * @param $names 操作列表，如果以+开头，则sync,以-开头，则删除
	 * 以~开头，表示清理url缓存
	 */
	public function sync(array $names)
	{
		$nodes = daocall('manynode', 'get');
		$sync_vhost = array();
		$del_vhost = array();
		$url = array();

		foreach ($names as $name) {
			$op = $name[0];

			if ($op == '-') {
				$del_vhost[] = substr($name, 1);
			}
			else if ($op == '+') {
				$sync_vhost[] = substr($name, 1);
			}
			else if ($op == '~') {
				$url[] = substr($name, 1);
			}
			else {
				if (0 < strlen($name)) {
					$sync_vhost[] = $name;
				}
			}
		}

		if (0 < count($sync_vhost) || 0 < count($del_vhost)) {
			$lvhs = $this->load_local_vhs();
		}

		foreach ($nodes as $node) {
			$this->sync_node_vhost($node, $lvhs, $sync_vhost, $del_vhost, $url);
		}

		return true;
	}

	/**
	 * 同步一个节点
	 */
	public function sync_node($node)
	{
		if (!is_array($node)) {
			$node = daocall('manynode', 'get', array($node));

			if (!$node) {
				return false;
			}
		}

		$version = $this->get_version($node);
		if ($version === false || $version < 20402) {
			echo 'warning: node ' . $node['name'] . " is old. please upgrade.\nnow use old function to sync\n";
			return apicall('cdn', 'sync_old_node', array($node));
		}

		$vhs = $this->get_vhost_info($node);

		if (is_array($vhs)) {
			$this->insert_flow($vhs, 'global.db');
		}

		$lvhs = $this->load_local_vhs();
		$sync_vhs = $this->getSyncVhostResult($lvhs, $vhs);
		return $this->sync_node_vhost($node, $this->local_vhs, $sync_vhs['sync'], $sync_vhs['del']);
	}

	/**
	 * 同步所有节点所有虚拟主机
	 */
	public function sync_all()
	{
		$this->sync_localhost_flow('global.db');
		$nodes = daocall('manynode', 'get');

		foreach ($nodes as $node) {
			$this->sync_node($node);
		}
	}

	private function get_version($node)
	{
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], $node['port'], $node['skey']));

		if (!$whm) {
			echo 'cann\'t makeEpanelWhm for node: ' . $node['name'] . "\n";
			return false;
		}

		$whmCall = $this->newCdnCall('get_version');
		$result = $whm->callEpanel($whmCall, 60);

		if ($result === false) {
			return false;
		}

		if ($result->getCode() != 200) {
			return false;
		}

		return $result->get('version');
	}

	private function get_vhost_info($node)
	{
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], $node['port'], $node['skey']));

		if (!$whm) {
			echo 'cann\'t makeEpanelWhm for node: ' . $node['name'] . "\n";
			return false;
		}

		$whmCall = $this->newCdnCall('get_vhost_info');
		$result = $whm->callEpanel($whmCall, 120);

		if ($result === false) {
			return false;
		}

		if ($result->getCode() != 200) {
			return false;
		}

		return $result->getJson('vhs');
	}

	private function newCdnCall($a)
	{
		if (!$this->setting['local_cdn_name']) {
			echo "no setting local_cdn_name\n";
			return false;
		}

		$whmCall = new WhmCall('api/', $a);
		$whmCall->addParam('c', 'cdn');
		$whmCall->addParam('nodename', $this->setting['local_cdn_name']);
		return $whmCall;
	}

	/**
	 * 插入流量
	 * @param $vhs
	 * @param $db_name
	 */
	private function insert_flow($vhs, $db_name)
	{
		load_lib('pub:flow');
		$flowobj = new flow($db_name);

		if (!$GLOBALS['clean_flow_flag']) {
			$flowobj->clean();
			$GLOBALS['clean_flow_flag'] = 1;
		}

		$flowobj->getPdo()->beginTransaction();

		foreach ($vhs as $name => $vh) {
			if ($vh['flow_limit'] <= 0) {
				continue;
			}

			$flowobj->addFlow('flow_hour', $name, $this->hour, $vh['flow_limit'], $vh['flow_cache']);
			$flowobj->addFlow('flow_day', $name, $this->day, $vh['flow_limit'], $vh['flow_cache']);
			$flowobj->addFlow('flow_month', $name, $this->month, $vh['flow_limit'], $vh['flow_cache']);
		}

		$flowobj->getPdo()->commit();
	}

	/**
	 * 处理本地流量
	 * @param unknown_type $db_name
	 * @return boolean
	 */
	private function sync_localhost_flow($db_name)
	{
		$result = apicall('vhost', 'getFlow', array('@', 1));

		if (!$result) {
			echo "sync_host_flow not result\r\n";
			return false;
		}

		$flows = (string) $result->get('flow', 0);
		$lines = explode("\n", $flows);

		foreach ($lines as $line) {
			$item = explode('	', $line);
			if (strcasecmp($item[0][0], '@') == 0 && ($s = explode(':', $item[0]))) {
				$name = $s[1];
			}
			else {
				$name = $item[0];
			}

			$flow = $item[1];
			$flow_cache = $item[2];
			$vhs[$name] = array('flow_limit' => $flow, 'flow_cache' => $flow_cache);
		}

		return $this->insert_flow($vhs, $db_name);
	}

	/**
	 * 加载本地虚拟主机
	 * @return NULL
	 */
	private function load_local_vhs()
	{
		if ($this->local_vhs_loaded) {
			return $this->local_vhs;
		}

		$this->local_vhs_loaded = true;
		$vhs = daocall('vhost', 'listVhostNotcdn', array($this->cdn_prefix, CdnPrimaryAPI::$sync_vhost_fields));

		foreach ($vhs as $vh) {
			$this->local_vhs[$vh['name']] = $vh;
		}

		return $this->local_vhs;
	}

	/**
	 * 得到同步信息
	 * @param $local_vhs 本地vhs
	 * @param $remote_vhs 远程vhs
	 * @return array('sync'=>需同步列表,'del'=>需删除列表);
	 */
	private function getSyncVhostResult($local_vhs, $remote_vhs)
	{
		foreach ($local_vhs as $vh) {
			if (isset($remote_vhs[$vh['name']])) {
				if (intval($vh['sync_seq']) != intval($remote_vhs[$vh['name']]['sync_seq'])) {
					$sync[] = $vh['name'];
				}
			}
			else {
				$sync[] = $vh['name'];
			}
		}

		if (is_array($remote_vhs)) {
			foreach ($remote_vhs as $vh) {
				if (!isset($local_vhs[$vh['name']])) {
					$del[] = $vh['name'];
				}
			}
		}

		$ret['sync'] = $sync;
		$ret['del'] = $del;
		return $ret;
	}

	/**
	 * 加载虚拟主机附加信息
	 * @param  $name 虚拟主机名称
	 * @param  $vhs  虚拟主机列表
	 * @return multitype:
	 */
	private function load_vhost_info($name, $vhs)
	{
		if (isset($this->loaded_vhs_info[$name])) {
			return $this->loaded_vhs_info[$name];
		}

		$infos = daocall('vhostinfo', 'getAll', array($name));
		$info2 = array();
		$vh = $vhs[$name];

		foreach ($infos as $info) {
			if ($info['type'] == 2 || $info['type'] == 3 || $info['type'] == 4 || $info['type'] == 5 || $info['type'] == 7) {
				continue;
			}
			if ($info['type'] == 0 && strncasecmp($info['value'], 'server://', 9) == 0 && strpos($info['value'],';') && strpos($info['value'],'.crt') && strpos($info['value'],'.key')){
				$certificate_file = $info['name'] . '.crt';
				$fp = @fopen($vh['doc_root'] . '/' . $certificate_file, 'rb');
				if ($fp) {
					$data = stream_get_contents($fp);
					$this->loaded_vhs_info[$name]['certs'][$certificate_file] = base64_encode($data);
					fclose($fp);
				}
				$certificate_key_file = $info['name'] . '.key';
				$fp = @fopen($vh['doc_root'] . '/' . $certificate_key_file, 'rb');
				if ($fp) {
					$data = stream_get_contents($fp);
					$this->loaded_vhs_info[$name]['certs'][$certificate_key_file] = base64_encode($data);
					fclose($fp);
				}
			}

			$info2[] = $info;
		}

		$this->loaded_vhs_info[$name]['info'] = $info2;

		if ($vh['access']) {
			$fp = @fopen($vh['doc_root'] . '/' . $vh['access'], 'rb');

			if ($fp) {
				$data = stream_get_contents($fp);
				$this->loaded_vhs_info[$name]['access'] = base64_encode($data);
				fclose($fp);
			}
		}

		if ($vh['certificate']) {
			$fp = @fopen($vh['doc_root'] . '/' . $vh['certificate'], 'rb');

			if ($fp) {
				$data = stream_get_contents($fp);
				$this->loaded_vhs_info[$name]['certificate'] = base64_encode($data);
				fclose($fp);
			}
		}

		if ($vh['certificate_key']) {
			$fp = @fopen($vh['doc_root'] . '/' . $vh['certificate_key'], 'rb');

			if ($fp) {
				$data = stream_get_contents($fp);
				$this->loaded_vhs_info[$name]['certificate_key'] = base64_encode($data);
				fclose($fp);
			}
		}

		return $this->loaded_vhs_info[$name];
	}

	/**
	 * cdn同步核心函数
	 * @param  $node 节点数组
	 * @param  $vhs  虚拟主机列表 array('name'=>vh)形式
	 * @param  $sync_vhost 需要同步的列表
	 * @param  $del_vhost  需要删除的列表
	 * @return boolean
	 */
	private function sync_node_vhost($node, $vhs, $sync_vhost, $del_vhost, $remove_cache_url = null)
	{
		if (is_array($sync_vhost)) {
			foreach ($sync_vhost as $name) {
				if(strpos($name,'@')!==false)continue;
				$vh = $this->load_vhost_info($name, $vhs);
				$vh['v'] = $vhs[$name];
				$sync[] = $vh;
			}
		}

		if (is_array($del_vhost)) {
			foreach ($del_vhost as $name) {
				$del[] = $name;
			}
		}

		if (is_array($remove_cache_url)) {
			foreach ($remove_cache_url as $u) {
				$url[] = $u;
			}
		}

		if (!isset($sync) && !isset($del) && !isset($url)) {
			return false;
		}

		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], $node['port'], $node['skey']));

		if (!$whm) {
			return false;
		}

		$whmCall = $this->newCdnCall('sync');
		$whmCall->addParam('sync', json_encode($sync));
		$whmCall->addParam('del', json_encode($del));
		$whmCall->addParam('url', json_encode($url));
		$result = $whm->callEpanel($whmCall, 120);
		if (is_object($result) && $result->getCode() == 200) {
			$this->updateNodeStatus($node['name'], 1);
		}
		else {
			$this->updateNodeStatus($node['name'], 2);
		}

		return $result;
	}

	private function updateNodeStatus($host, $status)
	{
		daocall('manynode', 'updateManynode', array(
	$host,
	array('synctime' => time(), 'syncstatus' => $status)
	));
	}
}

?>