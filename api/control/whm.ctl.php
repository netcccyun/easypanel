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
	else {
		$status = $whmResult->status;
		$ret = $whmResult->getResult();
	}

	if (is_array($info)) {
		$ret = array_merge($ret, $info);
	}

	whm_return((int) $status, $ret);
}

function proxy_call($whm_package = 'core.whm', $info = null)
{
	$whm_call = $_REQUEST['a'];
	$whm = apicall('nodes', 'makeWhm', array('localhost'));
	$whmCall = new WhmCall($whm_package, $whm_call);

	foreach ($_REQUEST as $name => $value) {
		if ($name == 'a' || $name == 'c') {
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
				unset($row['gid']);
			}

			whm_return(200, $vh);
		}

		whm_return(500);
	}

	public function listVh()
	{
		$list = daocall('vhost', 'listVhost', array());

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
		$name = trim($_REQUEST['name']);
		$result = apicall('vhost', 'del', array('localhost', $name));
		whm_return($result ? 200 : 500);
	}

	public function info_vh()
	{
		proxy_call();
	}

	public function getDbUsed()
	{
		$name = $_REQUEST['name'];
		$db = apicall('nodes', 'makeDbProduct', array('localhost'));
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
		if (trim($_REQUEST['name']) == '') {
			whm_return('500 name is empty or Presence');
			return false;
		}

		unset($_REQUEST['doc_root']);
		$_REQUEST['htaccess'] = $_REQUEST['htaccess'] == 1 ? '.htaccess' : null;
		$_REQUEST['access'] = $_REQUEST['access'] == 1 ? 'access.xml' : null;
		$_REQUEST['log_file'] = $_REQUEST['log_file'] == 1 ? 'logs/access.log' : null;
		$result = apicall('vhost', 'addVhost', array($_REQUEST));
		whm_return($result ? 200 : 500);
	}

	public function list_vhost()
	{
		$vhs = daocall('vhost', 'listVhost', array());

		if (count($vhs) < 0) {
			whm_return(500);
		}

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
		$vh_info = daocall('vhost', 'getVhost', array(trim($_REQUEST['vh'])));

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
		$info = daocall('vhostinfo', 'getInfo', array(trim($_REQUEST['vh'])));
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
		$vh = trim($_REQUEST['vh']);

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

		if (count($products) < 0) {
			whm_return(204);
		}

		$result = base64_encode(json_encode($products));
		whm_return(200, array('products' => $result));
	}

	public function migrate_down()
	{
		$file_name = trim($_REQUEST['f']);
		$fp = fopen(TMP_FILE_DIR . $file_name, 'rb');

		if (!$fp) {
			header('Status: 404');
			exit();
		}

		Header('Content-type:application/octet-stream ');
		Header('Content-Disposition: attachment; filename=' . basename($file_name));

		while (true) {
			$str = fread($fp, 8192);

			if ($str == FALSE) {
				break;
			}

			echo $str;
			flush();
		}

		fclose($fp);
		exit();
	}
}

?>