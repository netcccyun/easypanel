<?php
class HttpauthDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('vhost' => 'vhost', 'user' => 'user', 'passwd' => 'passwd');
		$this->MAP_TYPE = array();
		$this->_TABLE = 'httpauth';
	}

	public function add($vhost, $user, $passwd)
	{
		return $this->insert(array('vhost' => $vhost, 'user' => $user, 'passwd' => $this->getPassword($passwd)));
	}

	public function del($vhost, $user)
	{
		$where = $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('user', $user);
		return $this->delData($where);
	}

	public function changePassword($vhost, $user, $passwd)
	{
		$where = $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('user', $user);
		return $this->update(array('passwd' => $this->getPassword($passwd)), $where);
	}

	public function getAll($vhost)
	{
		$where = $this->getFieldValue2('vhost', $vhost);
		return $this->select(null, $where, 'rows');
	}

	private function getPassword($passwd)
	{
		$salt = getRandPasswd(8);
		$src = $passwd . $salt;
		return md5($src) . $salt;
	}
}

?>