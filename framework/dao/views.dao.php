<?php
class ViewsDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('id' => 'id', 'name' => 'name', 'desc' => 'desc', 'key' => 'key');
		$this->MAP_TYPE = array();
		$this->_TABLE = 'views';
		$this->_DBFILE = 'dns';
	}

	public function getView($name)
	{
		return $this->select(null, $this->getFieldValue2('name', $name), 'row');
	}

	public function viewsAdd($arr)
	{
		return $this->insert($arr, 'replace');
	}

	public function viewsDel($name = null)
	{
		$where = null;

		if ($name != null) {
			$where = $this->getFieldValue2('name', $name);
		}

		return $this->delData($where);
	}

	public function viewsList($where_arr = null, $fields = null)
	{
		$where = '1=1 order by id desc';
		return $this->select($fields, $where, 'rows');
	}

	public function viewChange($name, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('name', $name));
	}

	public function viewUpdate($name, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('name', $name));
	}

	public function viewsPageList($page, $page_count, &$count)
	{
		$fields = array('id', 'name', 'desc', 'key');
		$where = null;
		$order_field = 'name';
		$desc = true;
		return $this->selectPage($fields, $where, $order_field, $desc, $page, $page_count, $count);
	}
}

?>