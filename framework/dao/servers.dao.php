<?php
class ServersDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('server' => 'server', 'ns' => 'ns');
		$this->MAP_TYPE = array();
		$this->_TABLE = 'servers';
		$this->_DBFILE = 'dns';
	}

	public function serverAdd($arr)
	{
		return $this->insert($arr);
	}

	public function serverUpdate($server, $arr)
	{
		$where = $this->getFieldValue2('server', $server);
		return $this->update($arr, $where);
	}

	public function serverDel($server)
	{
		return $this->delData($this->getFieldValue2('server', $server));
	}

	public function serverPageList($page, $page_count, &$count)
	{
		$fields = array('server', 'ns');

		if (!$order_field) {
			$order_field = 'server';
		}

		$where = '';
		return $this->selectPage($fields, $where, $order_field, true, $page, $page_count, $count);
	}

	public function serverGet($where_arr = null, $fields = null)
	{
		$where = null;
		$type = 'rows';

		if ($where_arr['server']) {
			$where = $this->getFieldValue2('server', $where_arr['server']);
			$type = 'row';
		}

		return $this->select($fields, $where, $type);
	}
}

?>