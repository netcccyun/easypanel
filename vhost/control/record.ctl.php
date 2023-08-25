<?php
needRole('vhost');
class RecordControl extends Control
{
	public function recordAddFrom()
	{
		return $this->_tpl->fetch('record/from.html');
	}

	public function recordAdd()
	{
		$domain = trim($_REQUEST['domain']);
		$name = trim($_REQUEST['name']);

		if (!$name) {
			$name = '@';
		}

		$type = trim($_REQUEST['type']);
		$value = trim($_REQUEST['value']);
		$view = trim($_REQUEST['view']);
		$ttl = intval($_REQUEST['ttl']);
	}

	public function recordDel()
	{
	}

	public function recordUpdate()
	{
	}
}

?>