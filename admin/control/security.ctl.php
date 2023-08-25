<?php
needRole('admin');
class SecurityControl extends Control
{
	/**
	 *2013-5-21
	 */
	public function addFrom()
	{
		if ($_REQUEST['success'] == 1) {
			$this->_tpl->assign('msg', '更新设置成功');
		}

		if (!function_exists('imageCreate')) {
			$this->_tpl->assign('msg', 'php的gd扩展未打开,不能使用图片码验证功能');
		}

		$setting = daocall('setting', 'getAll', array());
		$this->_tpl->assign('setting', $setting);
		return $this->_tpl->fetch('security/add.html');
	}

	/**
	 *2013-5-21
	 */
	public function add()
	{
		daocall('setting', 'add', array('allow_login_ip', trim($_REQUEST['allow_login_ip'])));
		daocall('setting', 'add', array('allow_login_time', trim($_REQUEST['allow_login_time'])));
		daocall('setting', 'add', array('admin_login_img', trim($_REQUEST['admin_login_img'])));
		daocall('setting', 'add', array('admin_login_img_sum', trim($_REQUEST['admin_login_img_sum'])));
		daocall('setting', 'add', array('vhost_login_img', trim($_REQUEST['vhost_login_img'])));
		daocall('setting', 'add', array('vhost_login_img_sum', trim($_REQUEST['vhost_login_img_sum'])));
		header('Location:?c=security&a=addFrom&success=1');
		exit();
	}
}

?>