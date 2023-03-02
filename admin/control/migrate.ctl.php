<?php
needRole('admin');
define('MYSQL_FILE_EXT', '.sql.7z');
define('WEB_FILE_EXT', '.web.7z');
class MigrateControl extends Control
{
	private $migrate_skey;
	private $migrate_host;
	private $migrate_port;
	private $setting;
	private $migrate_nolog;

	public function __construct()
	{
		parent::__construct();
		$setting = daocall('setting', 'getAll', array());

		if ($_SESSION['migrate_host'] != '') {
			$this->migrate_host = $setting['migrate_host'] = $_SESSION['migrate_host'];
		}

		if ($_SESSION['migrate_port'] != '') {
			$this->migrate_port = $setting['migrate_port'] = $_SESSION['migrate_port'];
		}else{
			$_SESSION['migrate_port'] = 3312;
		}

		if ($_SESSION['migrate_skey'] != '') {
			$this->migrate_skey = $setting['migrate_skey'] = $_SESSION['migrate_skey'];
		}

		if ($_SESSION['migrate_change_uid'] != '') {
			$setting['migrate_change_uid'] = $_SESSION['migrate_change_uid'];
		}

		$setting['migrate_prefix'] = $_SESSION['migrate_prefix'];
		$this->migrate_nolog = intval($_SESSION['migrate_nolog']);
		$this->setting = $setting;
	}

	public function index()
	{
		$this->_tpl->assign('session', $_SESSION);
		return $this->_tpl->fetch('migrate/index.html');
	}

	public function addFrom()
	{
		if ($this->migrate_host != '' && $this->migrate_skey != '') {
			$result = $this->callWhm('list_vhost');
			if ($result !== false && $result != 500) {
				$vhs = json_decode($result->get('vh'));
				$this->_tpl->assign('vhs', $vhs);
			}
		}

		return $this->_tpl->fetch('migrate/addfrom.html');
	}

	private function migrate_result($code, $msg = null)
	{
		$ret['code'] = $code;
		$ret['out'] = $msg;
		exit(json_encode($ret));
	}

	public function add()
	{
		session_start();
		$migrate_host = trim($_REQUEST['migrate_host']);
		$migrate_port = intval($_REQUEST['migrate_port']);

		if (!$migrate_host) {
			exit('目标服务器IP未设置');
		}
		if (!$migrate_port) $migrate_port = 3312;

		$_SESSION['migrate_host'] = $migrate_host;
		$_SESSION['migrate_port'] = $migrate_port;
		$migrate_skey = trim($_REQUEST['migrate_skey']);

		if (!$migrate_skey) {
			exit('目标服务器安全码未设置');
		}

		$_SESSION['migrate_skey'] = $migrate_skey;
		$_SESSION['migrate_temp_del'] = $_REQUEST['migrate_temp_del'];
		$_SESSION['migrate_change_uid'] = $_REQUEST['migrate_change_uid'];
		$_SESSION['migrate_prefix'] = $_REQUEST['migrate_prefix'];
		$this->migrate_nolog = $_SESSION['migrate_nolog'] = intval($_REQUEST['migrate_nolog']);

		if ($_REQUEST['type'] == 1) {
			header('Location:?c=migrate&a=migrateProductFrom');
			exit();
		}

		header('Location:?c=migrate&a=addFrom');
		exit();
	}

	public function migrate()
	{
		$vh = trim($_REQUEST['vh']);

		if (!$vh) {
			$this->migrate_result(500, '账号不能为空');
		}

		switch ($_REQUEST['step']) {
		case 1:
			return $this->migrateWeb($vh);
		case 2:
			return $this->migrateSql($vh);
		case 3:
			return $this->migrateWebWget($vh);
		case 4:
			return $this->migrateSqlWget($vh);
		case 5:
			return $this->migrateWebRestore($vh);
		case 6:
			return $this->migrateSqlRestore($vh);
		case 7:
			return $this->migrateComplete($vh);
		default:
			break;
		}
	}

	public function migrateWebRestore($vh = null)
	{
		$vh_info = daocall('vhost', 'getVhost', array($this->setting['migrate_prefix'] . $vh));

		if (!$vh_info) {
			$this->migrate_result(500, '该账号没有在本地创建');
		}

		$arr['file'] = $vh . WEB_FILE_EXT;
		$call = 'restore_web';
		$result = apicall('shell', 'whmshell', array($call, $this->setting['migrate_prefix'] . $vh, $arr));

		if (!$result) {
			$this->migrate_result(500, 'shell返回错误');
		}

		$this->migrate_result(200, (string) $result->get('session'));
	}

