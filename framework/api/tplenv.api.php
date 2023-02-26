<?php
define(ENV_CHECK_FAILED, 0);
define(ENV_CHECK_SUCCESS, 1);
define(ENV_CHECK_NOT_FOUND, 2);
class TplenvAPI extends API
{
	public function __construct()
	{
		parent::__construct();
		@load_conf('pub:tplenv');
	}

	public function hasEnv($templete, $subtemplete)
	{
		if (0 < count($GLOBALS['tplenv'][$templete])) {
			return true;
		}

		if (0 < count($GLOBALS['tplenv'][$templete . ':' . $subtemplete])) {
			return true;
		}

		return false;
	}

	public function getEnv($templete, $subtemplete)
	{
		$env = @array_merge((array) $GLOBALS['tplenv'][$templete], (array) $GLOBALS['tplenv'][$templete . ':' . $subtemplete]);
		return $env;
	}

	public function setEnv($vhost, $templete, $subtemplete, $name, $value)
	{
		$ret = $this->checkEnv($name, $value, (array) $GLOBALS['tplenv'][$templete . ':' . $subtemplete]);

		if ($ret == ENV_CHECK_NOT_FOUND) {
			$ret = $this->checkEnv($name, $value, (array) $GLOBALS['tplenv'][$templete]);
		}

		if ($ret != ENV_CHECK_SUCCESS) {
			trigger_error('参数值:' . $value . '不合法');
			return false;
		}

		return apicall('vhost', 'addInfo', array($vhost, $name, 100, $value, false));
	}

	public function checkEnv($name, $value, $arr)
	{
		if (!is_array($arr[$name])) {
			return ENV_CHECK_NOT_FOUND;
		}

		switch ($arr[$name]['value'][0]) {
		case 'TEXT':
			if (preg_match($arr[$name]['value'][1], $value)) {
				return ENV_CHECK_SUCCESS;
			}

			return ENV_CHECK_FAILED;
		case 'RADIO':
			$n = 0;

			while ($n < count($arr[$name]['value'][1])) {
				if ($value == $arr[$name]['value'][1][$n][1]) {
					return ENV_CHECK_SUCCESS;
				}

				++$n;
			}

			return ENV_CHECK_FAILED;
		default:
		}

		return ENV_CHECK_FAILED;
	}
}

?>