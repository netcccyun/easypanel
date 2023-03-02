<?php
needRole('admin');
class ManynodeControl extends control
{
	private $daoname = 'manynode';

	/**
	 * 增加from
	 * Enter description here ...
	 */
	public function addFrom()
	{
		$local_cdn_name = daocall('setting', 'get', array('local_cdn_name'));

		if (!$local_cdn_name) {
			return $this->_tpl->fetch('manynode/addlocal.html');
		}

		if ($_REQUEST['name']) {
			$node = daocall($this->daoname, 'get', array($_REQUEST['name']));
			$this->_tpl->assign('edit', 1);
			$this->_tpl->assign('node', $node);
		}else{
			$this->_tpl->assign('node', ['port'=>3312]);
		}

		return $this->_tpl->display('manynode/addfrom.html');
	}

	public function getNode()
	{
		$nodes = daocall('manynode', 'get', array());
		$count = count($nodes);
		$status = '200';

		if ($count <= 0) {
			$status = '404';
		}

		$ret['status'] = $status;
		$ret['count'] = $count;
		$ret['nodes'] = $nodes;
		echo json_encode($ret);
	}

	public function testNode()
	{
		$nodename = $_REQUEST['name'];
		$node = daocall('manynode', 'get', array($nodename));

		if (!$node) {
			exit('404');
		}

		if (apicall('cdn', 'test_node', array($node))) {
			exit('200');
		}

		exit('500');
	}

	/**
	 * @deprecated
	 * Enter description here ...
	 */
	public function addLocalNameFrom()
	{
		$local_cdn_name = daocall('setting', 'get', array('local_cdn_name'));

		if ($local_cdn_name) {
			$this->_tpl->assign('local_cdn_name', $local_cdn_name);
		}

		return $this->_tpl->fetch('manynode/addlocal.html');
	}

	/**
	 * 增加主节点名称
	 * @deprecated
	 * Enter description here ...
	 */
	public function addLocalname()
	{
		if (daocall('setting', 'add', array('local_cdn_name', $_REQUEST['local_cdn_name']))) {
			return $this->addFrom();
		}

		exit('增加主节点名称失败');
	}

	public function sync()
	{
		apicall('cdnPrimary', 'sync_node', array($_REQUEST['name']));
		return $this->pageList();
	}

	/**
	 * 增加
	 * 修改 REPLACE
	 * Enter description here ...
	 */
	public function add()
	{
		if (!daocall('setting', 'get', array('local_cdn_name'))) {
			exit('请先增加主节点名称');
		}

		$name = trim($_REQUEST['name']);

		if (!preg_match('/^[0-9a-z]{2,15}$/', $name)) {
			exit('节点名称请用数字和字母的集合');
		}

		$host = trim($_REQUEST['host']);
		$port = intval($_REQUEST['port']);
		$skey = trim($_REQUEST['skey']);
		$mem = $_REQUEST['mem'];
		if (!$name || !$host) {
			return $this->pageList();
		}

		$return = daocall($this->daoname, 'add', array($name, $host, $port, $skey, $mem));

		if (!$return) {
			$this->_tpl->assign('msg', '增加失败');
			return $this->_tpl->fetch('msg.html');
		}

		apicall('cdnPrimary', 'sync_node', array($name));
		return $this->pageList();
	}

	public function syncDelNodeCdn()
	{
		if ($_REQUEST['name'] == '' || $_REQUEST['host'] == '' || $_REQUEST['skey'] == '') {
			return false;
		}

		$node['name'] = $_REQUEST['name'];
		$node['skey'] = $_REQUEST['skey'];
		$node['host'] = $_REQUEST['host'];
		$node['port'] = $_REQUEST['port']?$_REQUEST['port']:3312;
		apicall('cdn', 'del_node', array($node));
	}

	/**
	 * 删除
	 * Enter description here ...
	 */
	public function del()
	{
		$name = $_REQUEST['name'];
		$node = daocall('manynode', 'get', array($name));
		$return = daocall($this->daoname, 'del', array($name));

		if (!$return) {
			$this->_tpl->assign('msg', '增加失败');
			return $this->_tpl->fetch('msg.html');
		}

		echo '<script language=\'javascript\' src=\'?c=manynode&a=syncDelNodeCdn&name=' . $node['name'] . '&skey=' . $node['skey'] . '&host=' . $node['host'] . '&port=' . $node['port'] . '\'></script>';
		return $this->pageList();
	}

	/**
	 * 列表
	 * Enter description here ...
	 */
	public function pageList()
	{
		if ($_REQUEST['local_cdn_name']) {
			daocall('setting', 'add', array('local_cdn_name', $_REQUEST['local_cdn_name']));
		}

		$local_cdn_name = daocall('setting', 'get', array('local_cdn_name'));
		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = 20;
		$count = 0;
		$list = daocall($this->daoname, 'pageList', array($page, $page_count, &$count));
		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		$this->_tpl->assign('local_cdn_name', $local_cdn_name);
		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);
		return $this->_tpl->display('manynode/pagelist.html');
	}
}

?>