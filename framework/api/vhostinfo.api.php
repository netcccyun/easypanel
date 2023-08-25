<?php
class VhostinfoAPI extends API
{
	private $daoname = 'vhostinfo';

	public function add($arr)
	{
		return daocall($this->daoname, 'add', array($arr));
	}

	public function add2($vhost, $name, $value, $type = 0)
	{
		$arr['vhost'] = $vhost;
		$arr['name'] = $name;
		$arr['value'] = $value;
		$arr['type'] = $type;
		return $this->add($arr);
	}

	public function del($arr)
	{
		return daocall($this->daoname, 'del', array($arr));
	}

	public function del2($vhost, $name, $type)
	{
		$arr['vhost'] = $vhost;
		$arr['name'] = $name;
		$arr['type'] = $type;
		return $this->del($arr);
	}

	public function get($arr, $type = 'rows', $field = null, $where = null)
	{
		return daocall($this->daoname, 'get', array($arr, $type, $field, $where));
	}

	public function get2($vhost, $name, $type)
	{
		$arr['vhost'] = $vhost;
		$arr['name'] = $name;
		$arr['type'] = $type;
		return $this->get($arr, 'row');
	}

	public function set($arr, $wherearr)
	{
		return daocall($this->daoname, 'set', array($wherearr, $arr));
	}

	public function set2($vhost, $name, $type, $value)
	{
		$wherearr['vhost'] = $vhost;
		$wherearr['name'] = $name;
		$wherearr['type'] = $type;
		$arr['value'] = $value;
		return $this->set($arr, $wherearr);
	}

	public function delById($id)
	{
		$arr['id'] = $id;
		return $this->del($arr);
	}

	public function delByVhost($vhost)
	{
		$arr['vhost'] = $vhost;
		return $this->del($arr);
	}

	public function getById($id)
	{
		$arr['id'] = $id;
		return $this->get($arr, 'row');
	}

	public function getByVhost($vhost)
	{
		$arr['vhost'] = $vhost;
		return $this->get($arr, 'rows');
	}

	public function setById($id, $arr)
	{
		$wherearr['id'] = $id;
		return $this->set($arr, $wherearr);
	}
}

?>