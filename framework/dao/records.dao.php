<?php
class RecordsDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('id' => 'id', 'domain' => 'domain', 'name' => 'name', 'type' => 'type', 'value' => 'value', 'view' => 'view', 'ttl' => 'ttl', 'status' => 'status', 'prio' => 'prio', 'change_date' => 'change_date');
		$this->MAP_TYPE = array('id' => FIELD_TYPE_AUTO | FIELD_TYPE_INT, 'ttl' => FIELD_TYPE_INT, 'status' => FIELD_TYPE_INT);
		$this->_TABLE = 'records';
		$this->_DBFILE = 'dns';
	}

	public function recordGetCount($domain)
	{
		$sql = 'select count(domain) as count from ' . $this->_TABLE . ' where domain=\'' . $domain . '\'';
		return $this->executex($sql, 'row');
	}

	/**
	 * @param array $arr
	 */
	public function recordAdd($arr)
	{
		$arr['change_date'] = time();
		return $this->insert($arr, 'replace');
	}

	/**
	 * @param array $arr
	 */
	public function recordDel($arr = array())
	{
		if ($arr['id']) {
			$where = $this->getFieldValue2('id', $arr['id']);

			if ($arr['domain']) {
				$where .= ' and ' . $this->getFieldValue2('domain', $arr['domain']);
			}
		}
		else {
			if ($arr['domain']) {
				$where = $this->getFieldValue2('domain', $arr['domain']);

				if ($arr['name']) {
					$where .= ' and ' . $this->getFieldValue2('name', $arr['name']);
				}
			}
		}

		if ($where) {
			return $this->delData($where);
		}

		return false;
	}

	/**
	 * @param number $id
	 * @param array $arr
	 */
	public function recordUpdate($id, $arr)
	{
		$where = $this->getFieldValue2('id', $id);

		if ($arr['domain']) {
			$where .= ' and ' . $this->getFieldValue2('domain', $arr['domain']);
		}

		return $this->update($arr, $where);
	}

	public function recordListByDomain($domain, $any)
	{
		$where = $this->getFieldValue2('domain', $domain);
		$where .= ' AND ' . $this->MAP_ARR['view'];

		if (!$any) {
			$where .= '!';
		}

		$where .= '=\'any\'';
		return $this->select(null, $where, 'rows');
	}

	/**
	 * @param array $where_arr
	 * @param array $fields
	 */
	public function recordList($where_arr = null, $fields = null)
	{
		$where = null;
		$type = 'rows';

		if ($where_arr['domain']) {
			$where = $this->getFieldValue2('domain', $where_arr['domain']);
			$type = 'rows';
		}

		return $this->select($fields, $where, $type);
	}

	/**
	 * @param array $where_arr
	 */
	public function recordGet($where_arr = array())
	{
		$where = null;
		$type = 'row';

		if ($where_arr['id']) {
			$where .= $this->getFieldValue2('id', $where_arr['id']);
		}

		if ($where_arr['domain']) {
			if ($where) {
				$where .= ' and ';
			}
			else {
				$type = 'rows';
			}

			$where .= $this->getFieldValue2('domain', $where_arr['domain']);

			if ($where_arr['name']) {
				$where .= ' and ' . $this->getFieldValue2('name', $where_arr['name']);
				$type = 'row';
			}
		}

		return $this->select(null, $where, $type);
	}

	public function recordPageList($page, $page_count, $count, $where_arr = null, $order_field = null)
	{
		$fields = array('id', 'domain', 'name', 'type', 'value', 'view', 'ttl', 'status', 'prio', 'change_date');

		if (!$order_field) {
			$order_field = 'id';
		}

		$where = null;

		if ($where_arr['id']) {
			$where = $this->getFieldValue2('id', $where_arr['id']);
		}

		if ($where_arr['domain']) {
			$where = $this->getFieldValue2('domain', $where_arr['domain']);
		}

		if ($where_arr['view']) {
			$where = $this->getFieldValue2('view', $where_arr['view']);
		}

		return $this->selectPage($fields, $where, $order_field, $desc, $page, $page_count, $count);
	}
}

?>