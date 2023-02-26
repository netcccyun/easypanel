<?php
needRole('admin');
class ServersControl extends Control
{
	public function serverAdd()
	{
		$arr['server'] = trim($_REQUEST['server']);
		$arr['master'] = intval($_REQUEST['master']);
		$arr['ns'] = trim($_REQUEST['ns']);
		if ($arr['server'] == '' || $arr['ns'] == '') {
			exit('名称,ns/不能为空');
		}

		if (substr($arr['ns'], 0 - 1) != '.') {
			exit('NS最后请加上.,如ns.kanglesoft.com.');
		}

		if (!daocall('servers', 'serverAdd', array($arr))) {
			exit('添加失败');
		}

		exit('成功');
	}

	public function dig()
	{
		$ret = apicall('bind', 'domainDig', array($_REQUEST['domain'], $_REQUEST['node'], $_REQUEST['view']));
		$this->assign('dig', $ret);
		return $this->tools();
	}

	public function tools()
	{
		$this->assign('server', $_REQUEST['server']);
		$this->assign('domain', $_REQUEST['domain']);
		$views = daocall('views', 'viewsList', array());
		$slaves = daocall('slaves', 'slavesGet', array(
	array('server' => $_REQUEST['server'])
	));
		$nodes = array('localhost');

		foreach ($slaves as $slave) {
			$nodes[] = $slave['slave'];
		}

		$this->assign('nodes', $nodes);
		$this->assign('views', $views);
		$this->assign('view', $_REQUEST['view']);
		$this->assign('node', $_REQUEST['node']);
		return $this->fetch('servers/tools.html');
	}

	public function getServers()
	{
		$servers = daocall('servers', 'serverGet', array());
		$count = count($servers);
		$info['count'] = $count;
		$info['servers'] = $servers;
		$info = json_encode($info);
		exit($info);
	}

	public function serverUpdate()
	{
		$arr['ns'] = trim($_REQUEST['ns']);
		$oldserver = trim($_REQUEST['oldserver']);

		if ($_REQUEST['newserver']) {
			$arr['server'] = trim($_REQUEST['newserver']);
		}

		if (!$arr['ns']) {
			exit('ns不能为空');
		}

		if (!$oldserver) {
			exit('服务器名称不能为空');
		}

		if (apicall('server', 'serverUpdate', array($oldserver, $arr))) {
			exit('成功');
		}

		exit('修改失败');
	}

	public function serverPageList()
	{
		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = 20;
		$count = 0;
		$list = daocall('servers', 'serverPageList', array($page, $page_count, &$count));
		$slaves = daocall('slaves', 'slavesGet', array());

		if (0 < count($slaves)) {
			foreach ($list as &$li) {
				foreach ($slaves as $sl) {
					if ($li['server'] == $sl['server']) {
						$li['slaves'][] = $sl;
					}
				}
			}
		}

		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);
		return $this->_tpl->fetch('servers/pagelist.html');
	}

	public function serverDel()
	{
		$server = trim($_REQUEST['server']);

		if ($server == '') {
			exit('error');
		}

		if (!apicall('server', 'serverDel', array($server))) {
			exit('删除失败');
		}

		exit('成功');
	}
}

?>