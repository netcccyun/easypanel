<?php
class NodesAPI extends API
{
	private $MAP_ARR;

	public function __construct()
	{
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function getFullKey()
	{
		$key = @apicall('utils', 'getEntKey', array());
		$key .= @apicall('server', 'getEntKey', array());
		$product = apicall('product', 'newProduct', array('vhost'));
		$key .= $product->getEntKey();
		$key .= 'a2w7ZmFzbGQnYXNka2Zwb2FrandlcGtqLTIzNGstMjM0LTIzLTQya';
		return $key;
	}

	public function delMysqlTestDatabase($node)
	{
		$db = $this->makeDbProduct($node, 'mysql');
		return $db->delTestDatabase();
	}

	public function getInfo($node)
	{
		$node_cfg = $GLOBALS['node_cfg'][$node];

		if (!is_array($node_cfg)) {
			return trigger_error('没有节点' . $node . '的配置文件，请更新配置文件');
		}

		return $node_cfg;
	}

	public function whmCall($package, $callName, $params)
	{
		$whm = $this->makeWhm('localhost');

		if (!$whm) {
			return false;
		}

		$call = new WhmCall($package, $callName);

		foreach ($params as $k => $v) {
			$call->addParam($k, $v);
		}

		return $whm->call($call, 10);
	}

	public function listTemplete($node)
	{
		$whm = $this->makeWhm($node);

		if (!$whm) {
			return false;
		}

		$call = new WhmCall('core.whm', 'list_gtvh');
		$result = $whm->call($call, 5);

		if (!$result) {
			return false;
		}

		return $result->getAll('name');
	}

	public function listSubTemplete($node, $templete)
	{
		$whm = $this->makeWhm($node);

		if (!$whm) {
			return false;
		}

		$call = new WhmCall('core.whm', 'list_tvh');
		$call->addParam('name', $templete);
		$result = $whm->call($call, 5);

		if (!$result) {
			return false;
		}

		return $result->getAll('name');
	}

	public function makeWhm2($host, $port, $user, $passwd)
	{
		load_lib('pub:whm');
		$whm = new WhmClient();

		if ($host == 'localhost') {
			$host = '127.0.0.1';
		}

		$whmUrl = 'http://' . $host . ':' . $port . '/';
		$whm->setUrl($whmUrl);
		$whm->setAuth($user, $passwd);
		return $whm;
	}

	/**
	 * 
	 * Enter description here ...
	 * @param  $host
	 * @param  $port
	 * @param  $skey
	 */
	public function makeEpanelWhm($host, $port, $skey)
	{
		load_lib('pub:whm');
		$whm = new WhmClient();

		if ($host == 'localhost') {
			$host = '127.0.0.1';
		}

		$whmUrl = 'http://' . $host . ':' . $port . '/';
		$whm->setUrl($whmUrl);
		$whm->setSecurityKey($skey);
		return $whm;
	}

	public function makeLocalWhm($user, $passwd)
	{
		load_lib('pub:whm');
		$whm = new WhmClient();
		$port = 3311;

		if (isset($GLOBALS['node_cfg']['localhost']['port'])) {
			$port = $GLOBALS['node_cfg']['localhost']['port'];
		}

		$whmUrl = 'http://127.0.0.1:' . $port . '/';
		$whm->setUrl($whmUrl);
		$whm->setAuth($user, $passwd);
		return $whm;
	}

	public function changeAdminInfo($node, $user, $passwd)
	{
		$whm = $this->makeWhm($node);

		if (!$whm) {
			return false;
		}

		$whmCall = new WhmCall('core.whm', 'change_admin_password');
		$whmCall->addParam('admin_user', $user);
		$whmCall->addParam('admin_passwd', $passwd);
		$whmCall->addParam('admin_ips', '*');
		$result = $whm->call($whmCall, 10);

		if (!$result) {
			return false;
		}

		return $result->getCode() == 200;
	}

	public function getKangleInfo($node)
	{
		$whm = $this->makeWhm($node);

		if (!$whm) {
			return false;
		}

		$whmCall = new WhmCall('core.whm', 'info');
		return $whm->call($whmCall, 'info');
	}

	public function getWhmInfo($user, $passwd)
	{
		$whm = $this->makeLocalWhm($user, $passwd);
		$whmCall = new WhmCall('core.whm', 'info');
		return $whm->call($whmCall, 'info');
	}

	public function makeDbProduct($node, $db_type = 'mysql')
	{
		$node_cfg = $GLOBALS['node_cfg'][$node];

		if (!is_array($node_cfg)) {
			return trigger_error('没有节点' . $node . '的配置文件，请更新配置文件');
		}

		load_lib('pub:dbProduct');

		if (!$db_type) {
			$db_type = 'mysql';
		}

		$className = $db_type . 'DbProduct';
		load_lib('pub:' . $className);
		$className[0] = strtoupper($className[0]);
		$db = new $className();

		if (!$db->connect($node_cfg)) {
			return false;
		}

		return $db;
	}

	public function makeWhm($node = 'localhost')
	{
		$node_cfg = $GLOBALS['node_cfg'][$node];

		if (!is_array($node_cfg)) {
			return trigger_error('没有节点' . $node . '的配置文件，请更新配置文件');
		}

		return $this->makeWhm2($node_cfg['host'], $node_cfg['port'], $node_cfg['user'], $node_cfg['passwd']);
	}

	public function isWindows($node)
	{
		$node_cfg = $GLOBALS['node_cfg'][$node];

		if (!is_array($node_cfg)) {
			return trigger_error('没有节点' . $node . '的配置文件，请更新配置文件');
		}

		return $node_cfg['win'] == 1;
	}

	public function reboot($node)
	{
		$whm = $this->makeWhm($node);
		$whmCall = new WhmCall('core.whm', 'reboot');
		$whm->call($whmCall);
	}

	/**
	 *
	 * 初始化一个节点
	 * @param $node 节点名称
	 * @param $level 初始化级别
	 * 0   全部(首次初始化开始)
	 * 1  除首次初始化全部
	 */
	public function init($node, $config_flag, $init_flag, $reboot_flag)
	{
		$node_cfg = $GLOBALS['node_cfg'][$node];
		$whm = $this->makeWhm($node);
		$result = true;

		if ($config_flag == 1) {
			$driver = 'bin/vhs_';

			if ($GLOBALS['node_db'] == 'sqlite') {
				$driver .= 'sqlite';
			}
			else {
				$driver .= 'mysql';
			}

			if (is_win()) {
				$driver .= '.dll';
				$dso_ext = 'dll';
			}
			else {
				$driver .= '.so';
				$dso_ext = 'so';
			}

			$tpl = tpl::template_config(APPLICATON_ROOT . '/template_config/');
			$tpl->assign('win', is_win());
			$tpl->assign('skey', $GLOBALS['skey']);
			$tpl->assign('node', $node);
			$tpl->assign('driver', $driver);
			$tpl->assign('dso_ext', $dso_ext);
			$tpl->assign('node_db', $GLOBALS['node_db']);
			$setting = daocall('setting', 'getAll');
			$tpl->assign('setting', $setting);

			if ($GLOBALS['node_db'] != 'sqlite') {
				$tpl->assign('col_map', daocall('vhost', 'getColMap', array($node)));
				$tpl->assign('load_sql', daocall('vhost', 'getLoadSql', array($node)));
				$tpl->assign('flush_sql', daocall('vhost', 'getFlushSql', array(null)));
				$tpl->assign('load_info_sql', daocall('vhostinfo', 'getLoadInfoSql', array(null)));
				$tpl->assign('table', daocall('vhost', 'getTable'));
				$tpl->assign('col', daocall('vhost', 'getCols'));
				global $db_cfg;

				if ($db_cfg['ftp']) {
					$db = $db_cfg['ftp'];
				}
				else {
					$db = $db_cfg['default'];
				}

				$db_local = $this->isLocalHost($db['host']);
				$node_local = $this->isLocalHost($node_cfg['host']);
				if ($db_local && !$node_local) {
					$host = $_SERVER['SERVER_ADDR'];

					if ($host == '') {
						$host = $_SERVER['SERVER_NAME'];
					}

					if ($host == '' || $this->isLocalHost($host)) {
						trigger_error('Cann\'t init node,I Cann\'t translate the db host.');
						return false;
					}

					$db['host'] = $host;
				}

				$tpl->assign('db', $db);
			}

			$os = substr(php_uname('s'), 0, 3);
			$params = $setting['params'];
			$tpl->assign('dev', $node_cfg['dev']);
			$content = $tpl->fetch('vh_db.xml');
			$filename = $GLOBALS['safe_dir'] . 'vh_db.xml';
			$fp = fopen($filename, 'wb');

			if ($fp) {
				fwrite($fp, $content);
				fclose($fp);
			}

			$tpl->assign('php_extend', $php_extend);
			$content = $tpl->fetch('templete.xml');
			$filename = $GLOBALS['safe_dir'] . '../ext/templete.xml';
			$fp = fopen($filename, 'wb');

			if ($fp) {
				fwrite($fp, $content);
				fclose($fp);
			}

			if (is_win()) {
				//@unlink($GLOBALS['safe_dir'] . '../ext/dbadmin.xml');
				$ftp_configs = '';

				if ($setting['ftp_pasv_port']) {
					$ftp_configs .= 'pasv_port ' . $setting['ftp_pasv_port'] . "\r\n";
				}

				if ($setting['ftp_port']) {
					$ftp_configs .= 'port ' . $setting['ftp_port'] . "\r\n";
				}

				$tpl->assign('ftp_configs', $ftp_configs);
				$content = $tpl->fetch('linxftp.conf');
				$filename = $GLOBALS['safe_dir'] . 'linxftp.conf';
				$fp = fopen($filename, 'wb');

				if ($fp) {
					fwrite($fp, $content);
					fclose($fp);
				}

				$whmCall = new WhmCall('vhost.whm', 'reboot_ftp');
				$result = $whm->call($whmCall);
			}
			else {
				$ftp_configs = '';
				if ($setting['ftp_pasv_port']) {
					$ftp_configs .= ' --passiveportrange ' . $setting['ftp_pasv_port'];
				}

				if ($setting['ftp_port']) {
					$ftp_configs .= ' --bind *,' . $setting['ftp_port'];
				}

				$tpl->assign('ftp_configs', $ftp_configs);
				$content = $tpl->fetch('pureftpd');
				$fp = fopen('/etc/init.d/pureftpd', 'wb');

				if ($fp) {
					fwrite($fp, $content);
					fclose($fp);
					exec('/etc/init.d/pureftpd restart');
				}
			}

			$content = $tpl->fetch('db_config.conf');
			$filename = $GLOBALS['safe_dir'] . 'db_config.php';
			$fp = fopen($filename, 'wb');

			if ($fp) {
				fwrite($fp, $content);
				fclose($fp);
			}
		}

		if ($init_flag == 1) {
			$whmCall = new WhmCall('vhost.whm', 'init_node');
			$whmCall->addParam('dev', $node_cfg['dev']);
			$whmCall->addParam('prefix', apicall('vhost', 'getPrefix'));
			$result = $whm->call($whmCall);
		}

		if ($reboot_flag == 1) {
		}

		if (!$result) {
			trigger_error($whmCall->getCallName() . ' ' . $whm->err_msg);
			return false;
		}

		return true;
	}

	/**
	 *
	 * 重建节点配置文件
	 */
	public function flush()
	{
		$nodes = daocall('nodes', 'listNodes');
		return apicall('utils', 'writeConfig', array($nodes, 'name', 'node', $GLOBALS['safe_dir']));
	}

	public function isLocalHost($host)
	{
		if (strcasecmp($host, 'localhost') == 0) {
			return true;
		}

		if (strncmp($host, '127.0.0.', 8) == 0) {
			return true;
		}

		if ($host == '::1') {
			return true;
		}

		return false;
	}

	public function checkNode($node = 'localhost')
	{
		$whm = $this->makeWhm($node);
		$ret = array();
		$ret['whm'] = 0;

		if ($whm) {
			$whmCall = new WhmCall('core.whm', 'check_vh_db');
			$result = $whm->call($whmCall, 5);
			if ($result && intval($result->get('status')) == 1) {
				$ret['whm'] = 1;
			}
		}

		$node_cfg = $GLOBALS['node_cfg'][$node];

		if ($node_cfg['db_user'] != '') {
			$db = $this->makeDbProduct($node, 'mysql');

			if ($db) {
				$ret['db'] = 1;
			}
			else {
				$ret['db'] = 0;
			}
		}
		else {
			$ret['db'] = 2;
		}

		if ($node_cfg['sqlsrv_user']) {
			$db = $this->makeDbProduct($node, 'sqlsrv');

			if ($db) {
				$ret['sqlsrv'] = 1;
			}
			else {
				$ret['sqlsrv'] = 0;
			}
		}
		else {
			$ret['sqlsrv'] = 2;
		}

		return $ret;
	}

	public function checkNodes()
	{
		$node_cfgs = $GLOBALS['node_cfg'];
		$nodes = array();
		$keys = array_keys($node_cfgs);
		$i = 0;

		while ($i < count($keys)) {
			$nodes[$keys[$i]] = $this->checkNode($keys[$i]);
			++$i;
		}

		return $nodes;
	}
}

?>