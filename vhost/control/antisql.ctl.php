<?php
needRole('vhost');
define('SQL_TABLE_NAME', '!anti_sql');
define('BEGIN', 'BEGIN');
define('ACTION', 'table:!anti_sql');
class AntisqlControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function antisqlFrom()
	{
		$this->antisqlAddTable();
		$result = $this->access->listChain(SQL_TABLE_NAME);

		if ($result) {
			$id = 0;

			foreach ($result->children() as $chain) {
				foreach ($chain->children() as $ch) {
					$sql[$id] = array('value' => (string) $ch['value'], 'charset' => (string) $ch['charset'], 'get' => (string) $ch['get'], 'post' => (string) $ch['post'], 'id' => $id);
				}

				++$id;
			}

			$this->_tpl->assign('sql', $sql);
		}

		if ($this->access->findChain(BEGIN, SQL_TABLE_NAME)) {
			$this->_tpl->assign('at', 1);
		}

		return $this->_tpl->fetch('antisql/antisqlfrom.html');
	}

	public function antisqlAdd()
	{
		$check_result = apicall('access', 'checkAccess', array('free', '2.9.6'));

		if ($check_result !== true) {
			$this->_tpl->assign('msg', $check_result);
			return $this->_tpl->fetch('msg.html');
		}

		$param_value = trim($_REQUEST['param_value']);
		$charset = $_REQUEST['charset'] ? trim($_REQUEST['charset']) : 'utf-8';
		$param_get = 1;
		$param_post = 1;
		$arr['action'] = 'deny';
		$models['mark_param'] = array('value' => $param_value, 'charset' => $charset, 'get' => $param_get, 'post' => $param_post);

		if ($this->access->addChain(SQL_TABLE_NAME, $arr, $models)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('添加失败');
	}

	private function antisqlAddTable()
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == SQL_TABLE_NAME) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$this->access->addTable(SQL_TABLE_NAME)) {
				exit('不能增加表');
			}
		}
	}

	/**
	 * 清除设置，包括begin和sql表
	 * ajax
	 */
	public function antisqlDel()
	{
		$id = intval($_REQUEST['id']);

		if ($this->access->delChain(SQL_TABLE_NAME, $id)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('删除失败');
	}

	/**
	 * ajax
	 *
	 */
	public function antisqlCheckOn()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case '1':
			$arr = array('action' => ACTION, 'name' => SQL_TABLE_NAME);
			$this->access->addChain(BEGIN, $arr);
			break;

		case '2':
			$this->access->delChainByName(BEGIN, SQL_TABLE_NAME);
			break;

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>