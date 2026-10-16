<?php
needRole('vhost');
define('DENY_BANIP_TABLE', '!deny_vhost_ip');
define('DENY_BANURL_TABLE', '!deny_url');
define('ALLOW_TABLE', '!allow_vhost_ip');
define('ALLOW_URL_TABLE', '!allow_url');
define('REALIP_TABLE', '!real_ip');
define('ACTION', 'table:!deny_vhost_ip');
define('ACTION2', 'table:!deny_url');
define('BEGIN', 'BEGIN');
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

				$this->access->sortBeginChains();
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

	/**
	 * CDN真实IP获取页：站点套CDN后，用CDN传递真实IP的header替换客户端IP，
	 * 使黑白名单/防CC等所有按IP的功能按真实访客IP匹配（kangle replace_ip 标记）。
	 * 单一header模式：常用下拉选择或自定义。
	 */
	public function realipFrom()
	{
		$check_result = apicall('access', 'checkAccess', array());

		if ($check_result !== true) {
			return $this->show_msg($check_result);
		}

		$header = '';
		$result = $this->access->listChain(REALIP_TABLE, 1);

		if ($result) {
			foreach ($result->children() as $chain) {
				foreach ($chain->children() as $name => $ch) {
					if ($name == 'mark_replace_ip') {
						$header = (string) $ch['header'];
						break 2;
					}
				}
			}
		}

		// 区分常用选项与自定义，用于回显
		$common = array('X-Forwarded-For', 'X-Real-Ip', 'CF-Connecting-IP', 'Cdn-Src-Ip', 'True-Client-Ip');

		if (in_array($header, $common, true)) {
			$mode = $header;
			$custom = '';
		}
		elseif ($header !== '') {
			$mode = 'custom';
			$custom = $header;
		}
		else {
			$mode = '';
			$custom = '';
		}

		$this->assign('mode', $mode);
		$this->assign('custom', $custom);
		return $this->_tpl->fetch('banip/realip.html');
	}

	/**
	 * 保存真实IP header（单一模式）：填写则挂 BEGIN 引用链（sortBeginChains 保证位于最顶），
	 * 清空则摘链并清空子表。
	 */
	public function saveRealip()
	{
		$header = trim(ep_str(isset($_REQUEST['header']) ? $_REQUEST['header'] : ''));

		if ($header !== '' && !preg_match('/^[A-Za-z0-9-]+$/', $header)) {
			exit('header格式错误：' . $header);
		}

		while ($this->access->findChain(BEGIN, REALIP_TABLE)) {
			if (!$this->access->delChainByName(BEGIN, REALIP_TABLE)) {
				exit('保存失败');
			}
		}

		if ($header === '') {
			// 关闭：清空子表（不存在时忽略失败）
			$this->access->emptyTable(REALIP_TABLE);
		}
		else {
			if (!$this->access->addTable(REALIP_TABLE)) {
				exit('保存失败');
			}

			if (!$this->access->emptyTable(REALIP_TABLE)) {
				exit('保存失败');
			}

			$models = array();
			$models['mark_replace_ip'] = array('header' => $header);
			$arr = array('action' => 'continue');

			if (!$this->access->addChain(REALIP_TABLE, $arr, $models)) {
				exit('保存失败');
			}

			$arr = array('action' => 'table:' . REALIP_TABLE, 'name' => REALIP_TABLE);

			if (!$this->access->addChain(BEGIN, $arr)) {
				exit('保存失败');
			}

			$this->access->sortBeginChains();
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	/**
	 * 白名单独立页：IP白名单 + URL白名单，交互与黑名单一致（单条增加/删除/清空）。
	 * allow 为终结动作：命中的请求完全豁免下方所有防护与域名跳转（含黑名单）；
	 * 白名单（IP/URL）优先于黑名单，URL白名单优先于URL黑名单。
	 */
	public function whitelistFrom()
	{
		$check_result = apicall('access', 'checkAccess', array());

		if ($check_result !== true) {
			return $this->show_msg($check_result);
		}

		if ($this->access->findChain(BEGIN, ALLOW_TABLE) != false) {
			$this->assign('allowip', 1);
		}

		$result = $this->access->listChain(ALLOW_TABLE, 1);
		$id = 0;
		$ips = array();

		if ($result) {
			foreach ($result->children() as $chain) {
				$ips[] = array('ip' => (string) $chain->children(), 'id' => $id++);
			}
		}

		$this->assign('ips', $ips);

		if ($this->access->findChain(BEGIN, ALLOW_URL_TABLE) != false) {
			$this->assign('allowurl', 1);
		}

		$id = 0;
		$urls = array();
		$result2 = $this->access->listChain(ALLOW_URL_TABLE, 1);

		foreach ($result2 ? $result2->children() : array() as $chain) {
			$meth = '全部';
			$url = null;

			foreach ($chain->children() as $name => $ch) {
				if ($name == 'acl_url') {
					$url = (string) $ch;
				}
				elseif ($name == 'acl_meth') {
					$meth = (string) $ch[0];
					if ((string) $ch['revers'] == '1') {
						$meth = '!' . $meth;
					}
				}
			}

			if ($url !== null) {
				$urls[] = array('url' => $url, 'meth' => $meth, 'id' => $id++);
			}
		}

		$this->assign('urls', $urls);
		return $this->_tpl->fetch('banip/whitelist.html');
	}

	/**
	 * 增加白名单IP：每IP一条 acl_src+allow 链（与黑名单一条一IP对称）。
	 * 是否生效由独立开关控制（与黑名单一致）。
	 */
	public function addWhitelist()
	{
		$ip = trim(ep_str(isset($_REQUEST['ip']) ? $_REQUEST['ip'] : ''));

		if ($ip === '') {
			exit('IP地址不能为空');
		}

		if (!$this->checkIp($ip)) {
			exit('请输入正确的IP地址');
		}

		if (!$this->access->addTable(ALLOW_TABLE)) {
			exit('增加失败');
		}

		$models = array();
		$models['acl_src'] = array('ip' => $ip);
		$arr = array('action' => 'allow');

		if (!$this->access->addChain(ALLOW_TABLE, $arr, $models)) {
			exit('增加失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function delWhitelist()
	{
		$id = intval($_REQUEST['id']);

		if (!$this->access->delChain(ALLOW_TABLE, $id)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function delWhitelistAll()
	{
		if (!$this->access->emptyTable(ALLOW_TABLE)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	/**
	 * 白名单开关：与黑名单 switchIp 对称，独立控制 BEGIN 引用链。
	 */
	public function switchWhitelist()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			$this->access->addTable(ALLOW_TABLE);
			$this->access->addTable(BEGIN);

			$existing = $this->access->findChain(BEGIN, ALLOW_TABLE);

			if ($existing != false && (string) $existing['action'] === 'table:' . ALLOW_TABLE) {
				break;
			}

			// 与黑名单 switchIp 对称：BEGIN 引用链必须指向子表。
			// 历史版本曾误存 action=allow 的无ACL引用链（等于放行所有人），此处删除重挂以自动纠正
			$this->access->delChainByName(BEGIN, ALLOW_TABLE);

			$arr = array('action' => 'table:' . ALLOW_TABLE, 'name' => ALLOW_TABLE);

			if (!$this->access->addChain(BEGIN, $arr)) {
				return $this->show_msg('不能增加链');
			}

			$this->access->sortBeginChains();
			break;

		case 2:
			$this->access->delChainByName(BEGIN, ALLOW_TABLE);
			break;

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	/**
	 * 增加白名单URL：每URL一条 acl_url+allow 链（链内ACL是AND逻辑，多个模式必须拆多条链），
	 * 可选请求方法（支持 !GET 反选），与 addBanurl 对称。
	 */
	public function addWhitelistUrl()
	{
		$url = trim(ep_str(isset($_REQUEST['url']) ? $_REQUEST['url'] : ''));
		$meth = trim(ep_str(isset($_REQUEST['meth']) ? $_REQUEST['meth'] : ''));

		if (!$url) {
			exit('URL不能为空');
		}

		if (!$this->access->addTable(ALLOW_URL_TABLE)) {
			exit('增加失败');
		}

		$models = array();

		if (!empty($meth)) {
			if (substr($meth, 0, 1) == '!') {
				$meth = substr($meth, 1);
				$models['acl_meth'] = array('revers' => 1, 'meth' => $meth);
			}
			else {
				$models['acl_meth'] = array('meth' => $meth);
			}
		}

		$models['acl_url'] = array('url' => $url, 'nc' => 1);
		$arr = array('action' => 'allow');

		if (!$this->access->addChain(ALLOW_URL_TABLE, $arr, $models)) {
			exit('增加失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function delWhitelistUrl()
	{
		$id = intval($_REQUEST['id']);

		if (!$this->access->delChain(ALLOW_URL_TABLE, $id)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function delWhitelistUrlAll()
	{
		if (!$this->access->emptyTable(ALLOW_URL_TABLE)) {
			exit('删除失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	/**
	 * URL白名单开关：与黑名单 switchUrl 对称。
	 */
	public function switchWhitelistUrl()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 1:
			$this->access->addTable(ALLOW_URL_TABLE);
			$this->access->addTable(BEGIN);

			if ($this->access->findChain(BEGIN, ALLOW_URL_TABLE) == false) {
				$arr = array('action' => 'table:' . ALLOW_URL_TABLE, 'name' => ALLOW_URL_TABLE);

				if (!$this->access->addChain(BEGIN, $arr)) {
					return $this->show_msg('不能增加链');
				}

				$this->access->sortBeginChains();
			}

			break;

		case 2:
			$this->access->delChainByName(BEGIN, ALLOW_URL_TABLE);
			break;

		default:
			break;
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

				$this->access->sortBeginChains();
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
