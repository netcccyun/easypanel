<?php
class Dnsdun
{
	private $domain;
	private $domainkey;
	private $host;
	private $port;
	private $obj;
	private $http;
	private $apidir;

	public function __construct($domain, $domainkey)
	{
		load_lib('pub:curl');
		$this->domain = $domain;
		$this->domainkey = $domainkey;

		if (!defined('DNSDUN_DOMAIN')) {
			define('DNSDUN_DOMAIN', 'api.dnsdun.com');
		}

		if (!defined('DNSDUN_PORT')) {
			define('DNSDUN_PORT', 80);
		}

		if (!defined('DNSDUN_HTTP')) {
			define('DNSDUN_HTTP', 'http://');
		}

		if (!defined('DNSDUN_APIDIR')) {
			define('DNSDUN_APIDIR', '/');
		}

		$this->http = DNSDUN_HTTP;
		$this->apidir = DNSDUN_APIDIR;
		$this->port = DNSDUN_PORT;
		$this->host = DNSDUN_DOMAIN;
	}

	private function getList()
	{
		$obj = new Curl($this->getInterface('list'));
		$param = 'domain=' . $this->domain . '&domain_key=' . $this->domainkey . '&offset=0&length=9999';
		$obj->init();
		$obj->addParam(CURLOPT_POSTFIELDS, $param);
		$ret = $obj->call();
		if (is_array($ret) && $ret['status']['code'] == '1') {
			return $ret['records'];
		}

		setLastError($ret['status']['message'] . ' ' . $obj->getError());
		return false;
	}

	public function ifset($name, $value, $type = 'A')
	{
		$rows = $this->getList();

		if ($rows === false) {
			return false;
		}

		foreach ($rows as $row) {
			if ($row['name'] == $name && $row['value'] == $value && $row['type'] == $type) {
				return true;
			}
		}

		return false;
	}

	public function addRecord($name, $value, $view = '默认', $type = 'A', $ttl = 600)
	{
		if ($this->ifset($name, $value, $type)) {
			return true;
		}

		$obj = new Curl($this->getInterface('add'));
		$param = 'domain=' . $this->domain . '&domain_key=' . $this->domainkey . '&sub_domain=' . $name . '&record_type=' . $type . '&value=' . $value . '&record_line=' . $view . '&ttl=' . $ttl;
		$obj->init();
		$obj->addParam(CURLOPT_POSTFIELDS, $param);
		$ret = $obj->call();
		if (is_array($ret) && $ret['status']['code'] == '1') {
			return $ret['record']['id'];
		}

		setLastError($ret['status']['message'] . ' ' . $obj->getError());
		return false;
	}

	private function getInterface($a, $c = 'record')
	{
		return $this->http . $this->host . $this->apidir . 'index.php?c=' . $c . '&a=' . $a;
	}

	public function delRecord($recordid)
	{
		$obj = new Curl($this->getInterface('del'));
		$param = 'domain=' . $this->domain . '&domain_key=' . $this->domainkey . '&record_id=' . $recordid;
		$obj->init();
		$obj->addParam(CURLOPT_POSTFIELDS, $param);
		$ret = $obj->call();
		if (is_array($ret) && $ret['status']['code'] == '1') {
			return $ret['record']['id'];
		}

		setLastError($ret['status']['message'] . ' ' . $obj->getError());
		return false;
	}

	public function test()
	{
		$obj = new Curl($this->getInterface('test'));
		$param = 'domain=' . $this->domain . '&domain_key=' . $this->domainkey;
		$obj->init();
		$obj->addParam(CURLOPT_POSTFIELDS, $param);
		$ret = $obj->call();
		if (is_array($ret) && $ret['status']['code'] == '1') {
			return true;
		}

		setLastError($obj->getError());
		return false;
	}

	/**
	 * @deprecated
	 * ep没有更新记录。只有添加和删除
	 * @param unknown $recordid
	 * @param unknown $name
	 * @param unknown $type
	 * @param unknown $value
	 * @param unknown $view
	 * @param unknown $ttl
	 * @return boolean
	 */
	public function updateRecord($recordid, $name, $type, $value, $view, $ttl)
	{
		$obj = new Curl($this->getInterface('modify'));
		$param = 'domain=' . $this->domain . '&domain_key=' . $this->domainkey . '&sub_domain=' . $name . '&record_type=' . $type . '&value=' . $value . '&record_line=' . $view . '&ttl=' . $ttl;
		$param .= '&record_id=' . $recordid;
		$obj->init();
		$obj->addParam(CURLOPT_POSTFIELDS, $param);
		$ret = $obj->call();
		if (is_array($ret) && $ret['status']['code'] == '1') {
			return true;
		}

		setLastError($ret['status']['message'] . ' ' . $obj->getError());
		return false;
	}
}


?>