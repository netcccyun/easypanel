<?php
class AccessAPI extends API
{
	/**
	 * 
	 * Enter description here ...
	 * @param string $type
	 * @param string $version
	 */
	public function checkAccess($type = null, $version = null)
	{
		if (!$_SESSION['user'][getRole('vhost')]['access']) {
			return '您的空间不支持该功能，请联系管理员';
		}

		return true;
	}

	public function checkEntAccess()
	{
		$host = $_SERVER['HTTP_HOST'];
		$hostarr = explode(':', $host);
		$host = $hostarr[0];

		if ($host != EP_ENT_HOST) {
			setLastError('当前访问域名和授权域名不符合.请使用' . EP_ENT_HOST . '访问');
			return false;
		}

		$nowTime = date('YmdHis', time());

		if (EP_ENT_EXPIRE < $nowTime) {
			setLastError('授权已过期');
			return false;
		}

		$entkey = apicall('nodes', 'getFullKey', array());
		$entkey .= 'mszLXJmYWV3c2Rwb2tdLTBpaz0gLW9pPTVvNDMtNQ';
		$entkey = base64_decode($entkey);
		$str = EP_ENT_EXPIRE . $entkey . $host;
		$entkeys = substr(EP_ENT_KEYS, 0, 32);

		if (md5(md5($str) . 'dnsdun_kangle_cdnbest') == $entkeys) {
			return true;
		}

		setLastError('验证失败');
		return false;
	}
}

?>