<?php
abstract class Product
{
	/**
	 * 计算金额
	 * @param $price 每年的价格
	 * @param $month 月份
	 */
	public function caculatePrice($price, $month)
	{
		if ($this->isYears($month)) {
			return $price * $month / 12;
		}

		$price = $price / 12;
		$price *= $month;

		if ($month == 1) {
		}

		return $price;
	}

	public function isYears($month)
	{
		return $month / 12 * 12 == $month;
	}

	/**
	 * 购买产品
	 * @param $username 用户名	@deprecate 
	 * @param $product_id 产品ID
	 * @param $suser 产品参数
	 * @$suser['init'] =1 表示新建,否则为重建
	 */
	public function sell($username, $product_id, $suser)
	{
		global $default_db;
		$username = $suser['name'];

		if (!$this->checkParam($username, $suser)) {
			return false;
		}

		$info = $suser;
		$info['node'] = 'localhost';

		if ($suser['module']) {
			$suser['templete'] = 'easypanel';
			unset($suser['subtemplete']);
		}

		if ($suser['edit'] == 1) {
			$vhost = daocall('vhost', 'getVhost', array($suser['name']));
		}

		if (!$this->create($username, $suser, $info)) {
			return false;
		}

		daocall('vhostinfo', 'addBind', array($suser['name'], $suser['ip'], $suser['port']));
		notice_cdn_changed($username);

		if ($suser['edit'] == 1) {
			$sync_db = false;

			if (0 < $vhost['db_quota']) {
				if ($vhost['db_type'] != $suser['db_type'] || $suser['db_quota'] == 0) {
					$db = apicall('nodes', 'makeDbProduct', array('localhost', $vhost['db_type']));

					if (is_object($db)) {
						$db->remove($vhost['db_name']);
						$db->change_quota($vhost);
					}

					if (0 < $suser['db_quota']) {
						$sync_db = true;
					}
				}
			}
			else {
				if (0 < $suser['db_quota']) {
					$sync_db = true;
				}
			}

			return apicall('vhost', 'resync', array($suser['name'], $sync_db, false));
		}

		return $this->sync($username, $suser, $info);
	}

	/**
	 * @deprecated
	 * 续费操作
	 * @param $username
	 * @param $susername
	 * @param $month
	 */
	public function renew($username, $susername, $month)
	{
	}

	/**
	 * 产品升级。
	 * @deprecated
	 * @param  $username
	 * @param  $susername
	 * @param  $new_product_id
	 */
	public function upgrade($username, $susername, $new_product_id)
	{
	}

	/**
	 * 得到产品信息
	 * @param $product_id 产品ID
	 */
	abstract public function getInfo($product_id, $susername = null);

	/**
	 * 给付产品,这一步只插入数据库
	 * @param  $user
	 * @param  $product_id
	 * @param  $month
	 * @param  $param
	 * @param  $params
	 */
	abstract protected function create($username, &$suser = array(), $product_info = array());

	/**
	 * 
	 * 更新用户数据
	 * @param $susername  用户名
	 * @param $month      月份
	 * @param $product_id 新产品ID,如果是0，则不更新
	 */
	abstract protected function addMonth($susername, $month);

	/**
	 * 
	 * 更改产品类型
	 * @param $susername
	 * @param $product_id
	 */
	abstract protected function changeProduct($susername, $product_id);

	/**
	 * 同步产品到磁盘或者远程
	 * @param  $user
	 * @param  $param
	 */
	abstract public function sync($username, $suser, $product_info);

	abstract protected function resync($username, $suser, $oproduct, $nproduct = null);

	abstract public function getSuser($susername);

	abstract public function checkParam($username, $suser);
}


?>