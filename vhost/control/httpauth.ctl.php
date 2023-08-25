<?php
needRole('vhost');
class HttpauthControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function index()
	{
		$httpusers = daocall('httpauth', 'getAll', array(getRole('vhost')));
		$this->assign('httpusers', $httpusers);
		return $this->_tpl->fetch('httpauth/index.html');
	}

	public function add()
	{
		apicall('httpauth', 'add', array(getRole('vhost'), filterParam($_REQUEST['httpuser']), filterParam($_REQUEST['passwd'])));
		return $this->index();
	}

	public function del()
	{
		apicall('httpauth', 'del', array(getRole('vhost'), filterParam($_REQUEST['httpuser'])));
		return $this->index();
	}

	public function changePassword()
	{
		apicall('httpauth', 'changePassword', array(getRole('vhost'), filterParam($_REQUEST['httpuser']), filterParam($_REQUEST['passwd'])));
		return $this->index();
	}

	public function sync()
	{
		apicall('httpauth', 'sync', array(getRole('vhost')));
		return $this->index();
	}
}

?>