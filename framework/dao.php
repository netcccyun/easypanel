<?php
class DAO
{
	protected $MAP_ARR = null;
	protected $MAP_TYPE = null;
	protected $_TABLE = null;
	protected $_DBFILE = 'vhs';

	public function __construct()
	{
		load_lib('pub:db');
	}

	public function __destruct()
	{
	}

	public function selectPage($fields, $where, $order_field, $desc, $page, $page_count, &$count)
	{
		if ($where && $where != '') {
			$where = ' WHERE ' . $where;
		}

		$count_sql = 'SELECT COUNT(*) AS count FROM ' . $this->_TABLE . $where;
		$ret = $this->executex($count_sql, 'row');
		$count = $ret['count'];
		$sql = 'SELECT ' . ($fields ? $this->queryFields($fields) : $this->AllQueryFields()) . ' FROM ' . $this->_TABLE . $where;

		if ($order_field) {
			$sql .= ' ORDER BY `' . $this->MAP_ARR[$order_field] . '`';

			if ($desc) {
				$sql .= ' DESC';
			}
		}

		$sql .= ' LIMIT ' . ($page - 1) * $page_count . ',' . $page_count;
		return $this->executex($sql, 'rows');
	}

	protected function connect()
	{
		global $default_db;
		global $dbs;

		if ($dbs[$this->_DBFILE] == null) {
			$dsn = 'sqlite:' . $GLOBALS['safe_dir'] . $this->_DBFILE . '.db';
			$dbs[$this->_DBFILE] = db_connect($dsn);
		}

		$default_db = $dbs['vhs'];
		return $dbs[$this->_DBFILE];
	}

	protected function getPdo()
	{
		return $this->connect();
	}

	public function begin()
	{
		return $this->connect()->beginTransaction();
	}

	public function commit()
	{
		return $this->connect()->commit();
	}

	public function rollback()
	{
		return $this->connect()->rollBack();
	}

	protected function executex($sql, $type = 'result')
	{
		$row = db_query($this->connect(), $sql, $type);
		return $row;
	}

	/**
	 * 执行sql语句
	 * @param String host 	          主机
	 * @param String dbname 	数据库名称
	 * @param String sql		sql语句
	 * @param String type		执行类型。row:单条查询；rows:多条查询；result:执行动作
	 */
	protected function execute($host = 'vip', $dbname = 'kpanel', $sql, $type = 'result')
	{
		$row = db_query($this->connect(), $sql, $type);
		return $row;
	}

	public function getTable()
	{
		return $this->_TABLE;
	}

	public function getCols()
	{
		return $this->MAP_ARR;
	}

	public function delData($where)
	{
		$sql = 'DELETE FROM ' . $this->_TABLE;
		if ($where && $where != '') {
			$sql .= ' WHERE ' . $where;
		}

		return $this->executex($sql);
	}

	public function export()
	{
		$tbl = $this->_TABLE;
		$sql = 'SELECT ';
	}

	public function select($fields, $where = '', $type = 'rows', $order = '')
	{
		$tbl = $this->_TABLE;
		$sql = 'SELECT ';

		if ($fields) {
			$sql .= $this->queryFields($fields);
		}
		else {
			$sql .= $this->AllQueryFields();
		}

		$sql .= ' FROM ' . $this->_TABLE;

		if ($where != '') {
			$sql .= ' WHERE ' . $where;
		}

		if ($order) {
			$sql .= ' ORDER BY ' . $order;
		}

		return $this->executex($sql, $type);
	}

	public function isok()
	{
		$sql = 'PRAGMA integrity_check(1)';
		return $this->executex($sql, 'row');
	}

	/**
	 * @deprecated use select
	 * Enter description here ...
	 * @param unknown_type $where
	 * @param unknown_type $type
	 */
	public function getData($where = '', $type = 'rows')
	{
		$tbl = $this->_TABLE;
		$sql = 'SELECT ' . $this->AllQueryFields() . ' FROM ' . $this->_TABLE;

		if ($where != '') {
			$sql .= ' WHERE ' . $where;
		}

		return $this->executex($sql, $type);
	}

	/**
	 * @deprecated use select
	 * Enter description here ...
	 * @param unknown_type $fields
	 * @param unknown_type $where
	 * @param unknown_type $type
	 */
	public function getData2($fields, $where = '', $type = 'rows')
	{
		$tbl = $this->_TABLE;
		$sql = 'SELECT ';

		if ($fields) {
			$sql .= $this->queryFields($fields);
		}
		else {
			$sql .= $this->AllQueryFields();
		}

		$sql .= ' FROM ' . $this->_TABLE;

		if ($where != '') {
			$sql .= ' WHERE ' . $where;
		}

		return $this->executex($sql, $type);
	}

	public function update($arr, $where)
	{
		$fields_str = '';

		foreach ($arr as $field => $value) {
			if (!array_key_exists($field, $this->MAP_ARR)) {
				continue;
			}

			if ($this->MAP_TYPE != null && $this->MAP_TYPE[$field] & FIELD_TYPE_AUTO) {
				continue;
			}

			if ($fields_str != '') {
				$fields_str .= ',';
			}

			$fields_str .= $this->getFieldValue2($field, $value);
		}

		$sql = 'UPDATE ' . $this->_TABLE . ' SET ' . $fields_str;

		if ($where != '') {
			$sql .= ' WHERE ' . $where;
		}

		return $this->executex($sql);
	}

