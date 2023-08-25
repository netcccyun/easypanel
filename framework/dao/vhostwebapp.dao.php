<?php
class VhostwebappDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('id' => 'id', 'user' => 'user', 'appid' => 'appid', 'domain' => 'domain', 'dir' => 'dir', 'phy_dir' => 'phy_dir', 'status' => 'status', 'install_time' => 'install_time', 'appname' => 'appname', 'appver' => 'appver');
		$this->MAP_TYPE = array('id' => FIELD_TYPE_AUTO, 'step' => FIELD_TYPE_INT, 'install_time' => FIELD_TYPE_DATETIME);
		$this->_TABLE = DBPRE . 'vhost_webapp';
	}

	public function add($user, $appid, $appname, $appver, $domain, $dir, $phy_dir)
	{
		$arr['user'] = $user;
		$arr['appid'] = $appid;
		$arr['domain'] = $domain;
		$arr['dir'] = $dir;
		$arr['phy_dir'] = $phy_dir;
		$arr['install_time'] = $this->now();
		$arr['appname'] = $appname;
		$arr['appver'] = $appver;
		$arr['status'] = 0;
		$result = $this->insertData($arr);

		if ($result) {
			try {
				$id = $this->getPdo()->lastInsertId();
			}
			catch (PDOException $e) {
			}

			return $id;
		}

		return false;
	}

	public function getAll($user)
	{
		return $this->select(null, $this->getFieldValue2('user', $user), 'rows');
	}

	public function getapp($id, $user)
	{
		return $this->select(null, $this->getFieldValue2('id', $id) . ' AND ' . $this->getFieldValue2('user', $user), 'row');
	}

	public function remove($id, $user)
	{
		return $this->delData($this->getFieldValue2('id', $id) . ' AND ' . $this->getFieldValue2('user', $user));
	}

	public function updateApp($id, $user)
	{
		return $this->update(array('status' => 1), $this->getFieldValue2('id', $id) . ' AND ' . $this->getFieldValue2('user', $user));
	}
}

?>