<?php
needRole('vhost');
define('DENY_BANIP_TABLE', '!deny_vhost_ip');
define('DENY_BANURL_TABLE', '!deny_url');
define('DENY_BANGEO_TABLE', '!deny_geo');
define('ACTION', 'table:!deny_vhost_ip');
define('ACTION2', 'table:!deny_url');
define('BEGIN', 'BEGIN');
class BanipControl extends Control
{
	private $access;
	private $geoAvailable;

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
		$ips = array();

		if ($result) {
			foreach ($result->children() as $chain) {
				$ips[] = array('ip' => (string) $chain->children(), 'id' => $id++);
			}
		}

		$this->assign('ips', $ips);

		$id = 0;
		$urls = array();

		foreach ($result2 ? $result2->children() : array() as $chain) {
			$meth = '全部';
			$url = '';
			$hasMethod = false;
			foreach ($chain->children() as $name=>$ch) {
				if($name == 'acl_url'){
					$url = (string) $ch['url'];
					if ($url === '') { $url = (string) $ch; }
				}elseif($name == 'acl_meth'){
					$meth = (string) $ch['meth'];
					if ($meth === '') { $meth = (string) $ch; }
					$hasMethod = $meth !== '';
					if((string)$ch['revers'] == '1')$meth = '!'.$meth;
				}
			}
			if ($url !== '' || $hasMethod) {
				$urls[] = array('url' => $url, 'meth' => $meth, 'id' => $id);
			}
			$id++;
		}

