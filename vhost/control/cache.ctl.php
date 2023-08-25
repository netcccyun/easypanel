<?php
needRole('vhost');
define('BEGIN', 'BEGIN');
define('CACHE_TABLE', '!cache_control');
define('ACTION', 'table:!cache_control');
class CacheControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'), 'response');
	}

	private function cacheGetChain(&$file, &$url, &$header)
	{
		$this->cacheAddTable();
		$result = $this->access->listChain(CACHE_TABLE, 1);
		$id = 0;

		foreach ($result->children() as $k => $chain) {
			foreach ($chain->children() as $key => $ch) {
				if (strstr($key, 'acl_')) {
					$header[$id]['name'] = substr($key, 4);
					$header[$id]['val'] = (string) $ch;
					$header[$id]['id'] = $id;
				}

				if (strstr($key, 'mark_')) {
					$header[$id]['max_age'] = (string) $ch['max_age'];
					$header[$id]['static'] = (string) $ch['static'];
				}
			}

			++$id;
		}
	}

	public function cacheFrom()
	{
		$this->cacheGetChain($file, $url, $header);
		if ($file && 0 < count($file)) {
			$this->_tpl->assign('file', $file);
		}

		if ($url && 0 < count($url)) {
			$this->_tpl->assign('url', $url);
		}

		if ($header && 0 < count($header)) {
			$this->_tpl->assign('header', $header);
		}

		if ($this->access->findChain(BEGIN, CACHE_TABLE)) {
			$this->_tpl->assign('at', 1);
		}

		return $this->_tpl->fetch('cache/cachefrom.html');
	}

	/**
	 * ajax
	 * Enter description here ...
	 */
	public function cacheAdd()
	{
		$mode = $_REQUEST['mode'];
		$cache_value = trim($_REQUEST['cache_value']);

		if ($cache_value == '') {
			exit('值不能为空');
		}

		switch ($mode) {
		case 'content-type':
			$mode = 'header';
			$acl_mode = array('header' => 'content-Type', 'val' => $cache_value, 'regex' => 1);
			break;

		case 'url':
			$mode = 'url';
			$acl_mode = array('url' => $cache_value);
			break;

		case 'file_ext':
			$mode = 'file_ext';
			$acl_mode = array('v' => $cache_value);
			break;

		default:
			exit('error');
		}

		$max_age = trim($_REQUEST['max_age']);
		$arr['action'] = 'continue';
		$modeles['acl_' . $mode] = $acl_mode;
		$modeles['mark_cache_control'] = array('max_age' => $max_age, 'static' => $_REQUEST['static']);

		if ($this->access->addChain(CACHE_TABLE, $arr, $modeles)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('失败');
	}

	public function cacheCheckOn()
	{
		$status = intval($_REQUEST['status']);

		if ($status == 2) {
			$this->access->delChainByName(BEGIN, CACHE_TABLE);
			exit('成功');
		}

		$this->cacheAddChain();
		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function cacheDel()
	{
		$id = intval($_REQUEST['id']);

		if ($this->access->delChain(CACHE_TABLE, $id)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('删除失败');
	}

	/**
	 * 在BEGIN表里启用,增加链
	 * Enter description here ...
	 */
	private function cacheAddChain()
	{
		if ($this->access->findChain(BEGIN, CACHE_TABLE)) {
			return true;
		}

		$arr = array('action' => ACTION, 'name' => CACHE_TABLE);
		$this->access->addChain(BEGIN, $arr);
	}

	/**
	 * 创建表
	 * Enter description here ...
	 */
	private function cacheAddTable()
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == CACHE_TABLE) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$this->access->addTable(CACHE_TABLE)) {
				return $this->show_msg('不能增加表');
			}
		}
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>