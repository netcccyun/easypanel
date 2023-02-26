<?php
class SqlsrvDbProduct extends DbProduct
{
	public function connect($node)
	{
		$server = '(local)';

		if ($node['sqlsrv_port']) {
			$server .= ', ' . $node['sqlsrv_port'];
		}

		$dsn = 'sqlsrv:server=' . $server;

		try {
			$this->pdo = new PDO($dsn, $node['sqlsrv_user'], $node['sqlsrv_passwd']);
		}
		catch (Exception $e) {
			return false;
		}

		return true;
	}

	public function change_quota($vhost)
	{
		$user = $vhost['db_name'];

		if (!$this->check($user)) {
			return false;
		}

		$db_path = str_replace('/', '\\', $vhost['doc_root']) . '\\database\\';
		$sql[] = 'ALTER DATABASE ' . $user . ' MODIFY FILE (NAME=' . $user . ',FILENAME=\'' . $db_path . $user . '.ss\',MAXSIZE=' . $vhost['db_quota'] . 'MB,SIZE=5MB)';
		return $this->query($sql);
	}

	public function create($vhost)
	{
		$user = $vhost['db_name'];

		if (!$this->check($user)) {
			return false;
		}

		$db_path = str_replace('/', '\\', $vhost['doc_root']) . '\\database\\';
		$sql[] = 'CREATE DATABASE ' . $user . ' ON PRIMARY (NAME=' . $user . ',FILENAME=\'' . $db_path . $user . '.ss\',MAXSIZE=' . $vhost['db_quota'] . 'MB,SIZE=5MB)';
		$sql[] = 'USE ' . $user;
		$sql[] = 'CREATE LOGIN ' . $user . ' WITH PASSWORD = \'' . $vhost['passwd'] . '\' , DEFAULT_DATABASE=' . $user;
		$sql[] = 'EXEC sp_grantdbaccess \'' . $user . '\'';
		$sql[] = 'EXEC sp_addrolemember \'db_owner\', \'' . $user . '\'';
		return $this->query($sql);
	}

	public function remove($uid)
	{
		$user = $uid;

		if (!$this->check($uid)) {
			return false;
		}

		$sql[] = 'USE ' . $user;
		$sql[] = 'ALTER AUTHORIZATION ON SCHEMA::'.$user.' TO dbo';
		$sql[] = 'DROP USER ' . $user;
		$sql[] = 'DROP LOGIN ' . $user;
		$sql[] = 'USE master';
		$sql[] = 'ALTER DATABASE '.$user.' SET SINGLE_USER WITH ROLLBACK IMMEDIATE';
		$sql[] = 'DROP DATABASE ' . $user;
		$result = $this->query($sql);
		return $result;
	}

	public function password($uid, $passwd)
	{
		$user = $uid;

		if (!$this->check($uid)) {
			return false;
		}

		$sql[] = 'ALTER LOGIN ' . $user . ' WITH PASSWORD = \'' . $passwd . '\'';
		return $this->query($sql);
	}

	public function used($uid)
	{
		$user = $uid;

		if (!$this->check($uid)) {
			return false;
		}

		$sql = 'sp_helpdb \'' . $user . '\'';
		$result = $this->pdo->query($sql);

		if (!$result) {
			return 0;
		}

		$row = $result->fetch();
		return floatval($row['db_size']);
	}

	private function check($uid)
	{
		if (strncasecmp($uid, 'sq_', 3) != 0) {
			return false;
		}

		return true;
	}

	public function query(array $sqls)
	{
		foreach ($sqls as $sql) {
			$result = $this->pdo->exec($sql);
			if (!$result && $this->pdo->errorCode() != '00000') {
				//print_r($this->pdo->errorInfo());
			}
		}

		return true;
	}

	public function dumpOutPassword($user)
	{
	}

	public function dumpInPassword($user, $passwd)
	{
	}
}

?>