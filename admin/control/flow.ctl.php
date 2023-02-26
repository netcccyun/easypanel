<?php
needRole('admin');
load_lib('pub:flow');
class FlowControl extends control
{
	public function flowSort()
	{
		$flowobj = new flow('global.db');

		if (!is_object($flowobj)) {
			trigger_error('没有这个库文件');
			return false;
		}

		$time = date('YmdH', time(NULL));

		switch ($_REQUEST['t']) {
		case 'day':
			$t = substr($time, 0, 8);
			$table = 'flow_day';
			$data = '当天';
			break;

		case 'month':
			$t = substr($time, 0, 6);
			$table = 'flow_month';
			$data = '当月';
			break;

		default:
			$t = substr($time, 0, 8);
			$table = 'flow_day';
			$data = '当天';
			break;
		}

		$page = $_REQUEST['page'] ? $_REQUEST['page'] : 1;
		$count = $_REQUEST['count'] ? $_REQUEST['count'] : 25;
		$flows = $flowobj->getAll($table, $t, $count);
		$this->_tpl->assign('data', $data);
		$this->_tpl->assign('t', $_REQUEST['t']);
		$this->_tpl->assign('flows', $flows);
		$this->_tpl->assign('date', $flows[0]['t']);
		return $this->_tpl->display('flow/sort.html');
	}
}

?>