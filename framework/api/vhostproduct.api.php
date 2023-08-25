<?php
class VhostproductAPI extends API
{
	public function add($arr, $migrate = null)
	{
		$name = $arr['product_name'];

		if ($arr['cdn'] == '1') {
			$arr['subdir_flag'] = 1;
			$arr['templete'] = 'html';
			$arr['subtemplete'] = null;
			$arr['db_quota'] = 0;
			$arr['web_quota'] = 0;
		}

		if (daocall('product', 'getProductByName', array($name))) {
			setLastError('产品名重复');
			print_r($GLOBALS['last_error']);
			return false;
		}

		if (!daocall('product', 'addProduct', array($arr))) {
			setLastError('插入失败');
			return false;
		}

		return true;
	}

	/**
	 * da.ctl.php
	 * Enter description here ...
	 */
	public function del()
	{
	}
}

?>