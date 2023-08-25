<?php
class UserAPI extends API
{
	/**
	 * 构造函数
	 **/
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * 析构函数 **/
	public function __destruct()
	{
		parent::__destruct();
	}

	public function& getUser($uid = 0)
	{
	}

	public function insertUser($arr = array())
	{
		$ret = daocall('user', 'insertUser', array($arr));
		return $ret;
	}
}

?>