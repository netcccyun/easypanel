<?php
class VhostinfoDAO extends DAO
{
	/**
	 * type格式:
	 * 	0,域名,
	 * 	1,自定义错误页面，
	 * 	2，默认首页文件，
	 * 	3，扩展映射，
	 * 	4，别名，为别名时 value有三个值,以,号分开，后面两个默认,0,0即可,例/home/ftp,0,0
	 * 	5，mime_type,
	 * 	7,独立IP端口，
	 * 	100，kangle变量
	 * 	101,easypanel变量
	 * name格式:
	 * 	type=3:
	 * 	例:1,php(1是表示按文件扩展名,后面的PHP表示值)
	 * value格式:
	 * 	type=3:
	 * 	例:1,cmd:php5217,* 解释:1是确认文件存在，*表示允许方法
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('id' => 'id', 'vhost' => 'vhost', 'type' => 'type', 'name' => 'name', 'value' => 'value');
		$this->MAP_TYPE = array('id' => FIELD_TYPE_INT, 'type' => FIELD_TYPE_INT);
		$this->_TABLE = DBPRE . 'vhost_info';
	}

	public function delNodeCdn($prefix)
	{
		$len = strlen($prefix)+1;
		$sql = 'delete from ' . $this->_TABLE . ' where substr(`vhost`,0,' . $len . ')=\'' . $prefix . '\'';
		return $this->executex($sql);
	}

	public function getAll($vhost = null)
	{
		$where = '';

		if ($vhost != null) {
			$where = $this->getFieldValue2('vhost', $vhost);
		}

		return $this->select(null, $where);
	}

	public function selectExpireDomain($table, $field)
	{
		$sql = 'SELECT DISTINCT ' . $this->_TABLE . '.' . 'vhost as vhost from ' . $this->_TABLE . ' left join ' . $table . ' on ';
		$sql .= $this->_TABLE . '.vhost=' . $table . '.' . $field . ' where ' . $table . '.' . $field . ' is null';
		$ret = $this->executex($sql, 'rows');

		if (!$ret) {
			return false;
		}

		return $ret;
	}

	public function getDomainCount($vhost)
	{
		$sql = 'SELECT COUNT(name) as count FROM ' . $this->_TABLE . ' WHERE ' . $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('type', 0);
		$ret = $this->executex($sql, 'row');

		if (!$ret) {
			return false;
		}

		return $ret['count'];
	}

	public function checkDomainSubdir($vhost, $subdir, $max_subdir)
	{
		$max_subdir = intval($max_subdir);

		if ($max_subdir <= 0) {
			return true;
		}

		$ret = $this->select(array('value'), $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('value', $subdir) . ' AND ' . $this->getFieldValue2('type', 0), 'row');

		if ($ret) {
			return true;
		}

		$sql = 'SELECT COUNT(DISTINCT value) as count FROM ' . $this->_TABLE . ' WHERE ' . $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('type', 0);
		$ret = $this->executex($sql, 'row');

		if (!$ret) {
			return true;
		}

		if ($max_subdir <= $ret['count']) {
			return false;
		}

		return true;
	}

	public function findDomain($domain, $vhost = null)
	{
		$where = $this->getFieldValue2('name', $domain) . ' AND ' . $this->getFieldValue2('type', 0);

		if ($vhost != null) {
			$where .= ' AND ' . $this->getFieldValue2('vhost', $vhost);
		}

		return $this->getData2(array('vhost'), $where, 'row');
	}

	public function delAllInfo($vhost)
	{
		return $this->delData($this->getFieldValue2('vhost', $vhost));
	}

	public function addBind($vhost, $ip, $port)
	{
		$ip = trim($ip);
		$port = trim($port);
		$this->delData($this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('type', 7));
		$binds = array();
		if ($port != '' || $ip != '') {
			if ($port != '') {
				$ports = explode(',', $port);
			}

			if (empty($ports) || count($ports) == 0) {
				$binds[] = '!' . $ip . ':80';
			}
			else {
				if ($ip == '') {
					$ip = '*';
				}

				foreach ($ports as $p) {
					$binds[] = '!' . $ip . ':' . trim($p);
				}
			}

			foreach ($binds as $bind) {
				$this->addInfo($vhost, $bind, 7, '');
			}
		}
	}

	public function delInfo($vhost, $name, $type, $value)
	{
		$where = $this->getFieldValue2('vhost', $vhost);
		$where .= ' AND ' . $this->getFieldValue2('type', $type);
		$where .= ' AND ' . $this->getFieldValue2('name', $name);

		if ($value != null) {
			$where .= ' AND ' . $this->getFieldValue2('value', $value);
		}

		return $this->delData($where);
	}

	public function addInfo($vhost, $name, $type, $value, $multi = true, $id = 1000)
	{
		if (!$multi) {
			$this->delInfo($vhost, $name, $type, null);
		}

		return $this->insert(array('vhost' => $vhost, 'name' => $name, 'type' => $type, 'value' => $value, 'id' => $id));
	}

	public function getInfo($vhost, $type = null, $name = null)
	{
		$where = $this->getFieldValue2('vhost', $vhost);

		if ($name) {
			$where .= ' AND ' . $this->getFieldValue2('name', $name);
		}

		if ($type) {
			$where .= ' AND ' . $this->getFieldValue2('type', $type);
		}

		return $this->getData2(array('name', 'type', 'value'), $where);
	}

	public function getMap($vhost, $value)
	{
		return $this->select('', $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('type', 3) . ' AND ' . $this->getFieldValue2('value', $value), 'rows', 'id');
	}

	public function updateInfo($vhost, $name, $arr, $type = 0)
	{
		$where = $this->getFieldValue2('vhost', $vhost);
		$where .= ' and ' . $this->getFieldValue2('type', $type);
		$where .= ' and ' . $this->getFieldValue2('name', $name);
		return $this->update($arr, $where);
	}

	public function getDomain($vhost)
	{
		$where = $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('type', 0);
		return $this->getData2(array('name', 'value'), $where);
	}

	public function delDomain($vhost, $domain)
	{
		$where = $this->getFieldValue2('vhost', $vhost) . ' AND ' . $this->getFieldValue2('type', 0);
		$where .= ' AND ';
		$where .= $this->getFieldValue2('name', $domain);
		return $this->delData($where);
	}

	/**
	 * addtime 2013,4,9
	 * @param unknown_type $arr
	 * @return Ambigous <boolean, PDOStatement>
	 */
	public function add($arr)
	{
		return $this->insert($arr);
	}

