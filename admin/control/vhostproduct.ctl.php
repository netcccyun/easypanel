<?php
needRole('admin');
class VhostproductControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function pageListProduct()
	{
		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = 20;
		$count = 0;
		$list = daocall('product', 'pageListProduct', array($page, $page_count, &$count));
		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);

		if ($_REQUEST['success']) {
			switch ($_REQUEST['success']) {
			case 'add':
				$this->_tpl->assign('msg', '添加产品 ' . $_REQUEST['name'] . ' 成功');
				break;

			case 'del':
				$this->_tpl->assign('msg', '删除产品 ' . $_REQUEST['name'] . ' 成功');
				break;

			case 'edit':
				$this->_tpl->assign('msg', '修改产品 ' . $_REQUEST['name'] . ' 成功');
				break;

			default:
				break;
			}

			$this->_tpl->assign('success', 1);
		}

		if ($_REQUEST['error'] && isset($_SESSION['last_error'])) {
			$this->_tpl->assign('msg', $_SESSION['last_error']);
		}

		return $this->_tpl->fetch('vhostproduct/pageListProduct.html');
	}

	public function refreshTemplete()
	{
		apicall('whm', 'refreshTemplete', array($_REQUEST['name']));
		$this->showTemplete();
	}

	public function ajaxListSubTemplete()
	{
		$templete = apicall('nodes', 'listSubTemplete', array($_REQUEST['node'], $_REQUEST['templete']));
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		$str .= '<result node=\'' . $_REQUEST['node'] . '\'>';
		$i = 0;

		while ($i < count($templete)) {
			$str .= '<subtemplete>' . $templete[$i] . '</subtemplete>';
			++$i;
		}

		$str .= '</result>';
		return $str;
	}

	public function ajaxListTemplete()
	{
		$templete = apicall('nodes', 'listTemplete', array($_REQUEST['node']));
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		$str .= '<result node=\'' . $_REQUEST['node'] . '\'>';
		$i = 0;

		while ($i < count($templete)) {
			$str .= '<templete>' . $templete[$i] . '</templete>';
			++$i;
		}

		$str .= '</result>';
		return $str;
	}

	public function ajaxCheckUser()
	{
		$name = $_REQUEST['name'];

		if (apicall('vhost', 'checkName', array($name)) !== true) {
			exit('1');
		}

		exit('0');
	}

	public function editProductForm()
	{
		$vhostproduct = daocall('product', 'getProduct', array(intval($_REQUEST['id'])));

		if (!$vhostproduct) {
			return trigger_error('不能找到该产品');
		}

		$this->_tpl->assign('vhost', $vhostproduct);
		$this->_tpl->assign('action', 'editProduct');
		$this->_tpl->assign('modules', modlist());
		return $this->_tpl->display('vhostproduct/addProduct.html');
	}

	public function addProductFrom()
	{
		$vhostproduct = array('product_name' => 'defaultProduct', 'web_quota' => 1000, 'db_quota' => 1000, 'domain' => 0 - 1, 'subdir' => '/wwwroot');
		$vhostproduct['htaccess'] = 1;
		$vhostproduct['ftp'] = 1;
		$vhostproduct['log_file'] = 1;
		$vhostproduct['access'] = 1;
		$vhostproduct['max_connect'] = 0;
		$vhostproduct['max_worker'] = 8;
		$webalizer = daocall('setting', 'get', array('webalizer'));
		$this->_tpl->assign('webalizer', $webalizer);
		$this->_tpl->assign('action', 'addProduct');
		$this->assign('vhost', $vhostproduct);
		$this->_tpl->assign('modules', modlist());

		if ($_REQUEST['error']) {
			$this->_tpl->assign('error', $_SESSION['last_error']);
		}

		return $this->_tpl->display('vhostproduct/addProduct.html');
	}

	public function addProduct()
	{
		if (intval($_REQUEST['cdn']) == 1) {
			$_REQUEST['subdir_flag'] = 1;
			$_REQUEST['templete'] = 'html';
			$_REQUEST['web_quota'] = 0;
			$_REQUEST['db_quota'] = 0;
			$_REQUEST['max_worker'] = 0;
		}

		unset($_REQUEST['c']);
		unset($_REQUEST['a']);
		unset($_REQUEST['PHPSESSID']);
		$url = '?c=vhostproduct';
		unset($GLOBALS['last_error']);

		if (!apicall('vhostproduct', 'add', array($_REQUEST))) {
			if ($GLOBALS['last_error']) {
				$_SESSION['last_error'] = $GLOBALS['last_error'];
			}

			$url .= '&a=addProductFrom&error=1';
		}
		else {
			$url .= '&a=pageListProduct&success=add&name=' . $_REQUEST['product_name'];
		}

		header('Location:' . $url);
		exit();
	}

	public function editProduct()
	{
		if (intval($_REQUEST['cdn']) == 1) {
			$_REQUEST['subdir_flag'] = 1;
			$_REQUEST['templete'] = 'html';
			$_REQUEST['max_worker'] = 0;
			$_REQUEST['db_quota'] = 0;
		}

		if (!daocall('product', 'addProduct', array($_REQUEST, $_REQUEST['id']))) {
			trigger_error('更新失败');
			return false;
		}

		return $this->pageListProduct();
	}

	public function delProduct()
	{
		$productinfo = daocall('product', 'getProduct', array(intval($_REQUEST['id'])));

		if (!$productinfo) {
			trigger_error('没有该产品');
			return false;
		}

		$vh = daocall('vhost', 'getAllVhostByProduct_id', array($_REQUEST['id']));

		if (0 < count($vh)) {
			$_SESSION['last_error'] = '该产品已有引用，不能删除,引用数 ' . count($vh);
			header('Location:?c=vhostproduct&a=pageListProduct&error=1');
			exit();
		}

		if (!daocall('product', 'delProduct', array(intval($_REQUEST['id'])))) {
			$_SESSION['last_error'] = '删除失败';
			header('Location:?c=vhostproduct&a=pageListProduct&error=1');
			exit();
		}

		header('Location:?c=vhostproduct&a=pageListProduct&success=del&name=' . $productinfo['product_name']);
		exit();
	}

	public function left()
	{
		return dispatch('user', 'left');
	}
}

?>