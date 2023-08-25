<?php
needRole('admin');
class ViewsControl extends Control
{
	public function viewsAddFrom()
	{
		return $this->_tpl->fetch('views/from.html');
	}

	public function getViewVersion()
	{
		$json = apicall('dnssync', 'getVersion', array());
		exit(json_encode($json));
	}

	public function viewsSync()
	{
		$step = intval($_REQUEST['step']);
		$bind_dir = apicall('bind', 'getBindDir', array());

		if (!file_exists($bind_dir . 'sbin/named')) {
			exit('请先安装bind');
		}

		$server = daocall('servers', 'serverGet', array());

		if (count($server) <= 0) {
			exit('未添加DNS服务器');
		}

		switch ($step) {
		case 1:
			if (@!apicall('dnssync', 'syncViewAndIp', array())) {
				exit('失败');
			}

			break;

		case 2:
			if (@!apicall('bind', 'bindInit', array(false))) {
				exit('失败');
			}

			break;

		case 3:
			if (@!apicall('dnssync', 'syncAllInit', array())) {
				exit('失败');
			}

			break;

		default:
			break;
		}

		exit('成功');
	}

	public function viewsPageList()
	{
		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = 20;
		$count = 0;
		$list = daocall('views', 'viewsPageList', array($page, $page_count, &$count));
		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);
		return $this->_tpl->fetch('views/pagelist.html');
	}
}

?>