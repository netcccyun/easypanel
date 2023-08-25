<?php
class FilterDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('value' => 'value');
		$this->_TABLE = DBPRE . 'filter';
	}

	public function add($value)
	{
		$arr['value'] = $value;
		return $this->insert($arr);
	}

	public function clear()
	{
		return $this->delData(null);
	}

	public function listFilter()
	{
		return $this->select(null);
	}
}

?>