	public function insert($arr, $cmd = 'INSERT')
	{
		$fields = '';
		$values = '';

		foreach ($arr as $key => $value) {
			if (!array_key_exists($key, $this->MAP_ARR)) {
				continue;
			}

			if ($this->MAP_TYPE != null && $this->MAP_TYPE[$key] & FIELD_TYPE_AUTO) {
				continue;
			}

			if ($fields != '') {
				$fields .= ',';
				$values .= ',';
			}

			$fields .= '`' . $this->MAP_ARR[$key] . '`';
			$values .= $this->getFieldValue($key, $value);
		}

		if (empty($fields) || empty($values)) {
			return false;
		}

		$sql = $cmd . ' INTO ' . $this->_TABLE . (' (' . $fields . ') VALUES (' . $values . ')');
		return $this->executex($sql);
	}

	/**
	 * @deprecated use insert
	 * Enter description here ...
	 * @param unknown_type $arr
	 */
	public function insertData($arr)
	{
		$fields = '';
		$values = '';

		foreach ($arr as $key => $value) {
			if (!array_key_exists($key, $this->MAP_ARR)) {
				continue;
			}

			if ($this->MAP_TYPE != null && $this->MAP_TYPE[$key] & FIELD_TYPE_AUTO) {
				continue;
			}

			if ($fields != '') {
				$fields .= ',';
				$values .= ',';
			}

			$fields .= '`' . $this->MAP_ARR[$key] . '`';
			$values .= $this->getFieldValue($key, $value);
		}

		if (empty($fields) || empty($values)) {
			return false;
		}

		$sql = 'INSERT INTO ' . $this->_TABLE . (' (' . $fields . ') VALUES (' . $values . ')');
		return $this->executex($sql);
	}

	/**
	 * 插入数据库语句组装
	 * @param table 表名
	 * @param Array 插入数据
	 * @param Array 映射数组
	 */
	protected function insertSql($table, &$infoAry, $mapArr)
	{
		return $this->insertData($infoAry);
	}

	protected function AllQueryFields()
	{
		$fieldstr = '';

		foreach ($this->MAP_ARR as $field) {
			if ($fieldstr != '') {
				$fieldstr .= ',';
			}

			$fieldstr .= '`' . $this->MAP_ARR[$field] . '` AS `' . $field . '`';
		}

		return $fieldstr;
	}

	protected function queryFields(&$fields)
	{
		$fieldstr = '';

		foreach ($fields as $field) {
			if ($fieldstr != '') {
				$fieldstr .= ',';
			}

			$fieldstr .= '`' . $this->MAP_ARR[$field] . '` AS `' . $field . '`';
		}

		return $fieldstr;
	}

	protected function getFields($fields, $array)
	{
		$fields_str = '';
		$i = 0;

		while ($i < count($fields)) {
			if ($fields_str != '') {
				$fields_str .= ',';
			}

			$fields_str .= $this->getFieldValue2($fields[$i], $array[$fields[$i]]);
			++$i;
		}

		return $fields_str;
	}

	/**
	 *  DEPRICATED
	 * 更新数据库字段组装
	 * @param Array updateAry	更新数组
	 * @param Array mapArr	映射数组
	 */
	protected function updateFields(&$updateAry, &$mapArr)
	{
		$fields_str = '';

		foreach ($updateAry as $field => $value) {
			if (!array_key_exists($field, $mapArr) || $mapArr[$field][2] === 0) {
				continue;
			}

			$fields_str .= $mapArr[$field] . ' = ' . $this->getFieldValue($field, $value) . ',';
		}

		$fields_str = trim($fields_str, ',');
		return $fields_str;
	}

	protected function daddslashes($string)
	{
		return str_replace('\'', '\'\'', $string);
	}

	protected function getFieldValue2($name, $value)
	{
		return '`' . $this->MAP_ARR[$name] . '`' . '=' . $this->getFieldValue($name, $value);
	}

	protected function getFieldValue($name, $value)
	{
		if ($this->MAP_TYPE == null) {
			return '\'' . $this->daddslashes($value) . '\'';
		}

		switch ($this->MAP_TYPE[$name] & 255) {
		case FIELD_TYPE_INT:
			return intval($value);
		case FIELD_TYPE_MD5:
			return '\'' . md5($value) . '\'';
		case FIELD_TYPE_DATETIME:
			return $value;
		}

		return '\'' . $this->daddslashes($value) . '\'';
	}

	protected function now()
	{
		if (defined(DAO_MYSQL_DRIVER)) {
			return 'NOW()';
		}

		if (defined(DAO_SQLITE_DRIVER)) {
			return 'datetime(\'now\')';
		}

		return time();
	}
}

function select_db($dbname)
{
	$GLOBALS['db_cfg']['default'] = array('dsn' => 'sqlite:' . $GLOBALS['safe_dir'] . $dbname);
}

function close_all_db()
{
	global $default_db;
	global $dbs;
	$default_db = null;
	$dbs = null;
}

define('FIELD_TYPE_STRING', 0);
define('FIELD_TYPE_INT', 1);
define('FIELD_TYPE_MD5', 2);
define('FIELD_TYPE_DATETIME', 4);
define('FIELD_TYPE_AUTO', 1 << 28);

?>