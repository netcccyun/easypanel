<?php
class Access
{
	private $vh;
	private $access;
	private $whm;

	public function __construct($vh = null, $access = 'request')
	{
		$this->vh = $vh;
		$this->access = $access;
		$this->whm = apicall('nodes', 'makeWhm', array('localhost'));
	}

	public function listTable()
	{
		$result = $this->call('list_table', null);
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return $result->getAll('table');
	}

	public function addTable($table)
	{
		$result = $this->call('add_table', array('table_name' => $table));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function emptyTable($table)
	{
		$result = $this->call('empty_table', array('table_name' => $table));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function delTable($table)
	{
		$result = $this->call('del_table', array('table_name' => $table));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function findChain($table, $name)
	{
		$result = $this->call('list_chain', array('table_name' => $table, 'name' => $name, 'detail' => 1));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return $result->get('table_info')->children();
	}

	/**
	 * $detail为1时得到的是详细的信息，否则为概要
	 * Enter description here ...
	 * @param  $table
	 * @param  $detail
	 */
	public function listChain($table, $detail = 1)
	{
		$result = $this->call('list_chain', array('table_name' => $table, 'detail' => $detail));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return $result->get('table_info')->children();
	}

	public function editChain($table, $arr, $models = null)
	{
		return $this->replaceChain('edit_chain', $table, $arr, $models);
	}

	public function addChain($table, $arr, $models = null)
	{
		return $this->replaceChain('add_chain', $table, $arr, $models);
	}

	private function replaceChain($callName, $table, $arr, $models = null)
	{
		$arr['table_name'] = $table;
		$whmCall = new WhmCall('core.whm', $callName);
		$whmCall->multi_param = true;
		$whmCall->addParam('access', $this->access);

		if ($this->vh) {
			$whmCall->addParam('vh', $this->vh);
		}

		foreach ($arr as $k => $v) {
			$whmCall->addParam($k, $v);
		}

		if ($models) {
			foreach ($models as $name => $val) {
				if(strpos($name, '#')) $name = explode('#',$name)[0];
				if (isset($val[0])) {
					foreach ($val as $v) {
						$this->setModel($whmCall, $name, $v);
					}
				}
				else {
					$this->setModel($whmCall, $name, $val);
				}
			}
		}

		$result = $this->whm->call($whmCall);
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	private function setModel(&$whmCall, $name, $val)
	{
		$whmCall->addParam('begin_sub_form', $name);

		foreach ($val as $k => $v) {
			$whmCall->addParam($k, $v);
		}

		$whmCall->addParam('end_sub_form', 1);
	}

	public function delChainByName($table, $name)
	{
		$result = $this->call('del_chain', array('table_name' => $table, 'name' => $name));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function delChain($table, $id)
	{
		$result = $this->call('del_chain', array('table_name' => $table, 'id' => $id));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	private function call($callname, $arr)
	{
		$whmCall = new WhmCall('core.whm', $callname);
		$whmCall->multi_param = true;
		$whmCall->addParam('access', $this->access);

		if ($this->vh) {
			$whmCall->addParam('vh', $this->vh);
		}

		if ($arr) {
			foreach ($arr as $k => $v) {
				$whmCall->addParam($k, $v);
			}
		}

		return $this->whm->call($whmCall);
	}
}


?>