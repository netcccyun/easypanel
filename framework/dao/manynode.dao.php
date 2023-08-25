<?php
class ManynodeDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('name' => 'name', 'host' => 'host', 'port' => 'port', 'mem' => 'mem', 'skey' => 'skey', 'synctime' => 'synctime', 'syncstatus' => 'syncstatus');
		$this->MAP_TYPE = array('syncstatus' => FIELD_TYPE_INT);
		$this->_TABLE = DBPRE . 'manynode';
	}

	public function add($name, $host, $port, $skey, $mem)
	{
		$arr = array('name' => $name, 'host' => $host, 'port' => $port, 'skey' => $skey, 'mem' => $mem);
		return $this->insert($arr, 'REPLACE');
	}

	public function del($name)
	{
		return $this->delData($this->getFieldValue2('name', $name));
	}

	public function getCount()
	{
		$sql = 'SELECT count(*) as c FROM ' . $this->_TABLE;
		$result = $this->executex($sql, 'row');

		if (!$result) {
			return 0;
		}

		return intval($result['c']);
	}

	public function get($name = null)
	{
		$where = '';
		$type = 'rows';

		if ($name != null) {
			$where = $this->getFieldValue2('name', $name);
			$type = 'row';
		}

		return $this->select(null, $where, $type);
	}

	public function updateManynode($name, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('name', $name));
	}

	public function pageList($page, $page_count, &$count)
	{
		$where = null;
		$order_field = 'name';
		$desc = true;
		return $this->selectPage(array('name', 'host', 'port', 'skey', 'mem', 'synctime', 'syncstatus'), $where, $order_field, $desc, $page, $page_count, $count);
	}
}

?>