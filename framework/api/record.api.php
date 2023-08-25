<?php
class RecordAPI extends API
{
	public function recordAdd($arr)
	{
		if (daocall('records', 'recordAdd', array($arr))) {
			return apicall('bind', 'writeZoneFile', array($arr['domain']));
		}

		return false;
	}

	/**
	 * arr['domain']一定要有
	 * Enter description here ...
	 * @param unknown_type $arr
	 */
	public function recordDel($arr)
	{
		if (!$arr['domain'] && $arr['id']) {
			$arr = daocall('records', 'recordGet', array($arr));
		}

		if (daocall('records', 'recordDel', array($arr))) {
			return apicall('bind', 'writeZoneFile', array($arr['domain']));
		}

		return false;
	}

	public function recordUpdate($id, $arr)
	{
		if (daocall('records', 'recordUpdate', array($id, $arr))) {
			return apicall('bind', 'writeZoneFile', array($arr['domain']));
		}

		return false;
	}

	/**
	 * 向dnsdun添加赠送域名的解析。
	 * @param unknown $name
	 * @param string $value
	 * @param string $view
	 * @param string $type
	 * @param number $ttl
	 * @return boolean
	 */
	public function addDnsdunRecord($name, $value = null, $view = '默认', $type = 'CNAME', $ttl = 600)
	{
		load_lib('pub:dnsdun');
		$setting = daocall('setting', 'getAll', array());
		$domain = $setting['dnsdundomain'] ? $setting['dnsdundomain'] : $setting['vhost_domain'];
		$domainkey = $setting['dnsdundomainkey'];
		$vhost_domain = $setting['vhost_domain'];
		$value = $value ? $value : $setting['cname_host'];
		$subname = $name;

		if ($type == 'CNAME') {
			if (!$value) {
				setLastError('CNAME 记录解析值未设置');
				return false;
			}

			if ($domain != $vhost_domain) {
				$len = strlen($domain);
				$vlen = strlen($vhost_domain);
				$domainname = substr($vhost_domain, 0, $vlen - $len - 1);
				$subname .= '.' . $domainname;
			}

			if (substr($value, 0 - 1) != '.') {
				$value .= '.';
			}
		}
		else {
			$value = $value ? $value : gethostbyname($_SERVER['SERVER_NAME']);
		}

		if (!$domainkey || !$domain) {
			setLastError('域名或域名api密钥未设置');
			return false;
		}

		$dnsdun = new Dnsdun($domain, $domainkey);
		$recordid = $dnsdun->addRecord($subname, $value, $view, $type, $ttl);

		if ($recordid !== false) {
			daocall('vhost', 'setRecordid', array($name, $recordid));
			return true;
		}

		return false;
	}

	/**
	 * 删除dnsdun的赠送域名解析，recordid保存在vhost表
	 * @param unknown $recordid
	 * @return boolean
	 */
	public function delDnsdunRecord($recordid)
	{
		load_lib('pub:dnsdun');
		$setting = daocall('setting', 'getAll', array());
		$domain = $setting['dnsdundomain'] ? $setting['dnsdundomain'] : $setting['vhost_domain'];
		$domainkey = $setting['dnsdundomainkey'];
		if (!$domainkey || !$domain) {
			setLastError('域名或域名api密钥未设置');
			return false;
		}

		$dnsdun = new Dnsdun($domain, $domainkey);
		return $dnsdun->delRecord($recordid);
	}

	public function test($domain = null, $domainkey = null)
	{
		load_lib('pub:dnsdun');
		$setting = daocall('setting', 'getAll', array());
		$domain = $domain ? $domain : $setting['dnsdundomain'];
		$domainkey = $domainkey ? $domainkey : $setting['dnsdundomainkey'];
		if (!$domainkey || !$domain) {
			setLastError('域名或域名api密钥未设置');
			return false;
		}

		$dnsdun = new Dnsdun($domain, $domainkey);
		return $dnsdun->test();
	}
}

?>