	public function migrateSqlRestore($vh = null)
	{
		$G = $GLOBALS['node_cfg']['localhost'];
		if (!$G['db_passwd'] || !$G['db_user']) {
			$this->migrate_result(500, '数据库账号和密码错误');
		}

		$vh_info = daocall('vhost', 'getVhost', array($this->setting['migrate_prefix'] . $vh));

		if (!$vh_info) {
			$this->migrate_result(500, '该账号没有在本地创建');
		}

		if ($vh_info['db_quota'] <= 0) {
			$this->migrate_result(200, '没有数据库');
		}

		$arr['host'] = 'localhost';
		$arr['user'] = $G['db_user'];
		$arr['passwd'] = $G['db_passwd'];
		$arr['file'] = $vh . MYSQL_FILE_EXT;
		$arr['dbname'] = $this->setting['migrate_prefix'] . $vh;
		$call = 'system_mysql_dumpin_compress_sql';
		$result = apicall('shell', 'whmshell', array($call, $this->setting['migrate_prefix'] . $vh, $arr));

		if (!$result) {
			$this->migrate_result(500, 'shell返回错误');
		}

		$this->migrate_result(200, (string) $result->get('session'));
	}

	public function migrateWebWget($vh = null)
	{
		$this->migrateWget($vh, WEB_FILE_EXT);
	}

	private function migrateWget($vh, $file_ext)
	{
		load_lib('pub:whm');
		srand((double) microtime() * 1000000);
		$call = new WhmCall('api/', 'migrate_down');
		$url = 'http://' . $this->migrate_host . ':'.$this->migrate_port.'/' . $call->buildEpanelUrl($this->migrate_skey);
		$url .= '&f=' . $vh . $file_ext;
		$vh_info = daocall('vhost', 'getVhost', array($this->setting['migrate_prefix'] . $vh));

		if (!$vh_info) {
			$this->migrate_result(500, '该账号没有在本地创建');
		}

		if ($file_ext == MYSQL_FILE_EXT && $vh_info['db_quota'] <= 0) {
			$this->migrate_result(200, '该空间没有数据库');
		}

		$call = 'system_wget';
		$arr['ext_arg'] = '-c';
		$arr['url'] = $url;
		$arr['file'] = $vh_info['doc_root'] . '/' . $vh . $file_ext;

		if ($result = apicall('shell', 'whmshell', array($call, $vh, $arr))) {
			$this->migrate_result(200, (string) $result->get('session'));
		}

		$this->migrate_result(500, 'shell返回错误');
	}

	public function migrateSqlWget($vh = null)
	{
		$this->migrateWget($vh, MYSQL_FILE_EXT);
	}

	public function migrateWeb($vh = null)
	{
		$arr['vh'] = $vh;
		$arr['nolog'] = $this->migrate_nolog;
		$result = $this->callWhm('migrate_domain', $arr);

		if ($result === false) {
			$this->migrate_result(500, 'shell返回错误');
		}

		$code = $result->getCode();

		if ($code != 200) {
			$this->migrate_result(500, 'shell返回错误');
		}

		$vhs = (array) json_decode(base64_decode($result->get('vh')));
		$vh_info = (array) $vhs['vh'];
		$vh_info['name'] = $this->setting['migrate_prefix'] . $vh_info['name'];

		if ($this->setting['migrate_change_uid'] == 1) {
			$vh_info['uid'] = null;
		}

		$whm = apicall('nodes', 'makeWhm', array('localhost'));

		if (!$whm) {
			$this->migrate_result(500, '获取模板失败');
		}

		$whmCall = new WhmCall('core.whm', 'list_gtvh');
		$result = $whm->call($whmCall);
		$templete_name = (array) $result->getAll('name');
		$templete_check = false;

		foreach ($templete_name as $t_name) {
			if ($vh_info['templete'] == $t_name) {
				$templete_check = true;
			}
		}

		if ($templete_check != true) {
			$this->migrate_result(500, '本地没有该模板' . $vh_info['templete'] . ',请先增加该模板');
		}

		load_lib('pub:product');
		load_lib('pub:vhostProduct');
		$vp = new vhostProduct();
		$vh_info['doc_root'] = $vp->getDocRoot($vh_info['name']);
		$vh_info['init'] = 1;
		$product = apicall('product', 'newProduct', array('vhost'));

		if (!$product->sell(getRole('admin'), intval($vh_info['product_id']), $vh_info)) {
			$this->migrate_result(500, '账号创建失败');
		}

		daocall('vhost', 'setPasswd', array($vh_info['name'], $vh_info['passwd']));
		$db_password = $vhs['db_password'];

		if ($db_password) {
			$db = apicall('nodes', 'makeDbProduct', array('localhost', $vh_info['db_type']));

			if (is_object($db)) {
				$db->dumpInPassword($vh_info['name'], $db_password);
			}
		}

		$domain_info = (array) $vhs['info'];

		foreach ($domain_info as $domain) {
			$domain = (array) $domain;

			if (!daocall('vhostinfo', 'findDomain', array($domain['name'], $this->setting['migrate_prefix'] . $vh))) {
				apicall('vhost', 'addInfo', array($this->setting['migrate_prefix'] . $vh, $domain['name'], $domain['type'], $domain['value']));
			}
		}

		unset($result);
		unset($code);
		$result = $this->callWhm('migrate_hello_vh_web', $arr);
		$code = $result->getCode();
		if ($code != 200 && $code != 201) {
			$this->migrate_result(500, 'shell返回错误');
		}

		$this->migrate_result(200, (string) $result->get('session'));
	}

