<?php
class SettingDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('name' => 'name', 'value' => 'value');
		$this->MAP_TYPE = array();
		$this->_TABLE = DBPRE . 'setting';
	}

	public function add($name, $value)
	{
		$arr['name'] = $name;
		$arr['value'] = $value;
		return $this->insert($arr, 'REPLACE');
	}

	public function get($name)
	{
		$ret = $this->getData2(array('value'), $this->getFieldValue2('name', $name), 'row');

		if (!$ret) {
			return null;
		}

		return $ret['value'];
	}

	/**
	 * 插入值需要叠加的字段
	 * $value int
	 * @param string $name
	 * @param int $value
	 */
	public function _setStackValue($name, $value)
	{
		$sql = 'select name as name ,value as value from ' . $this->_TABLE . ' where name=\'' . $name . '\'';
		$result = $this->executex($sql, 'row');

		if (!$result) {
			$sql = 'insert into setting (`name`,`value`) values (\'' . $name . '\',\'' . $value . '\')';
			return $this->executex($sql);
		}

		$sql = 'update ' . $this->_TABLE . ' set `value`=`value`+' . $value . ' where name=\'' . $name . '\'';
		return $this->executex($sql);
	}

	public function getAll()
	{
		$list = $this->getData();

		if (!$list) {
			return null;
		}

		$arr = array();

		foreach ($list as $item) {
			$arr[$item['name']] = $item['value'];
		}

		return $arr;
	}
}

?>