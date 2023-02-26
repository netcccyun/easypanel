<?php
needRole('vhost');
define('BEGIN', 'BEGIN');
define('TABLENAME', '!rewrite');
define('ACTION', 'table:!rewrite');
class RewriteControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function rewriteFrom()
	{
		$this->rewriteAddTable();
		$result = $this->access->listChain(TABLENAME);

		if ($result) {
			$id = 0;

			foreach ($result->children() as $chain) {
				$res[$id] = array();

				foreach ($chain->children() as $name=>$ch) {
					if($name == 'acl_host'){
						$res[$id]['host'] = (string)$ch;
					}elseif($name == 'acl_wide_host'){
						$res[$id]['host'] = (string)$ch['v'];
					}elseif($name == 'mark_rewrite'){
						$res[$id]['dst'] = substr((string) $ch['dst'], 7, 0 - 2);
						$res[$id]['code'] = (string) $ch['code'] . '跳转';
						$res[$id]['id'] = $id;
					}elseif($name == 'mark_host'){
						$res[$id]['dst'] = (string) $ch['host'];
						$res[$id]['code'] = '域名改写';
						$res[$id]['id'] = $id;
					}elseif($name == 'mark_host_rewrite'){
						$res[$id]['dst'] = str_replace('$1','*',$ch['host']);
						$res[$id]['code'] = '域名改写';
						$res[$id]['id'] = $id;
					}
				}

				++$id;
			}

			$this->_tpl->assign('res', $res);
		}

		if ($this->access->findChain(BEGIN, TABLENAME)) {
			$this->_tpl->assign('at', 1);
		}

		return $this->_tpl->fetch('rewrite/rewritefrom.html');
	}

	public function rewriteAdd()
	{
		$src_host = trim($_POST['host']);
		$dst_host = trim($_POST['dst']);
		$code = intval($_POST['code']);
		if ($src_host == '' || $dst_host == '') {
			exit('域名不能为空');
		}

		if (!checkDomain($src_host) && $src_host!='*') {
			exit('域名输入错误');
		}

		if ($code>0 && substr_count($dst_host, '.') < 1 || $code==0 && !checkDomain($dst_host)) {
			exit('跳转域名输入错误');
		}

		if (substr($dst_host, 0, 4) == 'http') {
			$dst = $dst_host . '$1';
		}
		else {
			$dst = 'http://' . $dst_host . '$1';
		}

		$wide = false;
		if(substr($src_host,0,2)=='*.' || $src_host=='*')$wide = true;

		$arr['action'] = 'continue';
		if($code == 0){
			if($wide){
				$modeles['mark_host_rewrite'] = array('reg_host' => str_replace(array('.','*'),array('\\.','(.*)'),$src_host), 'host' => str_replace('*','$1',$dst_host), 'port' => '0', 'life_time' => '0', 'proxy' => '0', 'rewrite' => '1');
			}else{
				$modeles['mark_host'] = array('host' => $dst_host, 'port' => '0', 'rewrite' => '1', 'life_time' => '0');
			}
		}else{
			$modeles['mark_rewrite'] = array('path' => '(.*)', 'dst' => $dst, 'code' => $code, 'nc' => '1', 'internal' => '0', 'qsa' => '1');
		}
		if($wide){
			$modeles['acl_wide_host'] = array('v' => $src_host);
		}else{
			$modeles['acl_host'] = array('v' => $src_host);
		}

		if ($this->access->addChain(TABLENAME, $arr, $modeles)) {
			exit('成功');
		}

		exit('添加失败');
	}

	public function rewriteDel()
	{
		$id = intval($_REQUEST['id']);

		if ($this->access->delChain(TABLENAME, $id)) {
			exit('成功');
		}

		exit('删除失败');
	}

	public function rewriteCheckOn()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case '2':
			$this->access->delChainByName(BEGIN, TABLENAME);
			break;

		case '1':
			$find_result = $this->access->findChain('BEGIN', '!ssl_rewrite');
			if($find_result){
				$this->access->delChainByName('BEGIN', '!ssl_rewrite');
			}

			$arr = array('action' => ACTION, 'name' => TABLENAME);
			$this->access->addChain(BEGIN, $arr);

			if($find_result){
				$arr['action'] = 'continue';
				$arr['name'] = '!ssl_rewrite';
				$models['mark_url_rewrite'] = array('url' => '^http://(.*)$', 'dst' => 'https://$1', 'nc' => '1', 'code' => '301');
				$this->access->addChain('BEGIN', $arr, $models);
			}
			break;

		default:
			break;
		}

		header('Location:?c=rewrite&a=rewriteFrom');
		exit();
	}

	private function rewriteAddTable()
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == TABLENAME) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$this->access->addTable(TABLENAME)) {
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