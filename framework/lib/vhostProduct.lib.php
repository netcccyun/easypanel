<?php
class VhostProduct extends Product
{
	public function __construct()
	{
	}

	public function __destruct()
	{
	}

	public function getEntKey()
	{
		return 'rZnBhc2prZHBmb2tzYXBkZmtwYXNkb2tmcGFza2RmcG9rYXNkZnNk';
	}

	/**
	 * 得到产品信息
	 * @param $product_id 产品ID
	 * @return array(产品价格,是否支持月付<true|false>,是否支持试用<true|false>)
	 */
	public function getInfo($product_id, $susername = null)
	{
		$info = daocall('vhostproduct', 'getProduct', array($product_id));

		if ($info) {
			$info['node'] = 'localhost';
		}

		return $info;
	}

	public function addExpireTime($vhost, $month)
	{
		$expire_time = $month;

		if (daocall('vhost', 'addMonth', array($vhost, $expire_time))) {
			return true;
		}

		return false;
	}

	/**
	 * 给付产品,这一步只插入数据库
	 * @param  $susername
	 * @param  $params
	 * @param  $product_info
	 */
	protected function create($susername, &$params = array(), $product_info = array())
	{
		if (!apicall('vhost', 'checkVhostname', array($params['name']))) {
			trigger_error('注册失败：保留账号');
			return false;
		}

		$params['node'] = $product_info['node'];
		$params['create_time'] = $params['create_time'] ? $params['create_time'] : time();

		if ($params['month']) {
			$params['expire_time2'] = time() + daocall('vhost', 'getExpireTime', array($params['month']));
		}

		if ($params['edit'] == 1) {
			$arr = $params;
			unset($arr['passwd']);

			if ($arr['uid'] == '') {
				unset($arr['uid']);
			}

			if (daocall('vhost', 'updateVhost', array($params['name'], $arr))) {
				return true;
			}
		}

		$params['db_name'] = $params['db_type'] == 'sqlsrv' ? 'sq_' . $params['name'] : $params['name'];
		$params['doc_root'] = $params['doc_root'] ? $params['doc_root'] : $this->getDocRoot($params['name']);
		$params['gid'] = $params['gid'] ? $params['gid'] : $this->getNodeGroup($product_info['node']);
		$uid = daocall('vhost', 'insertVhost', array($params));

		if (!$uid) {
			return false;
		}

		$params['uid'] = $params['uid'] ? $params['uid'] : $uid;
		return true;
	}

	/**
	 * 同步额外信息，比如域名绑定
	 * @param unknown_type $suser
	 */
	public function syncExtraInfo($suser, $node)
	{
		return true;
	}

	/**
	 * 同步产品到磁盘或者远程
	 * @param  $user
	 * @param  $param
	 */
	public function sync($user, $params, $product_info)
	{
		unset($GLOBALS['last_error']);
		$this->addDomains($params, $product_info);
		$module = $params['module'];

		if ($module) {
			$result = modcall($module, $module . '_precreate', array($params));
		}

		$param = $params['name'];
		$whm = apicall('nodes', 'makeWhm', array($params['node']));
		$whmCall = new WhmCall('core.whm', 'reload_vh');
		$whmCall->addParam('name', $params['name']);

		if (!$module) {
			$this->initCommonParams($whmCall, $params);
		}

		if (0 < $params['db_quota'] && $params['db_type'] != 'sqlsrv') {
			$this->createMysqlDb($params, $product_info);
		}

		if (!$whm->call($whmCall)) {
			setLastError('sync whm->call result failed error=' . $GLOBALS['last_error']);
			return false;
		}

		$this->createWebalizerDir($params);
		$this->callModule($params);
		if ((0 < $product_info['db_quota'] || 0 < $params['db_quota']) && $params['db_type'] == 'sqlsrv') {
			$this->createSqlserverDb($params, $product_info);
		}

		return true;
	}

	/**
	 * 模块调用(新)
	 * @param unknown_type $params
	 */
	private function callModule($params)
	{
		if ($params['module']) {
			$whm = apicall('nodes', 'makeWhm', array($params['node']));
			$module = $params['module'];
			$whmCall = new WhmCall('vhost.whm', 'init_vh');
			$this->initCommonParams($whmCall, $params);

			if ($whm->call($whmCall)) {
				$result = modcall($module, $module . '_postcreate', array($params));
			}
		}
	}

