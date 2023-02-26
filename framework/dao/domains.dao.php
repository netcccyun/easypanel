<?php
class DomainsDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('name' => 'name', 'passwd' => 'passwd', 'salt' => 'salt', 'status' => 'status', 'max_record' => 'max_record', 'server' => 'server');
		$this->MAP_TYPE = array('status' => FIELD_TYPE_INT, 'max_record' => FIELD_TYPE_INT, 'passwd' => FIELD_TYPE_MD5);
		$this->_TABLE = 'domains';
		$this->_DBFILE = 'dns';
	}

	public function domainAdd($arr)
	{
		return $this->insert($arr);
	}

	public function domainDel($name)
	{
		return $this->delData($this->getFieldValue2('name', $name));
	}

	public function domainUpdate($name, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('name', $name));
	}

	public function domainList($where = null, $fields = null)
	{
		return $this->select($fields, $where);
	}

	public function getDomain($arr = array(), $fields = null)
	{
		$where = '';
		$type = 'rows';

		if ($arr['name']) {
			$where = $this->getFieldValue2('name', $arr['name']);
			$type = 'row';
		}

		return $this->select($fields, $where, $type);
	}

	public function domainPageList($page, $page_count, &$count, $where_arr = null, $order_field = null)
	{
		$fields = array('name', 'passwd', 'salt', 'status', 'max_record', 'server');

		if (!$order_field) {
			$order_field = 'name';
		}

		$where = null;

		if ($where_arr['server']) {
			$where = $this->getFieldValue2('server', $where_arr['server']);
		}

		if ($where_arr['name']) {
			$where = $this->getFieldValue2('name', $where_arr['name']);
		}

		return $this->selectPage($fields, $where, $order_field, $desc, $page, $page_count, $count);
	}
}

?>