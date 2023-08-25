<?php
needRole('admin');
class RestoreControl extends Control
{
	public function restoreForm()
	{
		if ($_REQUEST['dir']) {
			$dir = $_REQUEST['dir'];
			daocall('setting', 'add', array('backup_now_dir', $dir));
			$this->_tpl->assign('cmd', $str);
		}

		$ftp_dir = daocall('setting', 'get', array('ftp_dir'));
		$listdir = apicall('restore', 'listFtpDirFile', array($ftp_dir));
		$this->_tpl->assign('listdir', $listdir);
		return $this->_tpl->fetch('restore/index.html');
	}

	public function restore()
	{
		$dir = $_REQUEST['dir'];
		daocall('setting', 'add', array('backup_now_dir', $dir));
		$str = 'aaaa';
		$this->_tpl->assign('cmd', $str);
		return $this->display('restore/index.html');
	}
}

?>