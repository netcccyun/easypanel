<?php
needRole('admin');
class SlaveControl extends Control
{
	public function checkSlave()
	{
		$arr['slave'] = trim($_REQUEST['slave']);
		$arr['server'] = trim($_REQUEST['server']);
		$slave = daocall('slaves', 'slavesGet', array($arr));
		$json['code'] = 400;

		if (!$slave) {
			$json['msg'] = '该节点不存在';
			exit(json_encode($json));
		}

		$node['host'] = $arr['slave'];
		$node['skey'] = $slave['skey'];
		if (!$node['host'] || !$node['skey']) {
			$json['msg'] = '主节和安全码不能为空';
			exit(json_encode($json));
		}

		if ($check_result = apicall('dnssync', 'test_dns', array($node))) {
			$json['code'] = $check_result->getCode();
			$json['msg'] = (string) $check_result->get('error');
		}

		exit(json_encode($json));
	}

	public function slaveGetAll()
	{
		$slaves = daocall('slaves', 'slavesGet', array());
		$json['count'] = count($slaves);
		$json['slaves'] = $slaves;
		exit(json_encode($json));
	}

	public function slaveAdd()
	{
		$arr['server'] = trim($_REQUEST['server']);
		$arr['slave'] = trim($_REQUEST['slave']);
		$arr['ns'] = trim($_REQUEST['ns']);
		$arr['skey'] = trim($_REQUEST['skey']);
		if ($arr['server'] == '' || $arr['slave'] == '' || $arr['ns'] == '' || $arr['skey'] == '') {
			exit('参数错误');
		}

		if (substr($arr['ns'], 0 - 1) != '.') {
			exit('域名服务器最后需要加上.(点),如ns.kanglesoft.com.');
		}

		if (!daocall('slaves', 'slaveAdd', array($arr))) {
			exit('添加失败');
		}

		exit('成功');
	}

	public function slavePageList()
	{
		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = 20;
		$count = 0;

		if (!$_REQUEST['server']) {
			exit('请先添加主服务器');
		}

		$where_arr['server'] = trim($_REQUEST['server']);
		$list = daocall('slaves', 'slavePageList', array($page, $page_count, &$count, $where_arr));
		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		$this->_tpl->assign('server', $_REQUEST['server']);
		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);
		return $this->_tpl->fetch('slave/pagelist.html');
	}

	public function slaveDel()
	{
		$server = trim($_REQUEST['server']);
		$slave = trim($_REQUEST['slave']);
		if ($server == '' || $slave == '') {
			exit('值不能为空');
		}

		if (!daocall('slaves', 'slaveDel', array($server, $slave))) {
			exit('删除失败');
		}

		exit('成功');
	}

	public function slaveUpdate()
	{
		$arr['server'] = trim($_REQUEST['server']);
		$arr['slave'] = trim($_REQUEST['slave']);
		$arr['ns'] = trim($_REQUEST['ns']);
		$arr['skey'] = trim($_REQUEST['skey']);
		$oldslave = $_REQUEST['oldslave'];

		if (!$oldslave) {
			exit('参数错误');
		}

		if ($arr['server'] == '' || $arr['slave'] == '' || $arr['ns'] == '' || $arr['skey'] == '') {
			exit('参数错误');
		}

		if (substr($arr['ns'], 0 - 1) != '.') {
			exit('域名服务器最后需要加上.(点),如ns.kanglesoft.com.');
		}

		if (apicall('slave', 'slaveUpdate', array($arr['server'], $oldslave, $arr))) {
			exit('成功');
		}

		exit('修改失败');
	}

	/**
	 * ajax
	 * Enter description here ...
	 */
	public function slaveGet()
	{
		$arr['server'] = trim($_REQUEST['server']);

		if (!$arr['server']) {
			exit('请输入服务器名称');
		}

		$slaves = daocall('slaves', 'slavesGet', array($arr));
		$json['count'] = count($slaves);
		exit(json_encode($json));
	}
}

?>