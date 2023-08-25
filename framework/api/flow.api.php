<?php
load_lib('pub:flow');
class FlowAPI extends API
{
	private $flowobj;

	public function __construct()
	{
		$flowobj = new flow('global.db');
		$this->flowobj = $flowobj;
	}

	public function getFlow($table, $name, $t)
	{
		return $this->flowobj->getFlow($table, $name, $t);
	}

	public function getListflow($table, $t)
	{
		return $this->flowobj->getListflow($table, $t);
	}

	public function getMonthFlow($name, $t)
	{
		$result = $this->flowobj->getMonthFlow($name, $t);
		return $result;
	}

	/**
	 * 得到虚拟主机当前月流量
	 * @param $name
	 * @return Ambigous <mixed, boolean>
	 */
	public function getCurrentMonthFlow($name)
	{
		return $this->getMonthFlow($name, date('Ym'));
	}
}

?>