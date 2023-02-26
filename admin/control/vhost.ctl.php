<?php
needRole('admin');
class VhostControl extends Control
{
	/**
	 * @param unknown_type $user
	 * @param unknown_type $call
	 * @return boolean
	 */
	private function getUser($user, $call)
	{
		$list = daocall('vhost', $call, array($user, 'row'));

		if ($list) {
			$product_info = apicall('product', 'getVhostProduct', array($list['product_id']));
			$list['product_name'] = $product_info['name'];
			$this->_tpl->assign('row', $list);
			$list = daocall('vhostinfo', 'getDomain', array($list['name']));
			$this->_tpl->assign('list', $list);
			return true;
		}

		return false;
	}

	/**
	 * name 如果为域名,则为单个搜索,传入string $search_key
	 * 如果不是域名,则为like搜索.传入数组$search_key
	 */
	public function pageVhost()
	{
		$search_key = null;

		if ($_REQUEST['name']) {
			$name = trim($_REQUEST['name']);

			if (strchr($name, '.')) {
				$domain = daocall('vhostinfo', 'findDomain', array($name));

				if ($domain) {
					$search_key = $domain['vhost'];
				}
			}
			else {
				$search_key['name'] = $name;
			}
		}

		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = daocall('setting', 'get', array('page_count'));
		$page_count = $page_count ? $page_count : 15;
		$count = 0;
		$list = daocall('vhost', 'pageVhost', array($search_key, $page, $page_count, &$count));
		@load_conf('pub:vhostproduct');
		$i = 0;

		while ($i < count($list)) {
			$list[$i]['product_name'] = $GLOBALS['vhostproduct_cfg'][$list[$i]['product_id']]['name'];
			++$i;
		}

		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		$products = daocall('product', 'getProducts');
		$counts = count($products);
		$this->_tpl->assign('count', $count);

		if (is_array($products)) {
			foreach ($products as $product) {
				$p[$product['id']] = $product;
			}
		}

		$p[0] = array('product_name' => '自由类型');
		$this->_tpl->assign('product', $p);
		$this->_tpl->assign('username', getRole('user'));
		$this->_tpl->assign('name', $name);
		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);
		$this->_tpl->display('vhostproduct/listVhost.html');
	}

	/**
	 * 删除无用域名
	 */
	public function delExpireDomain()
	{
		$vhosts = daocall('vhostinfo', 'selectExpireDomain', array('vhost', 'name'));

		if (is_array($vhosts)) {
			foreach ($vhosts as $vhost) {
				echo '正在删除' . $vhost['vhost'] . '域名信息 <br>';
				daocall('vhostinfo', 'delAllInfo', array($vhost['vhost']));
			}
		}

		exit('删除完成');
	}

	/**
	 * 删除主机
	 */
	public function del()
	{
		$vhost = trim($_REQUEST['name']);
		$json['code'] = 400;
		@apicall('cdn', 'delCdnAccessFile', array($vhost));

		if (@apicall('vhost', 'del', array('localhost', $vhost))) {
			$json['code'] = 200;
		}
		else {
			if ($GLOBALS['last_error']) {
				$json['msg'] = $GLOBALS['last_error'];
			}
		}

		exit(json_encode($json));
	}

	/**
	 * 暂停主机或开通
	 */
	public function setStatus()
	{
		$vhost = trim($_REQUEST['name']);
		$json['code'] = 400;

		if (!$vhost) {
			$json['msg'] = '参数name不能为空';
			exit(json_encode($json));
		}

		$node = daocall('vhost', 'getNode', array($vhost));

		if (!$node) {
			$json['msg'] = '获取节点信息失败';
			exit(json_encode($json));
		}

		if (apicall('vhost', 'changeStatus', array($node, $vhost, $_REQUEST['status']))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	/**
	 * 重建虚拟主机
	 */
	public function resync()
	{
		$vhost = trim($_REQUEST['name']);
		$json['code'] = 400;

		if (!$vhost) {
			$json['msg'] = '参数name不能为空';
			exit(json_encode($json));
		}

		if (apicall('vhost', 'resync', array($vhost))) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	/**
	 * 重设密码
	 */
	public function randPassword()
	{
		$vhost = $_REQUEST['name'];
		$passwd = getRandPasswd();
		$node = daocall('vhost', 'getVhost', array($vhost));
		$node['node'] = 'localhost';

		if (apicall('vhost', 'changePassword', array($node['node'], $vhost, $passwd))) {
			exit('成功,新密码: ' . $passwd);
			return NULL;
		}

		exit('重设密码出错');
	}

	/**
	 * 重设数据库密码
	 */
	public function randDbPassword()
	{
		$vhost = $_REQUEST['name'];
		$node = daocall('vhost', 'getVhost', array(
	$vhost,
	array('db_quota', 'uid')
	));
		$node['node'] = 'localhost';
		if (!$node && $node['db_quota'] == 0) {
			$msg = '重设数据库密码出错，该产品没有数据库。';
		}
		else {
			$passwd = getRandPasswd();
			$db = apicall('nodes', 'makeDbProduct', array($node['node']));
			if ($db && $db->password($node['uid'], $passwd)) {
				$msg = '新数据库密码是: ' . $passwd;
			}
			else {
				$msg = '重设数据库密码出错，请联系管理员。';
			}
		}

		$this->_tpl->assign('msg', $msg);
		return $this->showVhost();
	}

	/**
	 * 模拟登陆
	 */
	public function impLogin()
	{
		registerRole('vhost', $_REQUEST['name']);
		header('Location: /vhost/');
		exit();
	}
}

?>