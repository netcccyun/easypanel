<?php
class API
{
	protected $old_info;
	protected $new_info;
	protected $RET_SVR_BUSY = false;
	protected $RET_PARAM_ER = false;
	protected $RET_VALUE_MINUS = -301;

	public function __construct()
	{
	}

	public function __destruct()
	{
	}

	protected function mcResult($ret)
	{
		if ($ret === 0 - 1) {
			return false;
		}

		if ($ret === 0 - 2) {
			return $this->RET_SVR_BUSY;
		}

		return $ret;
	}
}


?>