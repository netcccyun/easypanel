<?php
class CheckparamAPI extends API
{
	/**
	 * grade 过滤等级
	 * 1为url级，一些=不能被过滤
	 * 0为正常参数
	 * @param unknown_type $param
	 */
	public function checkParam($param, $grade = 0)
	{
		$param = str_ireplace('\'', '', $param);
		$param = str_ireplace('"', '', $param);
		$param = str_ireplace(';', '', $param);
		$param = str_ireplace(' ', '', $param);

		if ($grade < 1) {
			$param = str_ireplace('\\', '', $param);
			$param = str_ireplace('=', '', $param);
		}

		$param = strip_tags($param);
		return $param;
	}

	public function checkArrParam($arr, $grade = 0)
	{
		foreach ($arr as $key => $value) {
			$a[$key] = $this->checkParam($value, $grade);
		}

		return $a;
	}
}

?>