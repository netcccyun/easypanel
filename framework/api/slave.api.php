<?php
class SlaveAPI extends API
{
	public function slaveUpdate($server, $oldslave, $arr)
	{
		$slave = daocall('slaves', 'slavesGet', array($server, $oldslave));

		if (!daocall('slaves', 'slaveUpdate', array($arr['server'], $oldslave, $arr))) {
			trigger_error('更新数据库失败');
			return false;
		}

		if ($slave['ns'] != $arr['ns'] || $oldslave != $arr['slave']) {
			return apicall('bind', 'bindInit', array());
		}

		return true;
	}
}

?>