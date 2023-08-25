<?php
needRole('admin');
class RecordControl extends Control
{
	public function recordDel()
	{
		$arr['id'] = intval($_REQUEST['id']);
		$arr['domain'] = trim($_REQUEST['domain']);
		$arr['name'] = trim($_REQUEST['name']);

		if (!$arr['name']) {
			$arr['name'] = '@';
		}

		if (!apicall('record', 'recordDel', array($arr))) {
			exit('删除失败');
		}

		exit('成功');
	}

	public function domainDig()
	{
		$domain = $_REQUEST['name'] . '.' . $_REQUEST['domain'];
		$json['code'] = 404;

		if (!$domain) {
			exit(json_encode($json));
		}

		$dig = apicall('bind', 'domainDig', array($domain));

		if ($dig === false) {
			$json['code'] = 400;
			exit(json_encode($json));
		}

		$json['code'] = 200;
		$json['dig'] = $dig;
		exit(json_encode($json));
	}

	public function recordPageList()
	{
		$page = intval($_REQUEST['page']);

		if ($page <= 0) {
			$page = 1;
		}

		$page_count = 20;
		$count = 0;
		$where = null;

		if ($_REQUEST['mode']) {
			switch ($_REQUEST['mode']) {
			case 'id':
				$where['id'] = intval($_REQUEST['mode_value']);
				break;

			case 'domain':
				$where['domain'] = trim($_REQUEST['mode_value']);
				break;

			case 'view':
				$where['view'] = trim($_REQUEST['mode_value']);
				break;

			default:
				break;
			}
		}

		$order = $_REQUEST['roder'] ? $_REQUEST['roder'] : null;
		$list = daocall('records', 'recordPageList', array($page, $page_count, &$count, $where, $order));
		$total_page = ceil($count / $page_count);

		if ($total_page <= $page) {
			$page = $total_page;
		}

		if ($order) {
			$this->_tpl->assign('order', $order);
		}

		$this->_tpl->assign('count', $count);
		$this->_tpl->assign('total_page', $total_page);
		$this->_tpl->assign('page', $page);
		$this->_tpl->assign('page_count', $page_count);
		$this->_tpl->assign('list', $list);
		return $this->_tpl->fetch('record/pagelist.html');
	}
}

?>