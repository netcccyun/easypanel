<?php
class WhmCall
{
	public $multi_param = false;
	private $url = '';
	private $callName = '';
	private $package = '';
	private $c = false;
	private $params = array();

	public function __construct($package, $callName)
	{
		$this->package = $package;
		$this->callName = $callName;
	}

	public function addParam($name, $value)
	{
		if ($name == 'c') {
			$this->c = true;
		}

		if ($this->multi_param) {
			if ($this->url != '') {
				$this->url .= '&';
			}

			$this->url .= $name . '=' . urlencode($value);
			return NULL;
		}

		$this->params[$name] = $value;
	}

	public function getCallName()
	{
		return $this->callName;
	}

	public function buildUrl()
	{
		return $this->package . '?whm_call=' . $this->callName;
	}

	public function buildEpanelUrl($skey)
	{
		$r = rand();
		$src = $this->callName . $skey . $r;
		$s = md5($src);
		$url = $this->package . '?a=' . $this->callName . '&r=' . $r . '&s=' . $s;

		if (!$this->c) {
			$url .= '&c=whm';
		}

		return $url;
	}

	public function buildPostData()
	{
		$str = $this->url;

		foreach ($this->params as $name => $value) {
			if ($str != '') {
				$str .= '&';
			}

			$str .= $name . '=' . urlencode($value);
		}

		return $str;
	}
}

class WhmResult
{
	public $status = '';
	private $result = array();

	public function add($name, $value)
	{
		$this->result[$name][] = $value;
	}

	public function getJson($name, $index = 0)
	{
		$str = $this->get($name, $index);
		return json_decode($str, true);
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

	public function getResult()
	{
		return $this->result;
	}

	public function setResult($result)
	{
		$this->result = $result;
	}

	private function stdClassToArray($arr)
	{
		foreach ($arr as $a) {
			$ar[] = (array) $a;
		}

		return $ar;
	}
}

class WhmClient
{
	public $auth = '';
	public $whm_url = '';
	public $err_msg = '';
	private $result;

	public function setSecurityKey($skey)
	{
		$this->auth = $skey;
	}

	public function setAuth($user, $password)
	{
		$this->auth = 'Basic ' . base64_encode($user . ':' . $password);
	}

	public function setUrl($url)
	{
		$this->whm_url = $url;
	}

	public function callEpanel(WhmCall $call, $tmo = 0)
	{
		$this->result = array();
		$opts = array(
			'http' => array('method' => WHM_CALL_METHOD)
			);

		if (WHM_CALL_METHOD == 'POST') {
			$opts['http']['content'] = $call->buildPostData();
		}

		if (0 < $tmo) {
			$opts['http']['timeout'] = $tmo;
		}

		$url = $this->whm_url . $call->buildEpanelUrl($this->auth);
		return $this->callWhm($call, $url, $opts);
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
		return $this->callWhm($call, $url, $opts);
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

	private function callWhm(WhmCall $call, $url, $opts)
	{
		if (WHM_CALL_METHOD != 'POST') {
			$url .= '&' . $call->buildPostData();
		}

		$msg = @file_get_contents($url, false, stream_context_create($opts));

		if ($msg === FALSE) {
			$this->err_msg = 'cann\'t connect to host' . $url . "\n";
			setLastError($this->err_msg);
			return false;
		}

		try {
			$xml = new SimpleXMLElement($msg);
		}
		catch (Exception $e) {
			try {
				$msg = mb_convert_encoding($msg, 'UTF-8', 'GBK');
				$xml = new SimpleXMLElement($msg);
			}
			catch (Exception $e) {
				setLastError('callwhm error msg =' . $msg);
				return null;
			}
		}

		$result = new WhmResult();

		foreach ($xml->children() as $child) {
			if ($child->getName() == 'result') {
				$result->status = $child['status'];

				foreach ($child->children() as $node) {
					$result->add($node->getName(), $node[0]);
				}

				break;
			}
		}

		return $result;
	}
}

function parseWhmNode($result_node, $level)
{
	if (100 < $level) {
		return false;
	}

	$result = new WhmValue();
	$result->setName($result_node->nodeName);
	$nodes = $result_node->childNodes;
	$i = 0;

	while ($i < $nodes->length) {
		$node = $nodes->item($i);

		if ($node->nodeType != 1) {
			continue;
		}

		if (!$node) {
			break;
		}

		$cn = $node->childNodes;
		if ($cn && (1 < $cn->length || $cn->length == 1 && $cn->item(0)->nodeType != 3)) {
			$value = parseWhmNode($node, $level + 1);
		}
		else {
			$value = new WhmValue();
			$value->setCharacter($node->nodeValue);
			$value->setName($node->nodeName);
		}

		$value->addDomAttribute($node->attributes);
		$result->add($value);
		++$i;
	}

	return $result;
}

define('WHM_CALL_METHOD', 'POST');
class WhmValue extends ArrayObject
{
	private $name;
	private $childs = array();

	public function children()
	{
		return $this->childs;
	}

	public function add($value)
	{
		$this->childs[] = $value;
	}

	public function offsetGet($name)
	{
		return $this->childs[$name];
	}

	public function count()
	{
		return count($this->childs);
	}

	public function setCharacter($character)
	{
		$this->childs[] = $character;
	}

	public function addDomAttribute($dom_attr)
	{
		$i = 0;

		while ($i < $dom_attr->length) {
			$node = $dom_attr->item($i);
			$this->childs[$node->name] = $node->value;
			++$i;
		}
	}

	public function __toString()
	{
		return (string) $this->childs[0];
	}

	public function getName()
	{
		return $this->name;
	}

	public function setName($name)
	{
		$this->name = $name;
	}
}

?>