	/**
	 * 创建webalizer日志分析目录
	 * @param unknown_type $params
	 */
	private function createWebalizerDir($params)
	{
		if (daocall('setting', 'get', array('webalizer'))) {
			$webalizer_dir = $params['doc_root'] . '/webalizer';
			@mkdir($webalizer_dir, 700);
		}
	}

	/**
	 * 处理域名域名绑定,以及赠送域名
	 * 虚拟主机才执行.CDN不处理
	 * @param unknown_type $params
	 * @param unknown_type $product_info
	 */
	private function addDomains($params, $product_info)
	{
		if ($params['cdn'] == 0) {
			$type = 0;
			$domain_dir_value = $product_info['subdir'];
			$vhost_domain = daocall('setting', 'get', array('vhost_domain'));

			if ($vhost_domain) {
				$domain_name = $params['name'] . '.' . $vhost_domain;
				apicall('vhost', 'addInfo', array($params['name'], $domain_name, $type, $domain_dir_value, false));
				apicall('record', 'addDnsdunRecord', array($params['name']));
			}

			if ($params['vhost_domains']) {
				$vhost_domains = explode(',', $params['vhost_domains']);

				foreach ($vhost_domains as $vhost_domain) {
					apicall('vhost', 'addInfo', array($params['name'], trim($vhost_domain), $type, $domain_dir_value, false));
				}
			}
		}
	}

	/**
	 * 创建mysql数据库
	 * @param unknown_type $params
	 * @param unknown_type $product_info
	 */
	private function createMysqlDb($params, $product_info)
	{
		$db = apicall('nodes', 'makeDbProduct', array($params['node'], $product_info['db_type']));

		if (is_object($db)) {
			$db->create($params);
		}
	}

	/**
	 * 创建sql server 数据库
	 * @param unknown_type $params
	 * @param unknown_type $product_info
	 */
	private function createSqlserverDb($params, $product_info)
	{
		$db = apicall('nodes', 'makeDbProduct', array($params['node'], $product_info['db_type']));

		if (is_object($db)) {
			$db->create($params);
		}
	}

	public function copyIndexForUser($name)
	{
		if (!isEnt()) {
			return false;
		}

		if (!apicall('access', 'checkEntAccess', array())) {
			return false;
		}

		$docroot = $this->getDocRoot($name);
		$file = dirname(__FILE__) . '/../../../../etc/index.html';
		$filename = $docroot . '/index.html';

		if (file_exists($filename)) {
			return true;
		}

		return @exec(copy($file, $filename));
	}

	public function checkParam($username, $suser)
	{
		return apicall('vhost', 'checkName', array($suser['name']));
	}

	public function getDocRoot($name)
	{
		$dev = $GLOBALS['node_cfg']['localhost']['dev'];
		$prefix = apicall('vhost', 'getPrefix');

		if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
			return $prefix . $name[0] . '/' . $name;
		}

		return $dev . $prefix . $name[0] . '/' . $name;
	}

	private function initCommonParams(&$whmCall, $params)
	{
		$whmCall->addParam('name', $params['name']);
		$whmCall->addParam('vh', $params['name']);
		$whmCall->addParam('init', $params['init']);
		$whmCall->addParam('quota_limit', $params['web_quota']);
		$whmCall->addParam('wwwroot', $params['subdir']);
		if (0 < $params['db_quota'] && $params['db_type'] == 'sqlsrv') {
			$whmCall->addParam('sqlsrv_dir', 'database');
		}
	}

	private function getNodeGroup($node)
	{
		if (is_win()) {
			return getRandPasswd(12);
		}

		return '1100';
	}

	protected function addMonth($susername, $month)
	{
		return daocall('vhost', 'addMonth', array($susername, $month));
	}

	protected function changeProduct($susername, $product)
	{
		return daocall('vhost', 'changeProduct', array($susername, $product['id'], $product['templete']));
	}

	protected function resync($username, $suser, $oproduct, $nproduct = null)
	{
		if ($nproduct == null) {
			return true;
		}

		$suser['resync'] = '1';
		$suser['init'] = '1';
		$suser['templete'] = $nproduct['templete'];
		$suser['product_id'] = $nproduct['id'];
		return $this->sync($username, $suser, $nproduct);
	}

	public function getSuser($susername)
	{
		$ret = daocall('vhost', 'getVhost', array($susername));

		if ($ret) {
			$ret['node'] = 'localhost';
		}

		return $ret;
	}
}

?>