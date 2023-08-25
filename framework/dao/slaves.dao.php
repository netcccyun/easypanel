<?php
class SlavesDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('server' => 'server', 'slave' => 'slave', 'ns' => 'ns', 'skey' => 'skey');
		$this->MAP_TYPE = array();
		$this->_TABLE = 'slaves';
		$this->_DBFILE = 'dns';
	}

	public function slaveAdd($arr)
	{
		return $this->insert($arr);
	}

	public function slaveDel($server, $slave = null)
	{
		$where = $this->getFieldValue2('server', $server);

		if ($slave) {
			$where .= ' and ' . $this->getFieldValue2('slave', $slave);
		}

		return $this->delData($where);
	}

	public function slaveUpdate($server, $slave, $arr)
	{
		return $this->update($arr, $this->getFieldValue2('server', $server) . ' and ' . $this->getFieldValue2('slave', $slave));
	}

	public function slavePageList($page, $page_count, $count, $where_arr = null)
	{
		$fields = array('server', 'slave', 'skey', 'ns');

		if (!$order_field) {
			$order_field = 'slave';
		}

		if ($where_arr['server']) {
			$where = $this->getFieldValue2('server', $where_arr['server']);
		}

		return $this->selectPage($fields, $where, $order_field, true, $page, $page_count, $count);
	}

	public function slavesGet($where_arr = null)
	{
		$where = null;
		$type = 'rows';
		$fields = array('server', 'slave', 'ns', 'skey');

		if ($where_arr['server']) {
			$where = $this->getFieldValue2('server', $where_arr['server']);
			$type = 'rows';

			if ($where_arr['slave']) {
				$where .= ' and ' . $this->getFieldValue2('slave', $where_arr['slave']);
				$type = 'row';
			}
		}

		return $this->select($fields, $where, $type);
	}
}

?>