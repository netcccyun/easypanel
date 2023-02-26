<?php
class ProductDAO extends DAO
{
	/**
	 * 日志分析
	 * log_handle 1为开启,0为不开启
	 */
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('id' => 'id', 'product_name' => 'product_name', 'module' => 'module', 'templete' => 'templete', 'subtemplete' => 'subtemplete', 'subdir_flag' => 'subdir_flag', 'subdir' => 'subdir', 'web_quota' => 'web_quota', 'db_quota' => 'db_quota', 'domain' => 'domain', 'htaccess' => 'htaccess', 'max_connect' => 'max_connect', 'ftp' => 'ftp', 'envs' => 'envs', 'log_file' => 'log_file', 'access' => 'access', 'speed_limit' => 'speed_limit', 'log_handle' => 'log_handle', 'cdn' => 'cdn', 'db_type' => 'db_type', 'max_worker' => 'max_worker', 'max_queue' => 'max_queue', 'max_subdir' => 'max_subdir', 'flow_limit' => 'flow_limit', 'ftp_connect' => 'ftp_connect', 'ftp_usl' => 'ftp_usl', 'ftp_dsl' => 'ftp_dsl', 'ip' => 'ip', 'port' => 'port', 'ssi' => 'ssi', 'ignore_backup' => 'ignore_backup', 'cron' => 'cron', 'default_index' => 'default_index');
		$this->MAP_TYPE = array('id' => FIELD_TYPE_AUTO, 'subdir_flag' => FIELD_TYPE_INT, 'web_quota' => FIELD_TYPE_INT, 'db_quota' => FIELD_TYPE_INT, 'domain' => FIELD_TYPE_INT, 'max_connect' => FIELD_TYPE_INT, 'ftp' => FIELD_TYPE_INT, 'speed_limit' => FIELD_TYPE_INT, 'log_handle' => FIELD_TYPE_INT, 'cdn' => FIELD_TYPE_INT, 'max_worker' => FIELD_TYPE_INT, 'max_subdir' => FIELD_TYPE_INT, 'max_queue' => FIELD_TYPE_INT, 'flow_limit' => FIELD_TYPE_INT, 'ftp_connect' => FIELD_TYPE_INT, 'ftp_usl' => FIELD_TYPE_INT, 'ftp_dsl' => FIELD_TYPE_INT, 'ssi' => FIELD_TYPE_INT, 'ignore_backup' => FIELD_TYPE_INT, 'cron' => FIELD_TYPE_INT);
		$this->_TABLE = DBPRE . 'product';
	}

	/**
	 * ftp_usl  and ftp_dsl default is KB 
	 * not * 1024
	 * @param unknown_type $attr
	 * @param unknown_type $id
	 * @return Ambigous <boolean, PDOStatement>
	 */
	public function addProduct($attr, $id = null)
	{
		$arr = $attr;
		$arr['speed_limit'] = $attr['speed_limit'] * 1024;

		if ($id != null) {
			foreach ($this->MAP_ARR as $k => $v) {
				if (!isset($arr[$k])) {
					$arr[$k] = '';
				}
			}

			return $this->update($arr, $this->getFieldValue2('id', $id));
		}

		return $this->insert($arr);
	}

	public function pageListProduct($page, $page_count, &$count)
	{
		$fields = array('id', 'product_name', 'templete', 'subtemplete', 'subdir_flag', 'subdir', 'web_quota', 'db_quota', 'domain', 'htaccess', 'max_connect', 'ftp', 'log_file', 'access', 'speed_limit', 'log_handle', 'module');
		return $this->selectPage($fields, null, 'id', true, $page, $page_count, $count);
	}

	public function getProductByName($product_name)
	{
		return $this->select(null, $this->getFieldValue2('product_name', $product_name), 'row');
	}

	public function delProduct($id)
	{
		return $this->delData($this->getFieldValue2('id', $id));
	}

	public function getProduct($id, $fields = null)
	{
		$where = $this->getFieldValue2('id', $id);
		return $this->select($fields, $where, 'row');
	}

	public function getProducts()
	{
		return $this->select(null);
	}

	public function open_db()
	{
		return $this->connect();
	}
}

?>