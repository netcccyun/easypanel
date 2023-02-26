<?php
needRole('vhost');
define('UPLOAD_TABLE_NAME', '!anti_up_inject');
define('BEGIN', 'BEGIN');
define('ACTION', 'table:!anti_up_inject');
class AntiuploadControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function antiuploadFrom()
	{
		$check_result = apicall('access', 'checkAccess', array('free', '2.9.6'));

		if ($check_result !== true) {
			return $this->show_msg($check_result);
		}

		$tables = $this->access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == UPLOAD_TABLE_NAME) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$this->access->addTable(UPLOAD_TABLE_NAME)) {
				return $this->show_msg('不能增加表');
			}
		}

		$result = $this->access->listChain(UPLOAD_TABLE_NAME);
		$id = 0;

		foreach ($result->children() as $chain) {
			foreach ($chain->children() as $ch) {
				if ($ch['filename'] == '') {
					continue;
				}

				$filenames[] = array('filename' => $ch['filename'], 'id' => $id);
				++$id;
			}
		}

		$find_result = $this->access->findChain(BEGIN, UPLOAD_TABLE_NAME);

		if ($find_result) {
			$this->_tpl->assign('autiupload_start', 1);
		}

		$this->_tpl->assign('filenames', $filenames);
		return $this->_tpl->fetch('antiupload/antiuploadfrom.html');
	}

	public function antiuploadAdd()
	{
		$filename_str = trim($_REQUEST['filename']);

		if ($filename_str == '') {
			return $this->show_msg('文件名不能为空');
		}

		if (!$this->check_filename($filename_str)) {
			return $this->show_msg('文件名写入有误，请重新输入');
		}

		$filename = explode(',', $filename_str);
		$f = '\\.';

		foreach ($filename as $file) {
			if ($file == '') {
				continue;
			}

			$f .= '(' . $file . ')|';
		}

		$f = trim($f, '|');
		$f .= '$';
		$arr['action'] = 'deny';
		$models['mark_post_file'] = array('filename' => $f);

		if ($this->access->addChain(UPLOAD_TABLE_NAME, $arr, $models)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('增加失败');
	}

	public function antiuploadDel()
	{
		$id = intval($_REQUEST['id']);

		if ($this->access->delChain(UPLOAD_TABLE_NAME, $id)) {
			exit('成功');
		}

		exit('删除失败');
	}

	public function antiuploadEmpty()
	{
		$find_result = $this->access->findChain(BEGIN, UPLOAD_TABLE_NAME);

		if ($find_result) {
			if (!$this->access->delChainByName(BEGIN, UPLOAD_TABLE_NAME)) {
				exit('清除设置失败');
			}
		}

		if (!$this->access->emptyTable(UPLOAD_TABLE_NAME)) {
			exit('清除设置失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function antiuploadStart()
	{
		$find_result = $this->access->findChain(BEGIN, UPLOAD_TABLE_NAME);
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case 0:
			if ($find_result == false) {
				$this->access->addChain(BEGIN, array('action' => ACTION, 'name' => UPLOAD_TABLE_NAME));
				break;
			}

		case 1:
			if ($find_result != null) {
				$this->access->delChainByName(BEGIN, UPLOAD_TABLE_NAME);
				break;
			}

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	private function check_filename($filename_str)
	{
		$check_templete = '/^[a-z0-9][,a-z0-9_]+$/';
		return preg_match($check_templete, $filename_str);
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>