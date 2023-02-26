<?php
class Curl
{
	private $port;
	private $url;
	private $post;
	private $param;
	private $timeout;
	private $obj;
	private $error;
	private $result;

	public function __construct($url, $port = 80, $post = 1, $timeout = 15)
	{
		$this->url = $url;
		$this->port = $port;
		$this->post = $post;
		$this->timeout = $timeout;
	}

	public function getObj()
	{
		return $this->obj;
	}

	public function addParam($key, $value)
	{
		curl_setopt($this->obj, $key, $value);
	}

	public function init()
	{
		$this->obj = curl_init($this->url);
		curl_setopt($this->obj, CURLOPT_URL, $this->url);
		curl_setopt($this->obj, CURLOPT_PORT, $this->port);

		if ($this->post) {
			curl_setopt($this->obj, CURLOPT_POST, $this->post);
		}

		curl_setopt($this->obj, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($this->obj, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($this->obj, CURLOPT_RETURNTRANSFER, 1);

		if ($this->param) {
			curl_setopt($this->obj, CURLOPT_POSTFIELDS, $this->param);
		}
	}

	public function getError()
	{
		return $this->error;
	}

	public function call()
	{
		$this->result = curl_exec($this->obj);
		$this->error = curl_error($this->obj);
		curl_close($this->obj);
		return json_decode($this->result, true);
	}
}


?>