<?php
needRole('admin');
define(GZIPNAME, 'gzip');
class ResponseControl extends control
{
	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
	}

	public function gzipFrom()
	{
		return $this->_tpl->fetch('response/gzip.html');
	}

	public function addGzip()
	{
		$val = $gzip = $_REQUEST['gzip'] ? $_REQUEST['gzip'] : false;

		if ($gzip == false) {
			exit('增加失败');
		}

		$vhost = getRole('vhost');
		$arr['action'] = 'continue';
		$arr['name'] = GZIPNAME;
		$header = 'Content-Type';
		$models['acl_header'] = array('header' => $header, 'val' => $val, 'regex' => 1);
		$models['mark_response_flag'] = array('flagvalue' => 'gzip');
		$access = new Access(null, 'response');

		if (!$access->findChain('BEGIN', GZIPNAME)) {
			if ($access->addChain('BEGIN', $arr, $models)) {
				exit('增加成功');
			}

			exit('增加失败');
		}

		exit('该功能已经存在，不可重复添加');
	}

	public function delGzip()
	{
		$access = new Access(null, 'response');

		if (!$access->delChainByName('BEGIN', GZIPNAME)) {
			exit('删除失败');
		}

		exit('删除成功');
	}
}

?>