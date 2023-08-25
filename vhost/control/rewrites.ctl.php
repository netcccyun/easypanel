<?php
needRole('vhost');
class RewritesControl extends Control
{
	private $paths = array();

	public function __construct()
	{
		parent::__construct();
	}

	private function getPathList($vhost)
	{
		$list = daocall('vhostinfo', 'getDomain', array($vhost));
		
		$i = 0;
		$paths = array();
		foreach ($list as $domain) {
			$path = '/' . trim($domain['value'], '/');
			if(!in_array($path,$paths)){
				$paths[$i++] = $path;
			}
		}
		if(count($paths)==0) $paths[$i] = '/wwwroot';
		return $paths;
	}

	public function show()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		if ($user['cdn'])exit("not support");

		$id = isset($_GET['id'])?intval($_GET['id']):0;
		$paths = $this->getPathList($vhost);
		if($paths[$id]){
			if($paths[$id] == '/'){
				$file = $user['doc_root'] . '/.htaccess';
			}else{
				$file = $user['doc_root'] . $paths[$id] . '/.htaccess';
			}

			change_to_user($user['uid'], $user['gid']);
			if (file_exists($file)) {
				$content = file_get_contents($file);
				$this->_tpl->assign('content', $content);
			}
			change_to_super();
		}

		$rules = $this->getRuleList();

		$this->assign('paths', $paths);
		$this->assign('rules', $rules);
		$this->assign('pathid', $id);
		return $this->_tpl->fetch('rewrite.html');
	}

	public function edit()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		if ($user['cdn'])exit("not support");

		$id = isset($_POST['id'])?intval($_POST['id']):exit('请选择目录');
		$content = isset($_POST['content'])?$_POST['content']:exit('请填写伪静态规则');
		$paths = $this->getPathList($vhost);
		if($paths[$id]){
			if($paths[$id] == '/'){
				$dir = $user['doc_root'];
			}else{
				$dir = $user['doc_root'] . $paths[$id];
			}

			change_to_user($user['uid'], $user['gid']);
			if(is_dir($dir)){
				$file = $dir . '/.htaccess';
				if(empty($content)){
					unlink($file);
				}else{
					file_put_contents($file, $content);
				}
			}
			change_to_super();
		}

		exit('成功');
	}

	public function del()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		if ($user['cdn'])exit("not support");

		$id = isset($_POST['id'])?intval($_POST['id']):exit('请选择目录');
		$paths = $this->getPathList($vhost);
		if($paths[$id]){
			if($paths[$id] == '/'){
				$file = $user['doc_root'] . '/.htaccess';
			}else{
				$file = $user['doc_root'] . $paths[$id] . '/.htaccess';
			}

			change_to_user($user['uid'], $user['gid']);
			if (file_exists($file)) {
				unlink($file);
			}
			change_to_super();
		}

		exit('成功');
	}

	public function getRule()
	{
		$name = trim($_REQUEST['name']);
		if($name == '0')exit('{"code":-1}');

		if (!preg_match('/^[a-zA-Z0-9]+$/',$name)) exit('{"code":-1}');

		$file = dirname(__FILE__) . '/rewrite/'.$name.'.conf';

		if(file_exists($file)){
			$content = file_get_contents($file);
			$result = ['code'=>0, 'content'=>$content];
			echo json_encode($result);
			exit;
		}else{
			$result = ['code'=>0, 'content'=>''];
			echo json_encode($result);
			exit;
		}
	}

	private function getRuleList()
	{
		$dir = dirname(__FILE__) . '/rewrite/';
		$opdir = opendir($dir);

		if (!$opdir) {
			return false;
		}
		$rules = array();

		while (($file = readdir($opdir)) !== false) {

			if ($file == '.' || $file == '..') {
				continue;
			}

			if (is_dir($dir . $file) || !strstr($file, '.conf')) {
				continue;
			}

			$file = substr($file, 0, strpos($file,'.'));
			$rules[$file] = $file;
		}
		sort($rules);

		return $rules;
	}

}

?>