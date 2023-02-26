<?php
class WhmdomCall
{
	private $callName = '';
	private $package = '';
	private $params = array();

	public function WhmCall($package, $callName)
	{
		$this->package = $package;
		$this->callName = $callName;
	}

	public function addParam($name, $value)
	{
		$this->params[$name] = $value;
	}

	public function getCallName()
	{
		return $this->callName;
	}

	public function buildUrl()
	{
		$url = $this->package . '?whm_call=' . $this->callName;
		return $url;
	}

	public function buildPostData()
	{
		$url = '';

		foreach ($this->params as $name => $val) {
			if ($url != '') {
				$url .= '&';
			}

			$url .= $name . '=' . urlencode($val);
		}

		return $url;
	}
}

class WhmdomResult
{
	public $status = '';
	private $result = array();

	public function add($name, $value)
	{
		$this->result[$name][] = $value;
	}

	public function get($name, $index = 0)
	{
		$value = $this->result[$name];
		return $value[$index];
	}

	public function getAll($name)
	{
		return $this->result[$name];
	}

	public function getCode()
	{
		return intval($this->status);
	}
}

class WhmdomClient
{
	public $auth = '';
	public $whm_url = '';
	public $err_msg = '';
	private $result;

	public function setAuth($user, $password)
	{
		$this->auth = 'Basic ' . base64_encode($user . ':' . $password);
	}

	public function setUrl($url)
	{
		$this->whm_url = $url;
	}

	public function call(WhmCall $call, $tmo = 0)
	{
		$this->result = array();
		$opts = array(
			'http' => array('method' => WHM_CALL_METHOD, 'header' => 'Authorization: ' . $this->auth . "\r\n")
			);

		if (WHM_CALL_METHOD == 'POST') {
			$opts['http']['content'] = $call->buildPostData();
		}

		if (0 < $tmo) {
			$opts['http']['timeout'] = $tmo;
		}

		$url = $this->whm_url . $call->buildUrl();

		if (WHM_CALL_METHOD != 'POST') {
			$url .= '&' . $call->buildPostData();
		}

		$msg = @file_get_contents($url, false, stream_context_create($opts));

		if ($msg === FALSE) {
			$this->err_msg = 'cann\'t connect to host';
			return false;
		}

		$whm = new DOMDocument();

		if (!$whm->loadXML($msg)) {
			$this->err_msg = 'cann\'t parse whm xml';
			return false;
		}

		$result_node = $whm->getElementsByTagName('result')->item(0);
		$status = $result_node->attributes->getNamedItem('status')->nodeValue;
		$nodes = $result_node->childNodes;
		$result = new WhmResult();
		$result->status = $status;
		$i = 0;

		while (true) {
			$node = $nodes->item($i);

			if (!$node) {
				break;
			}

			if ($node->nodeType != 1) {
				continue;
			}

			$result->add($node->nodeName, $node->nodeValue);
			++$i;
		}

		return $result;
	}

	public function get($name, $index = 0)
	{
		$value = $this->result[$name];

		if (!$value) {
			return false;
		}

		if (count($value) <= $index) {
			return false;
		}

		return $value[$index];
	}

	public function setParam($name, $value)
	{
	}

	public function getLastError()
	{
		return $this->err_msg;
	}
}

define(WHM_CALL_METHOD, 'GET');

?>