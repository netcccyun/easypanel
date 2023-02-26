<?php
abstract class DbProduct
{
	protected $pdo;

	static public function getUser($name)
	{
		$vhost_info = daocall('vhost', 'getVhost', array($name));

		if ($vhost_info['db_name'] != '') {
			return $vhost_info['db_name'];
		}

		return $name;
	}

	abstract public function connect($node);

	public function add($uid, $passwd)
	{
		$vhost['db_name'] = $uid;
		$vhost['passwd'] = $passwd;
		return $this->create($vhost);
	}

	abstract public function create($vhost);

	abstract public function change_quota($vhost);

	abstract public function remove($uid);

	abstract public function password($uid, $passwd);

	abstract public function dumpOutPassword($user);

	abstract public function dumpInPassword($user, $passwd);

	abstract public function used($uid);
}


?>