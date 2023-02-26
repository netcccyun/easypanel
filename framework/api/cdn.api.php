<?php
class CdnAPI extends API
{
	private $setting;
	private $cdn_prefix = '@';
	private $cdndir = 'cdn';

	public function __construct()
	{
		$this->setting = daocall('setting', 'getAll', array());
	}

	/**
	 * 删了作辅节点的同步自定义控制文件
	 * @param unknown_type $vhostname
	 *2013-5-21
	 */
	public function delCdnAccessFile($vhostname)
	{
		$file = $GLOBALS['safe_dir'] . '../' . $this->cdndir . '/' . $vhostname . '.xml';
		unlink($file);
	}

	/**
	 *
	 * Enter description here ...
	 * @param string $vhostname
	 */
	public function sync_vhost_all($vhostname = null)
	{
		$nodes = daocall('manynode', 'get');

		if (count($nodes) <= 0) {
			return false;
		}

		$vhost = $vhostname ? $vhostname : getRole('vhost');

		if (!$vhost) {
			return false;
		}

		$vhostinfo = daocall('vhost', 'getVhost', array($vhost));
		$poststr['info'] = base64_encode(json_encode($vhostinfo));
		$domains = daocall('vhostinfo', 'getAll', array($vhost));

		if (0 < count($domains)) {
			$domains = base64_encode(json_encode($domains));
			$poststr['domain'] = $domains;
		}

		$xmlfile = $vhostinfo['doc_root'] . '/' . $vhostinfo['access'];

		if (file_exists($xmlfile)) {
			$poststr['access'] = base64_encode(file_get_contents($xmlfile));
		}

		foreach ($nodes as $node) {
			$this->sync_cdn($node, $poststr);
		}
	}

	public function sync_old_node($node, $vhostname = null)
	{
		$vhost = $vhostname ? $vhostname : getRole('vhost');

		if (!$vhost) {
			return false;
		}

		$vhostinfo = daocall('vhost', 'getVhost', array($vhost));
		$poststr['info'] = base64_encode(json_encode($vhostinfo));
		$domains = daocall('vhostinfo', 'getAll', array($vhost));

		if (0 < count($domains)) {
			$domains = base64_encode(json_encode($domains));
			$poststr['domain'] = $domains;
		}

		$xmlfile = $vhostinfo['doc_root'] . '/' . $vhostinfo['access'];

		if (file_exists($xmlfile)) {
			$poststr['access'] = base64_encode(file_get_contents($xmlfile));
		}

		$this->sync_cdn($node, $poststr);
	}

