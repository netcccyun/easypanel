<?php
needRole('vhost');
class FlowControl extends Control
{
	/**
	 * 数据库提取流量，
	 * t (day,month,year)
	 */
	public function viewFlow()
	{
		$name = getRole('vhost');

		switch ($_REQUEST['t']) {
		case 'day':
			$t = date('YmdH', time(NULL) - 86400);
			$table = 'flow_hour';
			$date = '小时';
			$datename = '日';
			break;

		case 'month':
			$t = date('Ymd', time(NULL) - 31 * 86400);
			$table = 'flow_day';
			$date = '日';
			$datename = '月';
			break;

		case 'year':
			$t = date('Ym');
			$table = 'flow_month';
			$date = '月';
			$datename = '年';
			break;

		default:
			echo 'No this time for flow';
			break;
		}

		$flow = 'name: \'总流量\', data: [';
		$flow_cache = 'name: \'缓存流量\', data: [';
		$cate = '';
		$flows = apicall('flow', 'getFlow', array($table, $name, $t));

		if (is_array($flows)) {
			$flows = array_reverse($flows);

			foreach ($flows as $f) {
				$flow .= $f['flow'] . ',';
				$flow_cache .= $f['flow_cache'] . ',';
				$cate .= '\'' . intval(substr($f['t'], 0 - 2));

				switch ($_REQUEST['t']) {
				case 'day':
					$cate .= ':00';
					break;

				case 'month':
					$cate .= '日';
					break;

				case 'year':
					$cate .= '年';
					break;

				default:
					break;
				}

				$cate .= '\',';
			}
		}

		$cate = trim($cate, ',');
		$flow .= ']';
		$flow_cache .= ']';
		$this->_tpl->assign('datename', $datename);
		$this->_tpl->assign('date', $date);
		$this->_tpl->assign('flow', $flow);
		$this->_tpl->assign('flow_cache', $flow_cache);
		$this->_tpl->assign('cate', $cate);
		return $this->_tpl->fetch('flow/index.html');
	}
}

?>