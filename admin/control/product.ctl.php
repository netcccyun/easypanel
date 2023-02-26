<?php
needRole('admin');
class ProductControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function test()
	{
		apicall('shell', 'check_flow', array());
	}

	public function productList()
	{
		if ($_REQUEST['target'] == '') {
			$_REQUEST['target'] = 'self';
		}

		$products = apicall('product', 'getProducts', null);
		$this->_tpl->assign('products', $products);
		$this->_tpl->assign('target', $_REQUEST['target']);
		$this->_tpl->display('product/product_list.js');
		exit();
	}

	public function sellForm()
	{
		if (getRole('admin') == '') {
			return '您还没有登录，请先登录';
		}

		$edit = $_REQUEST['edit'];

		if ($edit) {
			$vhost = daocall('vhost', 'getVhost', array($_REQUEST['name'], null));
			$this->assign('vhost', $vhost);
			$this->assign('edit', 1);
			$product_id = $vhost['product_id'];
		}
		else {
			$product_id = $_REQUEST['product_id'];
		}

		$products = daocall('product', 'getProducts');

		if (!$edit) {
			$setting = daocall('setting', 'getAll', array());
			$this->_tpl->assign('setting', $setting);
		}

		$this->assign('product_id', $product_id);
		$this->assign('products', $products);
		$this->_tpl->assign('modules', modlist());
		return $this->fetch('vhostproduct/sell.html');
	}

	public function check()
	{
		$product_type = $_REQUEST['product_type'];
		$name = trim($_REQUEST['name']);
		$this->_tpl->assign('product_type', $product_type);
		$this->_tpl->assign('param', $name);

		switch ($product_type) {
		case 'vhost':
			$result = daocall('vhost', 'check', array($name));
		}

		if ($result) {
			$this->_tpl->assign('result', 1);
		}
		else {
			$this->_tpl->assign('result', 0);
		}

		$this->_tpl->display('product/product_check_result.html');
		exit();
	}

	/**
	 * 开通站点的控制入口
	 */
	public function sell()
	{
		$_REQUEST['double_name_as_error'] = 1;
		$name = trim($_REQUEST['name']);

		if (!$name) {
			return $this->displayMyMsg('账号不能为空');
		}

		$msg = apicall('vhost', 'addVhost', array($_REQUEST));

		if ($msg != true) {
			$msg .= '失败 ' . $msg;

			if ($GLOBALS['last_error']) {
				$msg .= $GLOBALS['last_error'];
			}
		}
		else {
			$msg = '创建网站';

			if ($_REQUEST['edit'] == 1) {
				$msg = '修改网站';
			}

			$msg .= '成功';
		}

		return $this->displayMyMsg($msg);
	}

	public function syncCdn()
	{
		notice_cdn_changed($_REQUEST['name']);
	}

	private function displayMyMsg($msg)
	{
		$this->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}

	/**
	 * 
	 * Enter description here ...
	 */
	public function addExpireTime()
	{
		needRole('admin');
		$user = getRole('admin');
		$product_type = $_REQUEST['product_type'] ? $_REQUEST['product_type'] : 'vhost';
		$product = apicall('product', 'newProduct', array('vhost'));

		if ($product->addExpireTime($_REQUEST['name'], $_REQUEST['month'])) {
			exit('延时成功');
		}

		exit('延时失败');
	}

	/**
	 * @deprecated 未使用
	 * Enter description here ...
	 */
	public function upgrade()
	{
		needRole('admin');
		$user = getRole('admin');
		$product = apicall('product', 'newProduct', array($_REQUEST['product_type']));

		if ($product->upgrade($user, $_REQUEST['name'], $_REQUEST['product_id'])) {
			$this->_tpl->assign('msg', '升级成功');
		}
		else {
			$this->_tpl->assign('msg', '升级失败');
		}

		return $this->_tpl->fetch('msg.html');
	}

	/**
	 * @deprecated 暂停使用
	 * Enter description here ...
	 */
	public function syncProductAllVhost()
	{
		set_time_limit(0);
		$product_id = intval($_REQUEST['product_id']);
		$product_info = daocall('product', 'getProduct', array($product_id));
		$vhosts = daocall('vhost', 'getAllVhostByProduct_id', array($product_id));

		if (count($vhosts) < 0) {
			exit('该产品没有被使用,不需要重建');
		}

		foreach ($vhosts as $vh) {
			echo '请等待，正在同步中...';

			if (!daocall('vhost', 'updateVhost', array($vh['name'], $product_info))) {
				echo $vh['name'] . '更新数据失败';
			}

			echo $vh['name'] . '同步成功<br>';
		}

		echo '同步完成，<a href=\'?c=vhostproduct&a=pageListProduct\'>返回</a>';
		exit();
	}

	public function left()
	{
	}
}

?>