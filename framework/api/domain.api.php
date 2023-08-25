<?php
class DomainAPI extends API
{
	public function checkPasswd($name, $passwd)
	{
		$arr['name'] = $name;
		$domain = daocall('domains', 'getDomain', array($arr));

		if (!$domain) {
			return false;
		}

		if ($domain['passwd'] != md5($passwd)) {
			return false;
		}

		return true;
	}

	public function domainAdd($arr)
	{
		daocall('domains', 'domainAdd', array($arr));
		return apicall('bind', 'domainAdd', array($arr['name']));
	}

	public function domainDel($name)
	{
		$name = apicall('checkparam', 'checkParam', array($name));

		if (!$name) {
			return false;
		}

		if (daocall('domains', 'domainDel', array($name))) {
			$arr['domain'] = $name;
			daocall('records', 'recordDel', array($arr));
			return apicall('bind', 'domainDel', array($name));
		}

		return false;
	}

	public function domainUpdate($name, $arr)
	{
		daocall('domains', 'domainUpdate', array($name, $arr));
		return apicall('bind', 'writeZoneFile', array($name));
	}

	public function changePasswd($domain, $passwd)
	{
		$arr['passwd'] = $passwd;
		return daocall('domains', 'domainUpdate', array($domain, $arr));
	}
}

?>