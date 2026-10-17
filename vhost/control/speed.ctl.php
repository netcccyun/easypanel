<?php
needRole('vhost');
define('BEGIN', 'BEGIN');
define('TABLENAME', '!speed_limit');
define('ACTION', 'table:!speed_limit');
class SpeedControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function speedFrom()
	{
		$this->speedAddTable();
		$result = $this->access->listChain(TABLENAME);

		if ($result) {
			$id = 0;
			$sp = array();

			foreach ($result->children() as $chain) {
				foreach ($chain->children() as $ch) {
					if ($ch->getName() == 'acl_per_ip') {
						$sp[$id]['mode'] = 'c';
						$sp[$id]['max'] = (int) $ch['max'];
					}
					if ($ch->getName() == 'mark_speed_limit') {
						$sp[$id]['mode'] = 's';
					}

					if ($ch->getName() == 'mark_ip_speed_limit') {
						$sp[$id]['mode'] = 'i';
					}

					if ($ch->getName() == 'mark_gspeed_limit') {
						$sp[$id]['mode'] = 'g';
					}

					if ($ch['path']) {
						$sp[$id]['path'] = (string) $ch['path'];
					}

					if ((string) $ch['limit']) {
						$sp[$id]['limit'] = apicall('utils', 'get_size', array((string) $ch['limit']));
					}

					if ((string) $ch['speed_limit']) {
						$sp[$id]['limit'] = apicall('utils', 'get_size', array((string) $ch['speed_limit']));
					}

					if ((string) $ch['min_size']) {
						$sp[$id]['min_size'] = apicall('utils', 'get_size', array((string) $ch['min_size']));
					}

					$sp[$id]['id'] = $id;
				}

				++$id;
			}

			$this->_tpl->assign('sp', $sp);
		}

		if ($this->access->findChain(BEGIN, TABLENAME)) {
			$this->_tpl->assign('at', 1);
		}

		return $this->_tpl->fetch('speed/speedfrom.html');
	}

	public function speedAdd()
	{
		$path = filterParam(isset($_REQUEST['path']) ? $_REQUEST['path'] : '', 'dir');
		$mode = trim(ep_request('mode'));
		$models = array();
		$arr = array('action' => 'continue');
		$limit = $mode === 'per_ip' ? 0 : intval(isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 0);

		if ($limit < 0) {
			exit('参数错误');
		}

		$limit *= 1024;

		switch ($mode) {
		case 'per_ip':
			$max = ep_request('max');
			if (!is_string($max) || !preg_match('/\A[0-9]+\z/D', $max)
				|| (float) $max < 1 || (float) $max > 2147483647) {
				exit('单IP并发数必须是1到2147483647之间的整数');
			}
			// Check the directory before per_ip, whose match updates connection counts.
			if ($path !== '') {
				$models['acl_path'] = array('path' => $path);
			}
			$models['acl_per_ip'] = array('max' => (int) $max);
			$arr['action'] = 'deny';
			break;

		case 'speed_limit':
			if (!empty($_REQUEST['min_size'])) {
				$min_size = intval($_REQUEST['min_size']);
			}
			else {
				$min_size = 0;
			}

			$min_size *= 1024;
			$models = array();
			$models['mark_speed_limit'] = array('min_size' => $min_size, 'limit' => $limit);
			break;

		case 'ip_speed_limit':
			$models['mark_ip_speed_limit'] = array('speed_limit' => $limit);
			break;

		case 'gspeed_limit':
			if ($path == '') {
				exit('目录不能为空');
			}

			$models['mark_gspeed_limit'] = array('limit' => $limit);
			break;

		default:
			exit('error');
		}

		if ($path != '' && $mode !== 'per_ip') {
			$models['acl_path'] = array('path' => $path);
		}

		if ($this->access->addChain(TABLENAME, $arr, $models)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('添加失败');
	}

	public function speedDel()
	{
		$id = intval($_REQUEST['id']);

		if ($this->access->delChain(TABLENAME, $id)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('删除失败');
	}

	public function speedCheckOn()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case '1':
			if (!$this->access->findChain(BEGIN, TABLENAME)) {
				$arr = array('action' => ACTION, 'name' => TABLENAME);
				if (!$this->access->addChain(BEGIN, $arr)) {
					exit('开启失败');
				}
			}
			break;

		case '2':
			while ($this->access->findChain(BEGIN, TABLENAME)) {
				if (!$this->access->delChainByName(BEGIN, TABLENAME)) {
					exit('关闭失败');
				}
			}
			break;

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	private function speedAddTable()
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach (ep_iter($tables) as $table) {
			if ($table == TABLENAME) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$this->access->addTable(TABLENAME)) {
				exit('不能增加表');
			}
		}
	}
}
