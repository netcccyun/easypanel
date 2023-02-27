<?php
class VhostAPI extends API
{
	public function __construct()
	{
	}

	/**
	 * 20130106新增入口
	 * @param arr $where_arr
	 * @param string $type
	 * @param arr $fields
	 * @param string $wherestr
	 * @return Ambigous <Mixed, boolean, mixed>
	 */
	public function get($where_arr = null, $type = 'rows', $fields = null, $wherestr = null)
	{
		return daocall('vhost', 'get', array($where_arr, $type, $fields, $wherestr));
	}

	public function getByName($name)
	{
		$arr['name'] = $name;
		$type = 'row';
		return $this->get($arr, $type);
	}

	public function set($where, $arr)
	{
		return daocall('vhost', 'set', array($where, $arr));
	}

	public function setByName($name, $arr)
	{
		$where['name'] = $name;
		return $this->set($where, $arr);
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
	 * 增加或修改站点
	 * @param $params
	 */
	public function addVhost($params)
	{
		$arr = '';

		if ($params['cdn'] == 1) {
			$params['subdir_flag'] = 1;
			$params['templete'] = 'html';
			$params['web_quota'] = 0;
			$params['db_quota'] = 0;
			$params['module'] = '';
			$params['max_worker'] = 0;
		}

		$product_id = $params['product_id'];
		$product_name = $params['product_name'];

		if (!$params['edit']) {
			$msg = apicall('vhost', 'checkName', array($params['name'], true));

			if ($msg !== true) {
				if ($msg === false) {
					setLastError('站点名称非法');
					return false;
				}

				setLastError('站点名称错误' . $msg);
				return false;
			}
		}

		if (0 < intval($product_id) || $product_name) {
			if ($product_name) {
				$product_info = daocall('product', 'getProductByName', array($product_name));

				if (!$product_info) {
					setLastError('产品没找到');
					return false;
				}

				$product_id = $product_info['id'];
			}
			else {
				$product_info = daocall('product', 'getProduct', array($product_id));

				if (!$product_info) {
					setLastError('产品没找到');
					return false;
				}
			}

			$arr['product_id'] = $product_id;
			$arr['edit'] = $params['edit'];
			$domain_dir_value = $product_info['subdir'];

			foreach ($product_info as $key => $value) {
				$arr[$key] = $value;
			}
		}
		else {
			$arr = $params;
			$arr['speed_limit'] = intval($params['speed_limit']) * 1024;
			$arr['ftp_connect'] = intval($params['ftp_connect']);
			$arr['ftp_usl'] = intval($params['ftp_usl']);
			$arr['ftp_dsl'] = intval($params['ftp_dsl']);
			$arr['htaccess'] = $params['htaccess'];
			$arr['ssi'] = $params['ssi'];
			$arr['ftp'] = $params['ftp'];
			$arr['access'] = $params['access'];
			$arr['log_file'] = $params['log_file'];
			$arr['log_handle'] = $params['log_handle'];
			$arr['ignore_backup'] = $params['ignore_backup'];
			$arr['module'] = $params['module'];
		}

		$arr['name'] = trim($params['name']);
		$arr['month'] = intval($params['month']);

		if ($params['edit']) {
			$arr['uid'] = trim($params['uid']);
			unset($arr['passwd']);
		}
		else {
			$arr['init'] = 1;
			$arr['passwd'] = trim($params['passwd']);
		}

		if ($params['gid']) {
			$arr['gid'] = trim($params['gid']);
		}

		if ($params['doc_root']) {
			$arr['doc_root'] = trim($params['doc_root']);
		}

		if ($params['vhost_domains']) {
			$arr['vhost_domains'] = trim($params['vhost_domains']);
		}

		$product = apicall('product', 'newProduct', array('vhost'));

		if ($product->sell($arr['name'], $product_id, $arr)) {
			if ($arr['module']) {
				$value = $arr['module'] == 'php' ? PHP_DEFAULT_VERSION : IIS_DEFAULT_VERSION;
				apicall('vhostinfo', 'add2', array($arr['name'], 'moduleversion', $value, 101));
			}

			return true;
		}

		return false;
	}

	/**
	 * 不要直接用这个，而应该用更简单的notice_cdn_changed
	 * @param  $vhostname
	 * 每次加一,并调用后台同步到各节点。
	 */
	public function updateVhostSyncseq($vhostname)
	{
		if (daocall('manynode', 'getCount') <= 0) {
			return false;
		}

		daocall('vhost', 'updateSyncseq', array($vhostname));
		return apicall('cdnPrimary', 'daemon_sync', array('+' . $vhostname));
	}

	/**
	 * 保留账号设置
	 * 防止数据库账号冲掉，设置root,mysql,kangle
	 * 防域名赠送时，无法绑定www的域名，因为这是主域名
	 * @param  $vhostname
	 */
	public function checkVhostname($vhostname)
	{
		$arr = array('mysql', 'root');

		if (in_array($vhostname, $arr)) {
			return false;
		}

		return true;
	}

	public function checkName($name, $checkExsit = true)
	{
		$arr = array('mysql', 'root');

		if (in_array($name, $arr)) {
			return false;
		}

		if (!preg_match('/^[0-9a-z_]{2,16}$/', $name)) {
			return '注册失败：账号只支持英文字母加数字，且不超过16个';
		}

		if ($checkExsit && daocall('vhost', 'getVhost', array($name))) {
			return '该用户名已使用';
		}

		return true;
	}

	public function rebootProcess($vh)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('core.whm', 'kill_process');
		$whmCall->addParam('vh', $vh);
		return $whm->call($whmCall, 10);
	}

