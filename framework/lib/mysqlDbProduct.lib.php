<?php
class MysqlDbProduct extends DbProduct
{
	public function connect($node)
	{
		$db_host = $node['db_host'];

		if ($db_host == '') {
			$db_host = 'localhost';
		}

		$dsn = 'mysql:host=' . $db_host;

		if ($node['db_port']) {
			$dsn .= ';port=' . $node['db_port'];
		}

		try {
			$this->pdo = ep_new_pdo($dsn, $node['db_user'], $node['db_passwd'], array(PDO::ATTR_TIMEOUT => 10));
		}
		catch (Exception $e) {
			return false;
		}

		return true;
	}

	public function create($vhost)
	{
		$user = $vhost['db_name'];
		$passwd = $vhost['passwd'];
		if ($user == 'mysql' || $user == 'root') {
			return false;
		}

		$sqls = array('CREATE USER ' . $this->quoteValue($user) . '@\'%\' IDENTIFIED BY ' . $this->quoteValue($passwd), 'CREATE DATABASE IF NOT EXISTS ' . $this->quoteIdent($user), 'GRANT ALL PRIVILEGES ON ' . $this->quoteIdent($user) . ' . * TO ' . $this->quoteValue($user) . '@\'%\'');
		return $this->query($sqls);
	}

	public function change_quota($vhost)
	{
		return true;
	}

	public function delTestDatabase()
	{
		$sqls = array(
			'DROP DATABASE IF EXISTS `test`',
			'DELETE FROM `mysql`.`db` WHERE `User`=\'\' AND (`Db`=\'test\' OR `Db`=CONCAT(\'test\', CHAR(92), \'_%\'))',
			'FLUSH PRIVILEGES'
		);
		return $this->query($sqls);
	}

	public function remove($uid)
	{
		$user = $uid;
		if ($user == 'mysql' || $user == 'root') {
			return false;
		}

		$sqls = array('DROP USER ' . $this->quoteIdent($user) . '@\'%\'', 'DROP DATABASE ' . $this->quoteIdent($user));
		return $this->query($sqls);
	}

	public function password($uid, $passwd)
	{
		$user = $uid;
		if ($user == 'mysql' || $user == 'root') {
			return false;
		}

		return $this->query(array('SET PASSWORD FOR ' . $this->quoteValue($user) . '@\'%\' = PASSWORD( ' . $this->quoteValue($passwd) . ' )'));
	}

	public function used($uid, $failedreturnfalse = false)
	{
		$user = $uid;
		if ($user == 'mysql' || $user == 'root') {
			return false;
		}

		$sql = 'SELECT sum(Data_length ) + sum( Index_length ) FROM information_schema.`TABLES` WHERE TABLE_SCHEMA = ' . $this->quoteValue($user);
		$result = $this->pdo->query($sql);

		if (!$result) {
			if ($failedreturnfalse) {
				return false;
			}

			return 0;
		}

		$row = $result->fetch();
		return $row[0] / 1048576;
	}

	private function query(array $sqls)
	{
		$i = 0;

		while ($i < count($sqls)) {
			$result = $this->pdo->exec($sqls[$i]);
			if (!$result && $this->pdo->errorCode() != '00000') {
				return false;
			}

			++$i;
		}

		return true;
	}

	public function dumpOutPassword($user)
	{
		$sql = 'SELECT Password FROM user WHERE User=' . $this->quoteValue($user) . ' LIMIT 1';
		$this->pdo->exec('USE mysql');
		$result = $this->pdo->query($sql);
		if (!$result) {
			return false;
		}

		$ret = $result->fetch(PDO::FETCH_ASSOC);
		return isset($ret['Password']) ? $ret['Password'] : false;
	}

	public function dumpInPassword($user, $passwd)
	{
		if ($user == 'mysql' || $user == 'root') {
			return false;
		}

		return $this->query(array('SET PASSWORD FOR ' . $this->quoteValue($user) . '@\'%\' = ' . $this->quoteValue($passwd)));
	}

	public function getAllUsed()
	{
		$sql = 'SELECT TABLE_SCHEMA as name,sum(Data_length ) + sum( Index_length ) as size FROM information_schema.`TABLES` group by TABLE_SCHEMA';
		$result = $this->pdo->query($sql);

		if (!$result) {
			return false;
		}

		return $result->fetchAll();
	}

	private function quoteValue($value)
	{
		if ($this->pdo) {
			return $this->pdo->quote(ep_str($value));
		}

		return '\'' . str_replace(array('\\', '\''), array('\\\\', '\\\''), ep_str($value)) . '\'';
	}

	private function quoteIdent($name)
	{
		return '`' . str_replace('`', '``', ep_str($name)) . '`';
	}
}
