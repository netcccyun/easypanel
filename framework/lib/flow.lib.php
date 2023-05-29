<?php
class Flow
{
	private $pdo;

	public function __construct($db)
	{
		$file = $GLOBALS['safe_dir'] . $db;
		$exsit = file_exists($file);
		$dsn = 'sqlite:' . $file;
		$this->pdo = new PDO($dsn);

		if (!$exsit) {
			$this->init();
			return NULL;
		}

		$this->upgrade();
	}

	/**
	 * 流量排名
	 * @param  $table
	 * @param  $t
	 * @param $count 查询条数
	 */
	public function getAll($table, $t, $count)
	{
		$sql = 'SELECT flow/1024 as flow,flow_cache/1024 as flow_cache ,name as name , t as t from ';

		switch ($table) {
		case 'flow_day':
			$sql .= 'flow_day where t';
			break;

		case 'flow_month':
			$sql .= 'flow_month where t';
			break;

		case 'flow_hour':
			$sql .= 'flow_hour where t';
			break;

		default:
			break;
		}

		if (!($t = intval($t))) {
			trigger_error('时间格式不正确');
			return false;
		}

		$sql .= ' = ' . $t . ' order by flow desc limit 0';
		$sql .= ' ,' . $count;

		if (!is_object($this->pdo)) {
			return false;
		}

		if ($result = $this->pdo->query($sql)) {
			return $result->fetchAll(PDO::FETCH_ASSOC);
		}

		return false;
	}

	/**
	 * 取得流量,流量图
	 * @param  $table (flow_hour,flow_day,flow_month)
	 * @param  $name
	 * @param  $t
	 */
	public function getFlow($table, $name, $t)
	{
		$sql = 'SELECT flow as flow,flow_cache as flow_cache , t as t  from ' . $table . ' WHERE name=\'' . $name . '\'';

		switch ($table) {
		case 'flow_day':
			$sql .= ' AND t>=\'' . $t . '\' order by t desc  limit 31';
			break;

		case 'flow_month':
			$sql .= ' order by t  desc limit 12';
			break;

		case 'flow_hour':
			$sql .= 'and t>=\'' . $t . '\' order by t  desc limit 24';
			break;

		default:
			break;
		}

		if (is_object($this->pdo)) {
			$result = $this->pdo->query($sql);
		}

		if ($result) {
			return $result->fetchAll(PDO::FETCH_ASSOC);
		}
	}

	/**
	 * 得到一个虚拟主机的月流量,返回M
	 * @param $name
	 * @param $t
	 * @return mixed|boolean
	 */
	public function getMonthFlow($name, $t)
	{
		$sql = 'select flow/1024 as flow ,flow_cache/1024 as flow_cache,name as name from flow_month where name=\'' . $name . '\' AND t=' . $t;

		if (is_object($this->pdo)) {
			$result = $this->pdo->query($sql);
		}

		if ($result) {
			return $result->fetch(PDO::FETCH_ASSOC);
		}

		return false;
	}

	/**
	 * 取得所有时间为本月的流量
	 * Enter description here ...
	 * @param unknown_type $table
	 * @param unknown_type $t
	 */
	public function getListflow($table, $t)
	{
		$sql = 'select flow/1024 as flow ,flow_cache/1024 as flow_cache,name as name from ' . $table . ' where t=' . $t;

		if (is_object($this->pdo)) {
			$result = $this->pdo->query($sql);
		}

		if ($result) {
			return $result->fetchAll(PDO::FETCH_ASSOC);
		}

		return false;
	}

	/**
	 * 增加流量
	 * @param $table (flow_hour,flow_day,flow_month)
	 * @param $name
	 * @param $t
	 * @param $flow
	 */
	public function addFlow($table, $name, $t, $flow, $flow_cache = 0)
	{
		$sql = 'UPDATE ' . $table . ' SET flow=flow+' . $flow . ',flow_cache=flow_cache+' . $flow_cache . ' WHERE name=\'' . $name . '\' AND t=\'' . $t . '\'';
		$result = $this->pdo->exec($sql);

		if (!$result) {
			$sql = 'INSERT INTO ' . $table . ' (name,t,flow,flow_cache) VALUES (\'' . $name . '\',\'' . $t . '\',' . $flow . ',' . $flow_cache . ')';
			return $this->pdo->exec($sql);
		}

		return $result;
	}

	private function init()
	{
		$init_sql = array("CREATE TABLE [flow_hour] (\r\n\t\t\t\t[name] VARCHAR(255)  NULL,\r\n\t\t\t\t[t] VARCHAR(10)  NULL,\r\n\t\t\t\t[flow] INTEGER  NULL,\r\n\t\t\t\t[flow_cache] INTEGER  NULL,\r\n\t\t\t\tPRIMARY KEY ([name],[t])\r\n\t\t);", "CREATE TABLE [flow_day] (\r\n\t\t\t\t[name] VARCHAR(255)  NULL,\r\n\t\t\t\t[t] VARCHAR(8)  NULL,\r\n\t\t\t\t[flow] INTEGER  NULL,\r\n\t\t\t\t[flow_cache] INTEGER  NULL,\r\n\t\t\t\tPRIMARY KEY ([name],[t])\r\n\t\t);", "CREATE TABLE [flow_month] (\r\n\t\t\t\t[name] VARCHAR(255)  NULL,\r\n\t\t\t\t[t] VARCHAR(6)  NULL,\r\n\t\t\t\t[flow] INTEGER  NULL,\r\n\t\t\t\t[flow_cache] INTEGER  NULL,\r\n\t\t\t\tPRIMARY KEY ([name],[t])\r\n\t\t);");

		foreach ($init_sql as $sql) {
			$this->pdo->exec($sql);
		}

		return true;
	}

	private function upgrade()
	{
		$upgrade_sql = array('BEGIN', 'ALTER TABLE flow_hour add column flow_cache INTEGER NULL DEFAULT 0;', 'ALTER TABLE flow_day add column flow_cache INTEGER NULL DEFAULT 0;', 'ALTER TABLE flow_month add column flow_cache INTEGER NULL DEFAULT 0;', 'COMMIT');

		foreach ($upgrade_sql as $sql) {
			$this->pdo->exec($sql);
		}

		return true;
	}

	/**
	 * 清除60天以前的数据
	 * @return boolean
	 */
	public function clean()
	{
		$t = date('YmdH', time() - 60 * 86400);
		$month = substr($t, 0, 6);
		$day = substr($t, 0, 8);
		$hour = $t;
		$clean_sql = array('BEGIN', 'DELETE FROM flow_hour WHERE t<\'' . $hour . '\'', 'DELETE FROM flow_day  WHERE t<\'' . $day . '\'', 'DELETE FROM flow_month WHERE t<\'' . $month . '\'', 'COMMIT');

		foreach ($clean_sql as $sql) {
			$this->pdo->exec($sql);
		}

		return true;
	}

	public function getPdo()
	{
		return $this->pdo;
	}
}


?>