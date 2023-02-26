<?php
class VhostDAO extends DAO
{
	private $vh_col_map = array('name', 'doc_root', 'user', 'group', 'templete', 'status');

	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('name' => 'name', 'passwd' => 'passwd', 'doc_root' => 'doc_root', 'uid' => 'uid', 'gid' => 'gid', 'module' => 'module', 'templete' => 'templete', 'subtemplete' => 'subtemplete', 'create_time' => 'create_time', 'expire_time2' => 'expire_time2', 'status' => 'status', 'subdir_flag' => 'subdir_flag', 'subdir' => 'subdir', 'web_quota' => 'web_quota', 'db_quota' => 'db_quota', 'domain' => 'domain', 'htaccess' => 'htaccess', 'max_connect' => 'max_connect', 'max_worker' => 'max_worker', 'max_queue' => 'max_queue', 'ftp' => 'ftp', 'log_file' => 'log_file', 'access' => 'access', 'db_name' => 'db_name', 'speed_limit' => 'speed_limit', 'product_id' => 'product_id', 'envs' => 'envs', 'cs' => 'cs', 'cdn' => 'cdn', 'ext_passwd' => 'ext_passwd', 'db_type' => 'db_type', 'log_handle' => 'log_handle', 'max_subdir' => 'max_subdir', 'flow' => 'flow', 'sync_seq' => 'sync_seq', 'flow_limit' => 'flow_limit', 'ftp_connect' => 'ftp_connect', 'ftp_usl' => 'ftp_usl', 'ftp_dsl' => 'ftp_dsl', 'ip' => 'ip', 'port' => 'port', 'certificate' => 'certificate', 'certificate_key' => 'certificate_key', 'ftp_subdir' => 'ftp_subdir', 'last_password_error' => 'last_password_error', 'password_error_count' => 'password_error_count', 'password_security' => 'password_security', 'ssi' => 'ssi', 'ignore_backup' => 'ignore_backup', 'cron' => 'cron', 'recordid' => 'recordid', 'http2' => 'http2');
		$this->MAP_TYPE = array('passwd' => FIELD_TYPE_MD5, 'status' => FIELD_TYPE_INT, 'subdir_flag' => FIELD_TYPE_INT, 'web_quota' => FIELD_TYPE_INT, 'db_quota' => FIELD_TYPE_INT, 'domain' => FIELD_TYPE_INT, 'max_connect' => FIELD_TYPE_INT, 'max_worker' => FIELD_TYPE_INT, 'max_queue' => FIELD_TYPE_INT, 'cs' => FIELD_TYPE_INT, 'ext_passwd' => FIELD_TYPE_INT, 'ftp' => FIELD_TYPE_INT, 'speed_limit' => FIELD_TYPE_INT, 'product_id' => FIELD_TYPE_INT, 'cdn' => FIELD_TYPE_INT, 'ext_passwd' => FIELD_TYPE_INT, 'log_handle' => FIELD_TYPE_INT, 'max_subdir' => FIELD_TYPE_INT, 'expire_time2' => FIELD_TYPE_INT, 'create_time' => FIELD_TYPE_INT, 'flow_limit' => FIELD_TYPE_INT, 'sync_seq' => FIELD_TYPE_INT, 'ftp_connect' => FIELD_TYPE_INT, 'ftp_usl' => FIELD_TYPE_INT, 'ftp_dsl' => FIELD_TYPE_INT, 'ssi' => FIELD_TYPE_INT, 'ignore_backup' => FIELD_TYPE_INT, 'cron' => FIELD_TYPE_INT, 'recordid' => FIELD_TYPE_INT, 'http2' => FIELD_TYPE_INT);
		$this->_TABLE = DBPRE . 'vhost';
	}

	/**
	 * 取出不是cdn的账号，因cdn是@打头，所以取出不是@打头的。
	 * @param $prefix string
	 * @param $fields array
	 */
	public function listVhostNotcdn($prefix = '@', $fields = null)
	{
		$len = strlen($prefix);
		$where = 'substr(`name`,0,' . $len . ')!=\'' . $prefix . '\'';
		return $this->select($fields, $where);
	}

	/**
	 * 删除CDN辅节点的所有CDN。
	 * @param string $prefix=nodename.'_'
	 */
	public function delNodeCdn($prefix)
	{
		$len = strlen($prefix);
		$sql = 'delete from ' . $this->_TABLE . ' where substr(`name`,0,' . $len . ')=\'' . $prefix . '\'';
		return $this->executex($sql);
	}

	/**
	 * 域名，access.xml自定义控制每操作一次，sync_seq加1，用于同步
	 * Enter description here ...
	 * @param unknown_type $name
	 * @param unknown_type $sync_seq
	 */
	public function updateSyncseq($name)
	{
		$sql = 'update ' . $this->_TABLE . ' set `sync_seq`=`sync_seq`+1 where name=\'' . $name . '\'';
		return $this->executex($sql);
	}

	/**
	 * @param unknown $name
	 * @param unknown $recordid
	 * @return Ambigous <boolean, PDOStatement>
	 */
	public function setRecordid($name, $recordid)
	{
		$arr['recordid'] = $recordid;
		return $this->update($arr, $this->getFieldValue2('name', $name));
	}

	public function setPasswd($name, $passwd)
	{
		$sql = 'UPDATE vhost SET `passwd`=\'' . $passwd . '\' where `name`=\'' . $name . '\'';
		return $this->executex($sql);
	}

	public function ListByExpiretime($status = 0)
	{
		$time = time();
		$sql = 'SELECT `name` AS `name` FROM ';
		$sql .= $this->_TABLE . ' WHERE  expire_time2 < ' . $time . ' and expire_time2 > 0';

		if (0 <= $status) {
			$sql .= ' and ' . $this->getFieldValue2('status', $status);
		}

		return $this->executex($sql, 'rows');
	}

	public function getExpireTime($month)
	{
		$month = intval($month);

		if ($month == 1) {
			return $month * 1.013952 * 2592000;
		}

		return $month * 1.01389 * 2592000;
	}

	/**
	 * type=db_use 查寻数据库使用数所用
	 * type=flow_limit 查寻流量的限制所用.
	 * Enter description here ...
	 * @param unknown_type $type
	 */
	public function getListvhost($type = null)
	{
		if ($type == 'db_use') {
			$fieids = array('name', 'db_name', 'db_quota', 'status');
			$where = 'db_quota > 0';
		}

		if ($type == 'flow_limit') {
			$fieids = array('name', 'flow_limit', 'status');
			$where = 'flow_limit > 0';
		}

		return $this->select($fields, $where);
	}

	/**
	 * 增加时间。
	 * @param  $name
	 * @param  $expire_time2 int
	 */
	public function addMonth($name, $month)
	{
		if($month == 0){
			$sql = 'UPDATE vhost SET `expire_time2`=0 WHERE `name`=\'' . $name . '\'';
		}else{
		$expire_time = $this->getExpireTime($month);
		$result = $this->select(array('name', 'expire_time2'), $this->getFieldValue2('name', $name), 'row');

		if (0 < $result['expire_time2']) {
			$sql = 'UPDATE vhost SET `expire_time2`=`expire_time2`+' . $expire_time . ' WHERE `name`=\'' . $name . '\'';
		}
		else {
			$sql = 'UPDATE vhost SET `expire_time2`=' . (time() + $expire_time) . ' WHERE `name`=\'' . $name . '\'';
		}
		}

		return $this->executex($sql);
	}

	public function updateDbname($name, $uid)
	{
		$arr['db_name'] = $uid;
		return $this->update($arr, $this->getFieldValue2('name', $name));
	}

	public function pageVhost($search, $page, $page_count, &$count)
	{
		$where = null;

		if ($search) {
			if (is_array($search)) {
				foreach ($search as $key => $value) {
					$where .= 'name like \'%' . $value . '%\'';
				}
			}
			else {
				$where = $this->getFieldValue2('name', $search);
			}
		}

		return $this->selectPage(null, $where, 'create_time', true, $page, $page_count, $count);
	}

	public function check($user)
	{
		$sql = 'SELECT 1 FROM ' . $this->_TABLE . ' WHERE ' . $this->getFieldValue2('name', $user);
		return $this->executex($sql, 'row');
	}

	/**
	 * 插入用户信息信息
	 */
	public function maxuid()
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
			$sql = 'SELECT max(abs(substr(uid ,2,255))) AS uid FROM  ' . $this->_TABLE;
		}
		else {
			$sql = 'SELECT max(abs(substr(uid ,1,255))) AS uid FROM  ' . $this->_TABLE;
		}

		$uids = $this->executex($sql, 'row');
		if (!$uids || count($uids) < 0) {
			return false;
		}

		foreach ($uids as $uid) {
			if (1000 <= intval($uid)) {
				return $uid;
			}
		}

		return false;
	}

	/**
	 * 可能的情况。
	 * 自定义uid,重建空间带uid进来。
	 * 由程序指定uid
	 * 所以:
	 * 用户传进来的uid不能为数字型，否则数据库中取最大uid会出现问题。
	 *
	 * @param  $arr array
	 */
	public function insertVhost($arr)
	{
		if ($arr['uid'] == '') {
			$uida = '';

			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				$uida = 'a';
			}

			$uid = $this->maxuid();

			if ($uid) {
				$uid = intval($uid) + 1;
			}
			else {
				$uid = '1000';
			}

			$arr['uid'] = $uida . $uid;
		}

		$this->insert($arr, 'REPLACE');
		return $arr['uid'];
	}

	public function updatePassword($username, $passwd)
	{
		$sql = 'UPDATE ' . $this->_TABLE . ' SET ' . $this->getFieldValue2('passwd', $passwd) . ' WHERE ' . $this->getFieldValue2('name', $username);
		return $this->executex($sql);
	}

	public function updateFtp($vhost, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('name', $vhost) . ' AND ftp>0');
	}

	/**
	 * 更新用户信息
	 */
	public function updateVhost($vhostName, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('name', $vhostName));
	}

	public function updateAll($arr, $where_arr = null)
	{
		$where = '';

		if ($where_arr) {
			if (is_array($where_arr)) {
				foreach ($where_arr as $key => $value) {
					if (!array_key_exists($key, $this->MAP_ARR)) {
						continue;
					}

					if ($where != '') {
						$where .= ' and ';
					}

					$where .= $this->getFieldValue2($key, $value);
				}
			}
			else {
				$where .= $where_arr;
			}
		}

		return $this->update($arr, $where);
	}

	/**
	 * 删除用户信息
	 */
	public function delVhost($vhostName)
	{
		$where = $this->getFieldValue2('name', $vhostName);

		if (!$vhostName) {
			$where = 'name IS NULL';
		}

		return $this->delData($where);
	}

	public function listVhostByUid($uid, $result = 'rows')
	{
		$where = $this->getFieldValue2('uid', $uid);
		return $this->getData($where, $result);
	}

	public function listVhostByName($name, $result = 'rows')
	{
		$where = $this->getFieldValue2('name', $name);
		return $this->getData($where, $result);
	}

	public function listCdnVhost($prefix = null, $fields = null)
	{
		$where = '';

		if ($prefix != null) {
			$len = strlen($prefix);
			$where = ' substr(`name`,0,' . $len . ')=\'' . $prefix . '\'';
		}

		return $this->select($fields, $where, 'rows');
	}

	public function listVhost($username = null, $result = 'rows', $fields = null)
	{
		$where = '';

		if ($username) {
			$where = $this->getFieldValue2('username', $username);
		}

		return $this->getData2($fields, $where, $result);
	}

	public function listMyVhost($username, $result = 'rows')
	{
		if ($username == '' || $username == null) {
			return false;
		}

		return $this->listVhost($username, $result);
	}

	/**
	 * @deprecated
	 */
	public function getFlushSql()
	{
		return ' AND ' . $this->MAP_ARR['name'] . '=\'%s\'';
	}

	/**
	 * @deprecated
	 */
	public function getLoadSql($node)
	{
		$sql = 'SELECT ';
		$i = 0;

		while ($i < count($this->vh_col_map)) {
			if (0 < $i) {
				$sql .= ',';
			}

			$col_name = $this->vh_col_map[$i];

			if ($col_name == 'user') {
				$col_name = $this->getUserColName($node);
			}
			else if ($col_name == 'group') {
				$col_name = $this->getGroupColName($node);
			}
			else if ($col_name == 'doc_root') {
				$col_name = $this->getDocRootColName($node);
			}
			else if ($col_name == 'templete') {
				$col_name = 'CONCAT(' . $this->MAP_ARR['templete'] . ',\':\',' . $this->MAP_ARR['subtemplete'] . ') AS templete';
			}
			else {
				$col_name = $this->MAP_ARR[$col_name];
			}

			$sql .= $col_name;
			++$i;
		}

		$sql .= ' FROM ' . $this->_TABLE . ' WHERE ';
		$sql .= $this->getFieldValue2('node', $node);
		return $sql;
	}

	public function getColMap($node)
	{
		$i = 0;
		$col_map = '';

		while ($i < count($this->vh_col_map)) {
			if (0 < $i) {
				$col_map .= ',';
			}

			if ($this->vh_col_map[$i] == 'group') {
				$col_map .= $this->getGroupKangleName($node);
			}
			else {
				$col_map .= $this->vh_col_map[$i];
			}

			++$i;
		}

		return $col_map;
	}

	private function getGroupColName($node)
	{
		if (apicall('nodes', 'isWindows', array($node))) {
			return $this->MAP_ARR['gid'] . ' AS `group`';
		}

		return 'CONCAT(\'#\',' . $this->MAP_ARR['gid'] . ') AS `group`';
	}

	private function getDocRootColName($node)
	{
		$node_cfg = $GLOBALS['node_cfg'][$node];
		if (is_array($node_cfg) && $node_cfg['win'] == 1) {
			return 'CONCAT(\'' . $node_cfg['dev'] . '\'' . ',' . $this->MAP_ARR['doc_root'] . ') AS doc_root';
		}

		return $this->MAP_ARR['doc_root'];
	}

	private function getUserColName($node)
	{
		if (apicall('nodes', 'isWindows', array($node))) {
			return 'CONCAT(\'a\',' . $this->MAP_ARR['uid'] . ') AS user';
		}

		return 'CONCAT(\'#\',' . $this->MAP_ARR['uid'] . ') AS user';
	}

	public function getVhost($name, $fields = null)
	{
		$where = $this->getFieldValue2('name', $name);

		if (!$name) {
			$where = 'name IS NULL';
		}

		return $this->getData2($fields, $where, 'row');
	}

	public function getNode($name)
	{
		return 'localhost';
	}

	public function getGroupKangleName($node)
	{
		if (apicall('nodes', 'isWindows', array($node))) {
			return 'password';
		}

		return 'group';
	}

	public function expireUser()
	{
		$sql = 'UPDATE ' . $this->_TABLE . ' SET ' . $this->getFieldValue2('status', 9) . ' WHERE ' . $this->MAP_ARR['expire_time2'] . '<NOW()';
		return $this->executex($sql, 'result');
	}

	public function getAllVhostByProduct_id($product_id = null)
	{
		$where = '';

		if ($product_id) {
			$where .= $this->getFieldValue2('product_id', $product_id);
		}

		return $this->select(null, $where);
	}

	public function get($where_arr, $type, $fields, $where)
	{
		if (!$where && $where_arr) {
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