		$this->assign('urls', $urls);
		$this->assign('geo_available', $this->geoAvailable());
		if ($this->geoAvailable()) {
			$this->assign('bangeo', (bool) $this->access->findChain(BEGIN, DENY_BANGEO_TABLE));
			$this->assign('regions', $this->geoRules());
		}
		return $this->_tpl->fetch('banip/addFrom.html');
	}

	private function geoAvailable()
	{
		if ($this->geoAvailable === null) {
			load_lib('pub:geo');
			$this->geoAvailable = EpGeo::available();
		}
		return $this->geoAvailable;
	}

	private function geoJson($code, $message, $extra = array())
	{
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		exit(json_encode(array_merge(array('code' => $code, 'msg' => $message), $extra)));
	}

	private function requireGeo()
	{
		$permission = apicall('access', 'checkAccess', array());
		if ($permission !== true) { $this->geoJson(403, $permission); }
		if (!$this->geoAvailable()) { $this->geoJson(403, '全局请求控制规则未配置geo标记模块'); }
	}

	private function geoRules()
	{
		$rows = array();
		$chains = $this->access->listChain(DENY_BANGEO_TABLE, 1);
		$id = 0;
		foreach ($chains ? $chains->children() : array() as $chain) {
			$rows[] = array('id' => $id++, 'region' => (string) $chain['name']);
		}
		return $rows;
	}

	private function ensureGeoTable($name)
	{
		$tables = $this->access->listTable();
		return $tables !== false && (in_array($name, $tables, true) || $this->access->addTable($name));
	}

	public function switchGeo()
	{
		$this->requireGeo();
		$status = ep_request('status');
		if ($status !== '1' && $status !== '0') { $this->geoJson(400, '参数错误'); }
		$exists = $this->access->findChain(BEGIN, DENY_BANGEO_TABLE);
		if ($status === '1') {
			$ok = $this->ensureGeoTable(DENY_BANGEO_TABLE) && $this->ensureGeoTable(BEGIN);
			if ($ok && !$exists) {
				$ok = $this->access->addChain(BEGIN, array('name' => DENY_BANGEO_TABLE, 'action' => 'table:' . DENY_BANGEO_TABLE));
			}
		} else {
			$ok = !$exists || $this->access->delChainByName(BEGIN, DENY_BANGEO_TABLE);
		}
		if (!$ok) { $this->geoJson(500, '保存地区黑名单开关失败'); }
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		$this->geoJson(200, '成功');
	}

	public function addBangeo()
	{
		$this->requireGeo();
		$tree = EpGeo::regions();
		if (!$tree) { $this->geoJson(500, '无法读取静态地区数据，请先运行地区数据提取脚本'); }
		$path = EpGeo::selection($tree, ep_request('continent'), ep_request('country'), ep_request('province'));
		if ($path === false) { $this->geoJson(400, '请选择有效的大洲、国家和省份'); }
		$name = implode(' / ', $path);
		foreach ($this->geoRules() as $rule) {
			if ($rule['region'] === $name) { $this->geoJson(400, '该地区已在黑名单中'); }
		}
		// Header matching uses regular expressions; match one complete geo label.
		$models = array('acl_header' => array('header' => '_geo', 'val' => '^' . preg_quote(end($path), '/') . '$', 'nc' => 0));
		if (!$this->ensureGeoTable(DENY_BANGEO_TABLE) ||
			!$this->access->addChain(DENY_BANGEO_TABLE, array('action' => 'deny', 'name' => $name), $models)) {
			$this->geoJson(500, '增加地区黑名单失败');
		}
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		$this->geoJson(200, '成功');
	}

	public function delBangeo()
	{
		$this->requireGeo();
		$id = ep_request('id');
		$rules = $this->geoRules();
		if ($id === '' || !ctype_digit($id) || !isset($rules[intval($id)])) { $this->geoJson(400, '地区规则不存在'); }
		if (!$this->access->delChain(DENY_BANGEO_TABLE, intval($id))) { $this->geoJson(500, '删除失败'); }
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		$this->geoJson(200, '成功');
	}

	public function delBangeoAll()
	{
		$this->requireGeo();
		if (!$this->access->emptyTable(DENY_BANGEO_TABLE)) { $this->geoJson(500, '清空失败'); }
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		$this->geoJson(200, '成功');
	}

	private function addTable($tablename)
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach (ep_iter($tables) as $table) {
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
				$arr = array();
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
		$ip = trim(ep_str(isset($_REQUEST['ip']) ? $_REQUEST['ip'] : ''));

		if ($ip === '') {
			exit('IP地址不能为空');
		}
		if (!$this->checkIp($ip)) {
			exit('请输入正确的IP地址');
		}

		$models = array();
		$models['acl_src'] = array('ip' => $ip);
		$arr = array();
		$arr['action'] = 'deny';

		if (!$this->access->addChain(DENY_BANIP_TABLE, $arr, $models)) {
			exit('增加失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
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
		$url = trim(ep_str(isset($_REQUEST['url']) ? $_REQUEST['url'] : ''));
		$meth = trim(ep_str(isset($_REQUEST['meth']) ? $_REQUEST['meth'] : ''));

		if ($url === '' && $meth === '') {
			exit('URL和请求方法不能同时为空');
		}
		$methodName = substr($meth, 0, 1) === '!' ? substr($meth, 1) : $meth;
		if ($meth !== '' && !in_array($methodName, array('GET', 'POST', 'PUT', 'DELETE'), true)) {
			exit('请求方法错误');
		}
		$models = array();
		if(!empty($meth)){
			if(substr($meth,0,1)=='!'){
				$meth = substr($meth,1);
				$models['acl_meth'] = array('revers' => 1, 'meth' => $meth);
			}else{
				$models['acl_meth'] = array('meth' => $meth);
			}
		}
		if ($url !== '') {
			$models['acl_url'] = array('url' => $url, 'nc' => 1);
		}
		$arr['action'] = 'deny';

		if (!$this->access->addChain(DENY_BANURL_TABLE, $arr, $models)) {
			exit('增加失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
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
		$parts = explode('/', $ip, 2);
		if (!filter_var($parts[0], FILTER_VALIDATE_IP)) {
			return false;
		}

		if (count($parts) === 1) {
			return true;
		}

		$max_prefix = strpos($parts[0], ':') === false ? 32 : 128;
		return $parts[1] !== '' && ctype_digit($parts[1]) && intval($parts[1]) <= $max_prefix;
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}