	public function noticeChange($node = 'localhost', $name = null, $init = 0)
	{
		$whm = apicall('nodes', 'makeWhm', array($node));
		$whmCall = new WhmCall('core.whm', 'reload_vh');

		if ($name) {
			$whmCall->addParam('name', $name);
		}

		if ($init == 1) {
			$whmCall->addParam('init', 1);
		}

		if (!$whm->call($whmCall)) {
			return false;
		}

		$vhost = daocall('vhost', 'getVhost', array($name));
		if ($vhost && $vhost['module']) {
			modcall($vhost['module'], $vhost['module'] . '_update', array($vhost));
		}

		return true;
	}

	public function getQuota($user)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('vhost.whm', 'get_quota', 5);
		$whmCall->addParam('vh', $user['name']);
		$result = $whm->call($whmCall, 5);

		if (!$result) {
			return false;
		}

		$ret['web_limit'] = (string) $result->get('quota_limit');
		$ret['web_used'] = (string) $result->get('quota_used');

		if (0 < $user['db_quota']) {
			$db = apicall('nodes', 'makeDbProduct', array('localhost', $user['db_type']));

			if (is_object($db)) {
				$used = $db->used($user['db_name']);
				$ret['db_limit'] = $user['db_quota'];
				$ret['db_used'] = $used;
			}
		}