	public function test_node($node)
	{
		$a = 'test';
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], 3312, $node['skey']));

		if (!$whm) {
			trigger_error('make whm is failed');
			return false;
		}

		$whmCall = $this->getWhmUrl($node, $a);

		if ($whmCall === false) {
			trigger_error('whmcall is make error');
			return false;
		}

		$result = $whm->callEpanel($whmCall, 10);
		if ($result && $result->getCode() == 200) {
			return true;
		}

		return false;
	}

	/**
	 *
	 * @param array $nodes
	 * @param string $vhost
	 */
	public function sync_vhost_cdn($node, $vhostname)
	{
		$vhost = $vhostname ? $vhostname : getRole('vhost');
		$vhostinfo = daocall('vhost', 'getVhost', array($vhost));
		$vhostinfo['passwd'] = md5('sync_vhost_cdn____+++11+___');
		$poststr['info'] = base64_encode(json_encode($vhostinfo));
		$domains = daocall('vhostinfo', 'getAll', array($vhost));

		if (0 < count($domains)) {
			$domains = base64_encode(json_encode($domains));
			$poststr['domain'] = $domains;
		}

		$xmlfile = $vhostinfo['doc_root'] . '/' . $vhostinfo['access'];

		if (file_exists($xmlfile)) {
			$poststr['access'] = base64_encode(file_get_contents($xmlfile));
		}

		if (!$this->test_node($node)) {
			return false;
		}

		return $this->sync_cdn($node, $poststr);
	}

	/**
	 * 删除一个辅节点的所有CDN
	 * @param array $node
	 */
	public function del_node($node)
	{
		$a = 'del_all_cdn';
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], 3312, $node['skey']));

		if (!$whm) {
			return false;
		}

		$whmCall = $this->getWhmUrl($node, $a);
		$result = $whm->callEpanel($whmCall, 10);
		if ($result && $result->getCode() == 200) {
			return true;
		}

		return false;
	}

	/**
	 * 删除一个CDN，所有辅节点
	 * Enter description here ...
	 * @param  $vhost
	 * @param  $nodes
	 */
	public function del_cdn($vhost, $nodes = array())
	{
		$nodes = $nodes ? $nodes : daocall('manynode', 'get', array());

		if (count($nodes) <= 0) {
			return false;
		}

		foreach ($nodes as $node) {
			$this->del_node_cdn($node, $vhost);
		}

		return true;
	}

	/**
	 * 删除一台服务器，一个CDN
	 * @param array $node
	 * @param string $vhost
	 */
	public function del_node_cdn($node, $vhost)
	{
		$a = 'del_cdn';
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], 3312, $node['skey']));

		if (!$whm) {
			return false;
		}

		$whmCall = $this->getWhmUrl($node, $a);

		if (!$whmCall) {
			return false;
		}

		$whmCall->addParam('vhost', $vhost);
		$result = $whm->callEpanel($whmCall, 10);
		if ($result && $result->getCode() == 200) {
			return true;
		}

		return false;
	}

	/**
	 * ajax
	 * @param array $node
	 * @param array $poststr $poststr['access'],$poststr['info'],$poststr['domain']
	 */
	public function sync_cdn($node = array(), $poststr = array())
	{
		$a = 'sync_vhost_cdn';
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], 3312, $node['skey']));

		if (!$whm) {
			$GLOBALS['sync_cdn_result'] = 'cdn.api.sync_cdn makeEpanelWhm false:host=' . $node['host'] . ' &key=' . $node['skey'];
			return false;
		}

		$whmCall = $this->getWhmUrl($node, $a);

		if (!$whmCall) {
			$GLOBALS['sync_cdn_result'] = 'cdn.api.sync_cdn make whmCall false:whmCall=' . implode(' ', $whmCall);
			return false;
		}

		$whmCall->addParam('access', $poststr['access']);
		$whmCall->addParam('info', $poststr['info']);
		$whmCall->addParam('domain', $poststr['domain']);
		$result = $whm->callEpanel($whmCall, 10);
		if ($result && $result->getCode() == 200) {
			return true;
		}

		$GLOBALS['sync_cdn_result'] = is_array($result) ? implode(' ', $result) : $result;
		return false;
	}

	/**
	 * v2.0.5
	 * 流程:发送api到辅节点，得到流量，并且辅节点的数据。
	 * 将流量插入数据库
	 *
	 */
	public function sync_host_flow($node, $t, $month, $day, $hour)
	{
		if (!$this->test_node($node)) {
			return false;
		}

		$fields = array('name', 'doc_root', 'uid', 'status', 'subdir', 'web_quota', 'log_file', 'access', 'speed_limit', 'cdn', 'sync_seq');
		$lvhs = daocall('vhost', 'listVhostNotcdn', array($this->cdn_prefix, $fields));

		if (count($lvhs) < 1) {
			return false;
		}

		$sync_failed = 0;
		$flow_success = 1;
		$post_failed = 2;
		$this->updateSyncStatus($node['host'], $sync_failed);
		$whm = apicall('nodes', 'makeEpanelWhm', array($node['host'], 3312, $node['skey']));

		if (!$whm) {
			setLastError('make whm is error');
			return false;
		}

		$a = 'getflow2';
		$whmCall = $this->getWhmUrl($node, $a);

		if (!$whmCall) {
			setLastError('whmcall get whmurl is error');
			return false;
		}

		$result = $whm->callEpanel($whmCall, 10);

		if (!$result) {
			setLastError("callepanel is false\r\n");
			return false;
		}

		$this->updateSyncStatus($node['host'], $flow_success);
		$vhs = (array) json_decode($result->get('vhs'));

		if (0 < count($vhs)) {
			$this->updateSyncStatus($node['host'], $flow_success);
			$vhs = $this->delPrefix($this->stdClassToArray($vhs), $this->cdn_prefix . $this->setting['local_cdn_name'] . '_');
			$this->insert_flow($vhs, $hour, $day, $month, 'global.db');
			$ret = $this->getNvhs($lvhs, $vhs);
			$nvhs = $ret['nvhs'];
		}
		else {
			$nvhs = $lvhs;
		}

		if (0 < count($nvhs)) {
			foreach ($nvhs as $vh) {
				$this->sync_vhost_cdn($node, $vh['name']);
			}
		}

		if (0 < count($ret['dvhs'])) {
			foreach ($ret['dvhs'] as $d) {
				$this->del_node_cdn($node, $d['name']);
			}
		}
	}

	/**
	 * 流量插入数据库
	 * @param $vhs array()
	 * @param $hour
	 * @param $day
	 * @param $month
	 * @param $db_name global.db || flow.db
	 */
	private function insert_flow($vhs = array(), $hour, $day, $month, $db_name)
	{
		load_lib('pub:flow');
		$flowobj = new flow($db_name);

		foreach ($vhs as $vh) {
			if (is_object($vh)) {
				$vh = (array) $vh;
			}

			if ($vh['flow_limit'] <= 0) {
				continue;
			}

			$flowobj->addFlow('flow_hour', $vh['name'], $hour, $vh['flow_limit']);
			$flowobj->addFlow('flow_day', $vh['name'], $day, $vh['flow_limit']);
			$flowobj->addFlow('flow_month', $vh['name'], $month, $vh['flow_limit']);
		}
	}

	public function delPrefix($vhs, $prefix = null)
	{
		$prefix = $prefix ? $prefix : $this->cdn_prefix . $this->setting['local_cdn_name'] . '_';
		$len = strlen($prefix);

		foreach ($vhs as $vh) {
			$vh['name'] = substr($vh['name'], $len);
			$dv[] = $vh;
		}

		return $dv;
	}

	/**
	 * 取得差集，和同步码不相同的虚拟主机名称。
	 * @param array $l_vhs 主节点
	 * @param array $r_vhs 辅节点 已经去除了prefix前缀
	 */
	public function getNvhs($l_vhs, $r_vhs)
	{
		$rvhs_1 = $this->arr2ToArr1($r_vhs, 'name');
		$lvhs_1 = $this->arr2ToArr1($l_vhs, 'name');

		foreach ($r_vhs as $r) {
			$r2[$r['name']] = $r;
		}

		foreach ($l_vhs as $ll) {
			$l2[$ll['name']] = $ll;
		}

		$sect = array_intersect($rvhs_1, $lvhs_1);

		if (0 < count($sect)) {
			foreach ($sect as $s) {
				if ($l2[$s]['sync_seq'] == 0 || $l2[$s]['sync_seq'] != $r2[$s]['sync_seq']) {
					$ret['nvhs'][] = $l2[$s];
				}
			}
		}

		$diff_l = array_diff($lvhs_1, $rvhs_1);
		$diff_r = array_diff($rvhs_1, $lvhs_1);

		if (0 < count($diff_l)) {
			foreach ($diff_l as $dl) {
				$ret['nvhs'][] = $l2[$dl];
			}
		}

		if (0 < count($diff_r)) {
			foreach ($diff_r as $dr) {
				$ret['dvhs'][] = $r2[$dr];
			}
		}

		if ($ret) {
			return $ret;
		}

		return false;
	}

	/**
	 *
	 * @param array $node
	 * @param string $a
	 */
	public function getWhmUrl($node, $a)
	{
		if (!$this->setting['local_cdn_name']) {
			return false;
		}

		$whmCall = new WhmCall('api/', $a);
		$whmCall->addParam('c', 'cdn');
		$whmCall->addParam('nodename', $this->setting['local_cdn_name']);
		return $whmCall;
	}

	public function updateSyncStatus($host, $status)
	{
		daocall('manynode', 'updateManynode', array(
	$host,
	array('synctime' => time(), 'syncstatus' => $status)
	));
	}

	/**
	 * 通过c_arr(一维,某个字段),从s_arr中得到二维数据
	 * @param array $s_arr
	 * @param array $c_arr
	 */
	public function getDiffArr($s_arr, $c_arr)
	{
		if (count($c_arr) < 0) {
			return false;
		}

		foreach ($c_arr as $c) {
			foreach ($s_arr as $s) {
				if ($c == $s['name']) {
					$v[] = $s;
				}
			}
		}

		return $v;
	}

	/**
	 *二维数组抽出某一个字段，得到一维数组
	 * @param arr2 $arr
	 * @param int $len;
	 */
	public function arr2ToArr1($arr, $filed = 'name')
	{
		foreach ($arr as $ar) {
			$a[] = $ar[$filed];
		}

		return $a;
	}

	/**
	 * 将stdclass转为数组
	 * Enter description here ...
	 * @param array $arr
	 */
	public function stdClassToArray($arr)
	{
		foreach ($arr as $a) {
			$ar[] = (array) $a;
		}

		return $ar;
	}

	/**
	 * api本节点同步流量接口，取得流量
	 */
	public function getflow($prefix = '', $revers = 0)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('core.whm', 'dump_flow');
		$whmCall->addParam('prefix', $prefix);
		$whmCall->addParam('revers', $revers);
		return $whm->call($whmCall, 300);
	}

	/**
	 * 将需要同步的虚拟主机，生成字符串
	 * 域名，access.xml，vhost三项
	 * access.xml内容base64编码$v['access_str']
	 * 域名则是数组$v['info']
	 * @param array $vhs
	 * @deprecated
	 */
	public function getCdnStr($vhs)
	{
		$str = '';
		$info_all = daocall('vhostinfo', 'getAll', array());

		foreach ($vhs as $vh) {
			if (!file_exists($vh['doc_root'] . '/' . $vh['access'])) {
				continue;
			}

			$access = base64_encode(file_get_contents($vh['doc_root'] . '/' . $vh['access']));
			$vh['access_str'] = $access;

			foreach ($info_all as $info) {
				if ($info['vhost'] == $vh['name']) {
					$vh['info'][] = $info;
				}
			}

			$v[] = $vh;
		}

		return $v;
	}

	/**
	 * 得到cdn设置
	 * host
	 * @deprecated
	 */
	public function sync($host)
	{
		if (strncasecmp($host, 'http://', 7) == 0) {
			$url = $host;
			$filename = 'cdn.xml';
		}
		else {
			$s = explode(':', $host);
			$filename = $s[0] . '.xml';
			$url = 'http://' . $host . '/api/';
		}

		$opts = array(
			'http' => array('timeout' => 120, 'header' => "Connection: close\r\n")
			);
		$url .= '?c=cdn&a=list_vh';
		srand((double) microtime() * 1000000);
		$skey = daocall('setting', 'get', array('skey'));
		$r = time() . rand();
		$s = md5('list_vh' . $skey . $r);
		$url .= '&r=' . $r . '&s=' . $s;
		$msg = @file_get_contents($url, false, stream_context_create($opts));

		if (strstr($msg, '<!--configfileisok-->') === false) {
			trigger_error($msg . "\nlist_vh last msg is not ok");
			return false;
		}

		$md5str = md5($msg);
		$file = $GLOBALS['safe_dir'] . 'vh.d/';
		@mkdir($file);
		$file .= $filename;
		$fp = fopen($file . '.tmp', 'wb');

		if ($fp === false) {
			trigger_error('cann\'t write file ' . $file . '.tmp');
			return false;
		}

		$msglen = strlen($msg);

		if ($msglen != fwrite($fp, $msg, $msglen)) {
			fclose($fp);
			unlink($file . '.tmp');
			trigger_error('may no space to write ' . $file . '.tmp');
			return false;
		}

		fclose($fp);
		@unlink($file);
		return rename($file . '.tmp', $file) == 0;
	}

	/**
	 *@deprecated
	 * @param array $domain name type value
	 * @param string $mode add or del
	 */
	public function sync_vhost_domain($domain = array(), $mode)
	{
		$nodes = daocall('manynode', 'get');

		if (count($nodes) <= 0) {
			return false;
		}

		$a = 'sync_cdn_domain';
		$poststr['vhost'] = getRole('vhost');
		$poststr['info'] = json_encode($domain);
		$poststr['mode'] = $mode;

		foreach ($nodes as $node) {
			$url = $this->getWhmUrl($node, $a);

			if ($url === false) {
				exit('本地节点名称未设置，请联系管理员');
			}

			$opts = array(
				'http' => array('timeout' => 120, 'method' => 'POST', 'content' => http_build_query($poststr, '', '&'))
				);
			$result = @file_get_contents($url, false, stream_context_create($opts));
			if ($result === false || $result == 'access denied') {
				continue;
			}
		}
	}

	/**
	 *@deprecated
	 * @param array $node
	 * @param string $vhost
	 */
	public function sync_vhost_access()
	{
		$nodes = daocall('manynode', 'get');

		if (count($nodes) <= 0) {
			return false;
		}

		$vhost = getRole('vhost');
		$vhost_info = daocall('vhost', 'getVhost', array($vhost));
		$poststr['access'] = file_get_contents($vhost_info['doc_root'] . '/' . $vhost_info['access']);
		$poststr['vhost'] = $vhost;

		if (!$access) {
			return false;
		}

		$a = 'sync_cdn_access';

		foreach ($nodes as $node) {
			$url = $this->getWhmUrl($node, $a);

			if ($url === false) {
				exit('本地节点名称未设置，请联系管理员');
			}

			$opts = array(
				'http' => array('timeout' => 120, 'method' => 'POST', 'content' => http_build_query($poststr, '', '&'))
				);
			$result = @file_get_contents($url, false, stream_context_create($opts));
			if ($result === false || $result == 'access denied') {
				continue;
			}
		}
	}
}

?>