	public function migrateSql($vh = null)
	{
		$arr['vh'] = $vh = $_REQUEST['vh'];
		$vh_info = daocall('vhost', 'getVhost', array($this->setting['migrate_prefix'] . $vh));

		if (!$vh_info) {
			$this->migrate_result(500, '该账号没有在本地创建');
		}

		if ($vh_info['db_quota'] <= 0) {
			$this->migrate_result(200, '该空间没有数据库');
		}

		$result = $this->callWhm('migrate_hello_vh_sql', $arr);
		$code = $result->getCode();
		if ($code != 200 && $code != 201) {
			$this->migrate_result(500, 'shell返回错误');
		}

		$this->migrate_result(200, (string) $result->get('session'));
	}

	public function whmQuery()
	{
		$arr['session'] = $session = trim($_REQUEST['session']);
		$arr['vh'] = $vh = trim($_REQUEST['vh']);
		$step = $_REQUEST['step'];
		if ($step == 1 || $step == 2 || $step == 7) {
			$result = $this->callWhm('migrate_query', $arr);
		}
		else {
			$result = apicall('shell', 'query', array($session, $vh));
		}

		if ($result === false) {
			$ret['code'] = '500';
			$ret['out'] = null;
		}
		else {
			$ret['code'] = $result->getCode();
			$ret['out'] = (string) $result->get('out');
		}

		exit(json_encode($ret));
	}

	private function callWhm($callName, $arr = null)
	{
		$whm = apicall('nodes', 'makeEpanelWhm', array($this->migrate_host, $this->migrate_port, $this->migrate_skey));
		$whmCall = new WhmCall('api/', $callName);

		if ($arr) {
			foreach ($arr as $k => $v) {
				$whmCall->addParam($k, $v);
			}
		}

		return $whm->callEpanel($whmCall);
	}

	public function migrateComplete($vh = null)
	{
		$arr['vh'] = $vh;
		$result = $this->callWhm('migrate_complete', $arr);
		$this->migrateDelFile($vh);

		if ($result === false) {
			$this->migrate_result(500);
		}

		$this->migrate_result(200, $result->getCode());
	}

	private function migrateDelFile($vh = null)
	{
		$vh_info = daocall('vhost', 'getVhost', array($this->setting['migrate_prefix'] . $vh));

		if (!$vh_info) {
			return false;
		}

		@unlink($vh_info['doc_root'] . '/' . $vh . WEB_FILE_EXT);
		@unlink($vh_info['doc_root'] . '/' . $vh . MYSQL_FILE_EXT);
	}

	public function migrateProductFrom()
	{
		$result = $this->callWhm('migrate_list_product');
		if ($result !== false && $result != 204) {
			$products = json_decode(base64_decode($result->get('products')));

			foreach ($products as $product) {
				$p[] = (array) $product;
			}

			$this->_tpl->assign('products', $p);
		}

		return $this->_tpl->fetch('migrate/product.html');
	}

	public function migrateProduct()
	{
		$arr['id'] = trim($_REQUEST['id']);

		if (!$arr['id']) {
			$this->migrate_result(500, '产品ID不能为空');
		}

		$result = $this->callWhm('migrate_product', $arr);

		if ($result === false) {
			$this->migrate_result(500, 'shell返回失败');
		}

		$product = (array) json_decode(base64_decode($result->get('product')));

		if (apicall('vhostproduct', 'add', array($product, 1))) {
			$this->migrate_result(200, '迁移成功');
		}

		$this->migrate_result(500, '迁移失败');
	}
}

?>