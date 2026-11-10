<?php
function whm_return2($whmResult, $info = null)
{
	if ($whmResult === false) {
		$status = 500;
		$ret = array();
	}
	else if ($whmResult === true) {
		$status = 200;
		$ret = array();
	}
	else if (is_object($whmResult) && method_exists($whmResult, 'getResult')) {
		$status = $whmResult->status;
		$ret = $whmResult->getResult();
	}
	else {
		$status = is_numeric($whmResult) ? intval($whmResult) : 500;
		$ret = array();
	}

	if (is_array($info)) {
		$ret = array_merge($ret, $info);
	}

	whm_return((int) $status, $ret);
}

function proxy_call($whm_package = 'core.whm', $info = null)
{
	$whm_call = ep_request('a');
	$whm = apicall('nodes', 'makeWhm', array('localhost'));
	if (!is_object($whm)) {
		whm_return(500);
	}

	$whmCall = new WhmCall($whm_package, $whm_call);

	foreach ($_REQUEST as $name => $value) {
		if ($name == 'a' || $name == 'c') {
			continue;
		}

		if (is_array($value) || is_object($value)) {
			continue;
		}

		$whmCall->addParam($name, $value);
	}

	$result = $whm->call($whmCall);
	whm_return2($result, $info);
}

define('TMP_FILE_DIR', $GLOBALS['safe_dir'] . '../tmp/');
class WhmControl extends Control
{
	private function _require_ip_list_post()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
			whm_return(405, 'POST required');
		}
	}

	private function _ip_list_post_ip()
	{
		$ip = isset($_POST['ip']) ? trim(ep_str($_POST['ip'])) : '';
		if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
			whm_return(400, 'Invalid IP address');
		}

		// Use the same representation for adding and removing IPv6 addresses.
		$ip = inet_ntop(inet_pton($ip));
		if ($ip === false) {
			whm_return(400, 'Invalid IP address');
		}
		return $ip;
	}

	private function _update_ip_list($prefix)
	{
		$this->_require_ip_list_post();
		$ip = $this->_ip_list_post_ip();
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		if (!is_object($whm)) {
			whm_return(500);
		}

		$call = new WhmCall('core.whm', 'report_ip');
		$call->addParam('ips', $prefix . $ip);
		whm_return2($whm->call($call));
	}

	public function add_black_ip()
	{
		$dynamic = isset($_POST['dynamic']) ? ep_str($_POST['dynamic']) : '0';
		if ($dynamic !== '0' && $dynamic !== '1') {
			whm_return(400, 'Invalid dynamic flag');
		}
		$this->_update_ip_list($dynamic === '1' ? '0' : '2');
	}

	public function remove_black_ip()
	{
		$this->_update_ip_list('4');
	}

	public function add_white_ip()
	{
		$this->_update_ip_list('3');
	}

	public function remove_white_ip()
	{
		$this->_update_ip_list('4');
	}

	public function check_black_ip()
	{
		$this->_require_ip_list_post();
		$ip = $this->_ip_list_post_ip();
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		if (!is_object($whm)) {
			whm_return(500);
		}

		$call = new WhmCall('core.whm', 'black_list');
		$call->addParam('a', 'check');
		$call->addParam('ip', $ip);
		$result = $whm->call($call);
		if (!is_object($result) || $result->getCode() !== 200) {
			whm_return2($result);
		}
		$hit = $result->get('hit');
		if ((string) $hit !== '0' && (string) $hit !== '1') {
			whm_return(502, 'Invalid Kangle response');
		}
		whm_return(200, array('hit' => intval($hit)));
	}

	public function list_dynamic_black_ips()
	{
		$this->_require_ip_list_post();
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		if (!is_object($whm)) {
			whm_return(500);
		}

		$result = $whm->call(new WhmCall('core.whm', 'black_list'));
		if (!is_object($result) || $result->getCode() !== 200) {
			whm_return2($result);
		}
		$ips = array();
		foreach ($result->getAll('ip') as $ip) {
			$ips[] = (string) $ip;
		}
		whm_return(200, array('ips' => $ips));
	}

	public function clear_ip_list()
	{
		$this->_require_ip_list_post();
		if (!isset($_POST['confirm']) || ep_str($_POST['confirm']) !== '1') {
			whm_return(400, 'confirm=1 required');
		}
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		if (!is_object($whm)) {
			whm_return(500);
		}

		$call = new WhmCall('core.whm', 'black_list');
		$call->addParam('a', 'clear');
		whm_return2($whm->call($call));
	}

	public function change_password()
	{
		$result = apicall('vhost', 'changePassword', array('localhost', $_REQUEST['name'], $_REQUEST['passwd']));
		whm_return($result ? 200 : 500);
	}

	public function update_vh()
	{
		$result = apicall('vhost', 'changeStatus', array('localhost', $_REQUEST['name'], $_REQUEST['status']));
		whm_return($result ? 200 : 500);
	}

	public function check_vh_db()
	{
		$ret = apicall('nodes', 'checkNode');
		whm_return('200', $ret);
	}

	public function list_gtvh()
	{
		proxy_call();
	}

	public function list_tvh()
	{
		proxy_call();
	}

	public function is_name()
	{
		$name = daocall('vhost', 'getVhost', array($_REQUEST['name']));
		whm_return($name ? 200 : 500);
	}

	public function getVh()
	{
		$vh = daocall('vhost', 'getVhost', array($_REQUEST['name']));

		if ($vh) {
			if (!$_REQUEST['showpasswd']) {
				unset($vh['passwd']);
				unset($vh['gid']);
			}

			whm_return(200, $vh);
		}

		whm_return(500);
	}

	public function listVh()
	{
		$list = daocall('vhost', 'listVhost', array());
		$newlist = array();

		if ($list) {
			if (!$_REQUEST['showpasswd']) {
				foreach ($list as $key => $row) {
					unset($row['passwd']);
					unset($row['gid']);
					$newlist[$key] = $row;
				}
			}
			else {
				$newlist = $list;
			}
		}

		whm_return(200, array('rows' => $newlist));
	}

	public function del_vh()
	{
		$name = trim(ep_request('name'));
		$result = apicall('vhost', 'del', array('localhost', $name));
		whm_return($result ? 200 : 500);
	}

	public function info_vh()
	{
		proxy_call();
	}

	public function getDbUsed()
	{
		$name = ep_request('name');
		$db = apicall('nodes', 'makeDbProduct', array('localhost'));

		if (!$db) {
			whm_return(500);
			return;
		}

		$db_used = $db->used($name, true);

		if ($db_used !== false) {
			whm_return(200, array('used' => $db_used));
		}

		whm_return(500);
	}

	public function dump_flow()
	{
		proxy_call();
	}

	public function info()
	{
		$info = array();
		$info['easypanel_version'] = EASYPANEL_VERSION;
		proxy_call('core.whm', $info);
	}

	public function get_quota()
	{
		proxy_call('vhost.whm');
	}

	public function reload_vh()
	{
		proxy_call();
	}

	public function add_vh()
	{
		if (trim(ep_request('name')) == '') {
			whm_return('500 name is empty or Presence');
			return false;
		}

		unset($_REQUEST['doc_root'], $_REQUEST['uid'], $_REQUEST['gid'], $_REQUEST['edit'], $_REQUEST['node'],
			$_REQUEST['init'], $_REQUEST['create_time'], $_REQUEST['sync_seq'],
			$_REQUEST['recordid'], $_REQUEST['db_name']);
		$_REQUEST['htaccess'] = (isset($_REQUEST['htaccess']) && $_REQUEST['htaccess'] == 1) ? '.htaccess' : null;
		$_REQUEST['access'] = (isset($_REQUEST['access']) && $_REQUEST['access'] == 1) ? 'access.xml' : null;
		$_REQUEST['log_file'] = (isset($_REQUEST['log_file']) && $_REQUEST['log_file'] == 1) ? 'logs/access.log' : null;
		$result = apicall('vhost', 'addVhost', array($_REQUEST));
		whm_return($result ? 200 : 500);
	}

	public function list_vhost()
	{
		$vhs = daocall('vhost', 'listVhost', array());

		if (!is_array($vhs) || count($vhs) <= 0) {
			whm_return(500);
		}

		$v = array();
		foreach ($vhs as $vh) {
			$v[] = $vh['name'];
		}

		$v = json_encode($v);
		whm_return(200, array('vh' => $v));
	}

	public function migrate_domain()
	{
		if (!$_REQUEST['vh']) {
			whm_return(500);
		}

		$ret = array();
		$vh_info = daocall('vhost', 'getVhost', array(trim(ep_request('vh'))));

		if (!$vh_info) {
			whm_return(400);
		}

		if (0 < $vh_info['db_quota'] && $vh_info['db_type'] != 'sqlsrv') {
			$db = apicall('nodes', 'makeDbProduct', array('localhost', $vh_info['db_type']));

			if (is_object($db)) {
				$password = $db->dumpOutPassword($vh_info['db_name']);
				$ret['db_password'] = $password;
			}
		}

		$ret['vh'] = $vh_info;
		$info = daocall('vhostinfo', 'getInfo', array(trim(ep_request('vh'))));
		$ret['info'] = $info;
		$ret = base64_encode(json_encode($ret));
		whm_return(200, array('vh' => $ret));
	}

	public function migrate_hello_vh_web()
	{
		$vh = $_REQUEST['vh'];

		if (!$vh) {
			whm_return(500);
		}

		$result = apicall('migrate', 'zipVhWeb', array($vh, TMP_FILE_DIR));
		whm_return2($result);
	}

	public function migrate_hello_vh_sql()
	{
		$vh = $_REQUEST['vh'];

		if (!$vh) {
			whm_return(500);
		}

		$result = apicall('migrate', 'zipVhSql', array($vh, TMP_FILE_DIR));
		whm_return2($result);
	}

	public function migrate_query()
	{
		$session = $_REQUEST['session'];
		$vh = $_REQUEST['vh'] ? $_REQUEST['vh'] : '';
		$result = apicall('migrate', 'query', array($session, $vh));
		whm_return2($result);
	}

	public function migrate_complete()
	{
		$vh = trim(ep_request('vh'));

		if (!$vh) {
			whm_return2(500);
		}

		$result = apicall('migrate', 'migrateComplete', array($vh, TMP_FILE_DIR));
		whm_return2($result);
	}

	public function migrate_product()
	{
		$id = intval($_REQUEST['id']);
		$product_info = daocall('product', 'getProduct', array($id));

		if (!$product_info) {
			whm_return(500);
		}

		$result = base64_encode(json_encode($product_info));
		whm_return(200, array('product' => $result));
	}

	public function migrate_list_product()
	{
		$products = daocall('product', 'getProducts', array());

		if (!is_array($products) || count($products) <= 0) {
			whm_return(204);
		}

		$result = base64_encode(json_encode($products));
		whm_return(200, array('products' => $result));
	}

	public function migrate_down()
	{
		$file_name = ep_safe_name(basename(str_replace('\\', '/', ep_request('f'))));

		if ($file_name === '') {
			header('Status: 404');
			exit();
		}

		$fp = fopen(TMP_FILE_DIR . $file_name, 'rb');

		if (!$fp) {
			header('Status: 404');
			exit();
		}

		Header('Content-type:application/octet-stream ');
		Header('Content-Disposition: attachment; filename=' . basename($file_name));

		while (true) {
			$str = fread($fp, 8192);

			if ($str === false || $str === '') {
				break;
			}

			echo $str;
			flush();
		}

		fclose($fp);
		exit();
	}
}