	/**
	 * addtime 2013,4,9
	 * @param unknown_type $arr
	 * @return boolean
	 */
	public function del($arr)
	{
		if (!$arr) {
			return false;
		}

		$where = '';

		foreach ($arr as $key => $value) {
			if ($where != '') {
				$where .= ' and ';
			}

			$where .= $this->getFieldValue2($key, $value);
		}

		if (!$where) {
			return false;
		}

		return $this->delData($where);
	}

	/**
	 * addtime 2013,4,9
	 * @param unknown_type $where_arr
	 * @param unknown_type $type
	 * @param unknown_type $fields
	 * @param unknown_type $where
	 * @return Ambigous <boolean, PDOStatement>
	 */
	public function get($where_arr, $type, $fields, $where)
	{
		if (!$where) {
			$where = '';

			foreach ($where_arr as $key => $value) {
				if ($where != '') {
					$where .= ' and ';
				}

				$where .= $this->getFieldValue2($key, $value);
			}
		}

		return $this->select($fields, $where, $type);
	}

	/**
	 * addtime 2013,4,9
	 * @param unknown_type $where
	 * @param unknown_type $arr
	 * @return Ambigous <boolean, PDOStatement>
	 */
	public function set($where, $arr)
	{
		if (is_array($where)) {
			$wherestr = '';

			foreach ($where as $key => $value) {
				if ($wherestr != '') {
					$wherestr .= ' and ';
				}

				$wherestr .= $this->getFieldValue2($key, $value);
			}
		}
		else {
			$wherestr = $where;
		}

		return $this->update($arr, $wherestr);
	}
}

?>