		return $ret;
	}

	public function getProduct($name)
	{
		return $_SESSION['product_id'][$name];
	}

	public function getNode($name)
	{
		return 'localhost';
	}

	public function getPrefix()
	{
		return '/home/ftp/';
	}

	public function changeSubtemplete($node, $name, $subtemplete)
	{
		if ($node == null) {
			$node = $this->getNode($name);
		}

		$arr2 = array('subtemplete' => $subtemplete);

		if (daocall('vhost', 'updateVhost', array($name, $arr2))) {
			return $this->noticeChange($node, $name, 1);
		}

		return false;
	}

	public function changeStatus($node, $name, $status)
	{
		$attr = array('status' => $status);

		if (daocall('vhost', 'updateVhost', array($name, $attr))) {
			$this->noticeChange($node, $name);
			$this->updateVhostSyncseq($name);
			return true;
		}

		return false;
	}

	public function cleanCache($url, $vh = null, $need_sync = true)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));

		if (!$whm) {
			exit('清除缓存失败,请联系管理员');
		}

		$whmCall = new WhmCall('core.whm', 'clean_cache');
		$whmCall->addParam('url', $url);
		$result = $whm->call($whmCall);

		if ($need_sync) {
			if (0 < daocall('manynode', 'getCount')) {
				apicall('cdnPrimary', 'daemon_sync', array('~' . $url));
			}
		}

		return $result;
	}

	/**
	 *
	 * 更改虚拟主机的ftp密码，
	 * @param  $node 节点，可为null
	 * @param  $user ftp名
	 * @param  $passwd 密码
	 */
	public function changePassword($node, $name, $passwd)
	{
		return daocall('vhost', 'updatePassword', array($name, $passwd));
	}

	public function delInfo($user, $name, $type, $value = null)
	{
		$node = $this->getNode($user);

		if (daocall('vhostinfo', 'delInfo', array($user, $name, $type, $value))) {
			return $this->noticeChange($node, $user);
		}

		return false;
	}

	public function addInfos($user, $infos)
	{
		foreach ($infos as $info) {
			daocall('vhostinfo', 'addInfo', array($user, $info[0], $info[1], $info[2], $info[3]));
		}
	}

	/**
	 *
	 * Enter description here ...
	 * @param  $user
	 * @param  $name
	 * @param  $type
	 * @param  $value
	 * @param  $multi false为不许重复，true为允许重复。
	 */
	public function addInfo($user, $name, $type, $value, $multi = true, $id = 1000)
	{
		$node = $this->getNode($user);

		if (daocall('vhostinfo', 'addInfo', array($user, $name, $type, $value, $multi, $id))) {
			return $this->noticeChange($node, $user);
		}

		return false;
	}

	public function updateInfo($user, $name, $arr, $type)
	{
		$node = $this->getNode($user);

		if (daocall('vhostinfo', 'updateInfo', array($user, $name, $arr, $type))) {
			return $this->noticeChange($node, $user);
		}

		return false;
	}

	public function updateAll($arr, $where_arr)
	{
		if (daocall('vhost', 'updateAll', array($arr, $where_arr))) {
			return $this->noticeChange();
		}

		return false;
	}

	public function check_ssl($vhost)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('core.whm', 'check_ssl');
		$whmCall->addParam('vh', $vhost);
		$result = $whm->call($whmCall);
		return $result->get('ssl');
	}

	/**
	 *
	 * Enter description here ...
	 * @param unknown_type $node
	 * @param unknown_type $name
	 * @param unknown_type $attr
	 * @deprecated 使用noticeChange
	 */
	private function sqliteUpdateVirtualHost($node, $name, $attr)
	{
		$whm = apicall('nodes', 'makeWhm', array($node));
		$whmCall = new WhmCall('core.whm', 'reload_vh');
		$whmCall->addParam('name', $name);
		$key = array_keys($attr);
		$i = 0;

		while ($i < count($key)) {
			$whmCall->addParam($key[$i], $attr[$key[$i]]);
			++$i;
		}

		return $whm->call($whmCall);
	}

	public function resync($vh, $sync_db = true, $init = true)
	{
		if (!is_array($vh)) {
			$attr = daocall('vhost', 'getVhost', array($vh, null));
		}
		else {
			$attr = $vh;
		}

		$attr['node'] = 'localhost';
		return $this->sync($attr, $sync_db, $init);
	}

	public function sync($attr, $sync_db = true, $init = true)
	{
		$attr['resync'] = '1';

		if ($init) {
			$attr['init'] = '1';
		}

		if (!$sync_db) {
			$attr['db_quota'] = 0;
		}

		$product = apicall('product', 'newProduct', array('vhost'));

		if (!$product->sync($attr['name'], $attr, $attr)) {
			return false;
		}

		apicall('cron', 'sync', array($attr));
		return $product->syncExtraInfo($attr['name'], $attr['node']);
	}

	public function del($node, $name)
	{
		$vhost = daocall('vhost', 'getVhost', array($name));
		daocall('vhostinfo', 'delAllInfo', array($name));
		daocall('vhost', 'delVhost', array($name));

		if (0 < daocall('manynode', 'getCount')) {
			apicall('cdnPrimary', 'daemon_sync', array('-' . $name));
		}

		$module = $vhost['module'];

		if (0 < $vhost['cron']) {
			apicall('cron', 'delAll', array($name));
		}

		if ($module) {
			@modcall($module, $module . '_destroy', array($vhost));
		}

		apicall('httpauth', 'delAll', array($name));
		$no_del_data = daocall('setting', 'get', array('no_del_data'));

		if (!$no_del_data) {
			if ($vhost['db_quota'] != 0) {
				$db = apicall('nodes', 'makeDbProduct', array('localhost', $vhost['db_type']));

				if (is_object($db)) {
					$db->remove($vhost['db_name']);
				}
			}

			$whm = apicall('nodes', 'makeWhm', array($node));
			if(!($vhost['cdn']==1 && $vhost['doc_root']=='cdn')){
				$whmCall = new WhmCall('vhost.whm', 'destroy_vh');
				$whmCall->addParam('vh', $name);
				$whmCall->addParam('name', $name);
				@$whm->call($whmCall);
			}
			$whmCall = new WhmCall('core.whm', 'del_vh');
			$whmCall->addParam('name', $name);
			$whmCall->addParam('destroy', 1);
			@$whm->call($whmCall);
			$whmCall = new WhmCall('core.whm', 'reload_vh');
			$whmCall->addParam('name', $name);

			if (!$whm->call($whmCall)) {
				return false;
			}
		}

		if (0 < $vhost['recordid']) {
			@apicall('record', 'delDnsdunRecord', array($vhost['recordid']));
		}

		@unlink('/vhs/kangle/phpini/php-'.$name.'.ini');

		return true;
	}

	public function checkPassword($username, $passwd)
	{
		$user = daocall('vhost', 'getVhost', array($username));

		if (!$user) {
			return false;
		}

		if (strtolower($user['passwd']) != strtolower(md5($passwd))) {
			return false;
		}

		return $user;
	}

	public function setSystemFile($vhost, $doc_root, $files)
	{
		if (is_win()) {
			$whm = apicall('nodes', 'makeWhm', array('localhost'));
			$whmCall = new WhmCall('vhost.whm', 'acl');
			$i = 0;

			foreach ($files as $file) {
				$whmCall->addParam('file' . $i, $file);
				++$i;
			}

			$whmCall->addParam('subdir', '0');
			$whmCall->addParam('vh', getRole('vhost'));
			$whmCall->addParam('ur', 'Administrators:rw,system:rw');
			$whm->call($whmCall, 60);
		}
		else {
			foreach ($files as $file) {
				chmod($doc_root . '/' . $file, 384);
			}
		}

		return true;
	}

	public function copyIndexForUser($name, $dir)
	{
		/*if (!isEnt()) {
			return false;
		}

		if (!apicall('access', 'checkEntAccess', array())) {
			return false;
		}*/

		$product = apicall('product', 'newProduct', array('vhost'));
		$docroot = $product->getDocRoot($name);
		$file = $GLOBALS['safe_dir'] . '/index.html';

		if (!file_exists($file)) {
			return false;
		}

		$filename = $docroot . '/' . $dir . '/index.html';

		if (file_exists($filename)) {
			return false;
		}

		return @exec(copy($file, $filename));
	}
}

?>