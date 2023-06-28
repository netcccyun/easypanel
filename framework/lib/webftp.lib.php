<?php
class FileAccess
{
	private $vhost;
	private $doc_root;
	private $pdo;

	public function __construct($is_write = true)
	{
		$user = $GLOBALS['user'];
		$this->doc_root = $user['doc_root'];

		if (!$this->doc_root) {
			exit('doc_root is missing');
		}

		$this->vhost = $user['name'];
		load_lib('pub:access');
		$db = $this->doc_root . '/access.xml.db';
		$exsit = file_exists($db);
		if (!$exsit && !$is_write) {
			$this->pdo = null;
			return NULL;
		}

		$dsn = 'sqlite:' . $db;
		$this->pdo = new PDO($dsn);

		if (!$exsit) {
			$this->init();
		}
	}

	public function list_files()
	{
		if ($this->pdo == null) {
			return null;
		}

		$sql = 'SELECT file,action FROM file_access';
		$result = $this->pdo->query($sql);

		if (!$result) {
			return null;
		}

		$rows = $result->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$files[$row['file']] = $row['action'];
		}

		return $files;
	}

	public function add($file, $is_dir, $action)
	{
		$this->del($file);
		$sql = 'INSERT INTO file_access (file,is_dir,action) VALUES (\'' . $file . '\',' . intval($is_dir) . ',\'' . $action . '\')';
		$result = $this->pdo->exec($sql);
		$this->sync_access($is_dir, $action);
		return $result;
	}

	public function del($file)
	{
		$sql = 'SELECT is_dir,action FROM file_access WHERE file=\'' . $file . '\'';
		$result = $this->pdo->query($sql);

		if (!$result) {
			return false;
		}

		$rs = $result->fetch();

		if (!$rs) {
			return false;
		}

		$sql = 'DELETE FROM file_access WHERE file=\'' . $file . '\'';
		$result = $this->pdo->exec($sql);
		$this->sync_access($rs['is_dir'], $rs['action']);
		return $result;
	}

	public function sync_access($is_dir, $action)
	{
		$sql = 'SELECT file FROM file_access WHERE is_dir=' . $is_dir . ' AND action=\'' . $action . '\'';
		$result = $this->pdo->query($sql);
		$chain_name = $action . $is_dir;
		$access = new Access($this->vhost, 'response');

		if (!$result) {
			$access->delChainByName(FILE_ACCESS_TABLE, $chain_name);
			return false;
		}

		$rows = $result->fetchAll(PDO::FETCH_ASSOC);

		if (count($rows) == 0) {
			$access->delChainByName(FILE_ACCESS_TABLE, $chain_name);
			return true;
		}

		$arr = array('name' => $chain_name);
		$file = '';

		foreach ($rows as $row) {
			$file .= '|' . $this->doc_root . $row['file'];
		}

		if ($is_dir) {
			$models['acl_dir'] = array('v' => $file);
		}
		else {
			$models['acl_file'] = array('v' => $file);
		}

		if ($action == 'deny') {
			$arr['action'] = 'deny';
		}
		else if ($action == 'static') {
			$arr['action'] = 'continue';
			$models['mark_extend_flag'] = array('no_extend' => 1);
		}
		else if (strncasecmp($action, 'auth:', 5) == 0) {
			$arr['action'] = 'continue';
			$require = substr($action, 5);

			if (0 < strlen($require)) {
				$require = '~' . $require;
			}
			else {
				$require = '*';
			}

			$models['mark_auth'] = array('realm' => 'kangle', 'file' => apicall('httpauth', 'getFileName', array($this->vhost)), 'failed_deny' => 0, 'require' => $require, 'auth_type' => 'Basic', 'crypt_type' => 'smd5');
			$models['mark_cache_control'] = array('must_revalidate' => 1);
		}
		else if (strncasecmp($action, 'ip:', 3) == 0) {
			$arr['action'] = 'deny';
			$ips = explode(',', substr($action, 3));

			foreach ($ips as $ip) {
				$models['acl_src'][] = array('ip' => $ip, 'revers' => 1);
			}

			$models['mark_cache_control'] = array('must_revalidate' => 1);
		}
		else {
			return false;
		}

		return $access->editChain(FILE_ACCESS_TABLE, $arr, $models);
	}

	public function sync()
	{
		$this->init_access();
		$sql = 'SELECT is_dir,action FROM file_access group by is_dir,action';
		$result = $this->pdo->query($sql);

		if (is_object($result)) {
			$actions = $result->fetchAll(PDO::FETCH_ASSOC);

			foreach ($actions as $action) {
				$this->sync_access($action['is_dir'], $action['action']);
			}
		}
	}

	private function init()
	{
		$this->init_access();
		$init_sql = array("CREATE TABLE [file_access] (\r\n\t\t\t\t[file] text  NULL,\r\n\t\t\t\t[is_dir] INTEGER  NULL,\r\n\t\t\t\t[action] text  NULL,\r\n\t\t\t\tPRIMARY KEY ([file])\r\n\t\t);");

		foreach ($init_sql as $sql) {
			$this->pdo->exec($sql);
		}

		return true;
	}

	private function init_access()
	{
		$access = new Access($this->vhost, 'response');
		$access->addTable('POSTMAP');
		$access->addTable(FILE_ACCESS_TABLE);
		$access->emptyTable(FILE_ACCESS_TABLE);
		$access->editChain('POSTMAP', array('action' => 'table:' . FILE_ACCESS_TABLE, 'name' => 'file_access'));
	}
}

function mycopydir($src, $dst, &$count)
{
	$rd = opendir($src);

	if (!$rd) {
		return false;
	}

	while (($file = readdir($rd)) !== false) {
		if ($file == '.' || $file == '..') {
			continue;
		}

		$src_file = $src . '/' . $file;

		if (is_dir($src_file)) {
			@mkdir($dst . '/' . $file);
			mycopydir($src_file, $dst . '/' . $file, $count);
		}
		else {
			++$count;
			copy($src_file, $dst . '/' . $file);
		}
	}

	closedir($rd);
	++$count;
	return true;
}

function myrmdir($dir, &$count)
{
	$rd = opendir($dir);

	if (!$rd) {
		return false;
	}

	while (($file = readdir($rd)) !== false) {
		if ($file == '.' || $file == '..') {
			continue;
		}

		$file = $dir . '/' . $file;

		if (is_dir($file)) {
			myrmdir($file, $count);
		}
		else {
			++$count;
			unlink($file);
		}
	}

	closedir($rd);
	++$count;
	rmdir($dir);
	return true;
}

function toutf8($str, $encoding = null)
{
	if($encoding == 'gbk'){
		return mb_convert_encoding($str, 'UTF-8', 'GBK');
	}
	return $str;
}

function tolocal($str, $encoding = null)
{
	if($encoding == 'gbk'){
		return mb_convert_encoding($str, 'GBK', 'UTF-8');
	}
	return $str;
}

function getfileicon($file)
{
	$exts = [
		'exe' => ['exe', 'com', 'apk', 'ipa', 'dmg', 'deb', 'rpm'],
		'zip' => ['zip', '7z', 'tar', 'gz', 'tgz', 'bz2', 'rar', 'iso', 'cab', 'xz'],
		'txt' => ['txt', 'ini', 'conf', 'md', 'log'],
		'image' => ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'ico', 'tiff', 'tif', 'webp', 'heic']
	];
	$ext = strrchr($file, '.');

	if (!$ext) {
		return 'unknown';
	}

	$ext = strtolower(substr($ext, 1));
	foreach($exts as $key=>$value){
		if(in_array($ext, $value)){
			$file_type = $key;
		}
	}

	if (!$file_type) {
		return 'unknown';
	}

	return $file_type;
}

function ls($dir, $cwd, $file_access = null, $encoding = null)
{
	$rd = @opendir($dir);

	if (!$rd) {
		return false;
	}

	$dirs = array();
	$files = array();

	while (($file = readdir($rd)) !== false) {
		if ($file == '.' || $file == '..') {
			continue;
		}

		$info = @stat($dir . '/' . $file);

		if ($info[2] & S_IFDIR) {
			$type = 'folder';
		}
		else {
			$type = getfileicon($file);
		}

		$filename = toutf8($file, $encoding);
		$propty = '';

		if ($file_access) {
			$propty = $file_access[$cwd . '/' . $filename];
		}

		$file_info = array('codename' => rawurlencode($filename), 'filename' => $filename, 'info' => $info, 'dir' => $info[2] & S_IFDIR, 'type' => $type, 'propty' => $propty);

		if ($info[2] & S_IFDIR) {
			$test_file = $dir . '/' . $file . '/easypanel_test_for_write';
			$fp = @fopen($test_file, 'a+');

			if ($fp) {
				$file_info['writable'] = 1;
				fclose($fp);
			}
			else {
				$file_info['writable'] = 0;
			}

			@unlink($test_file);
			$dirs[] = $file_info;
		}
		else {
			$fp = @fopen($dir . '/' . $file, 'a+');

			if ($fp) {
				$file_info['writable'] = 1;
				fclose($fp);
			}
			else {
				$file_info['writable'] = 0;
			}

			$files[] = $file_info;
		}
	}

	closedir($rd);
	return array_merge($dirs, $files);
}

function splitdir($dir)
{
	$dirs = preg_split('/[\\/\\\\]+/', $dir);
	$ndir = array();

	foreach ($dirs as $dir) {
		if ($dir == '') {
			continue;
		}

		if (strstr($dir, '..')) {
			@array_pop($ndir);
			continue;
		}

		@array_push($ndir, $dir);
	}

	return $ndir;
}

function unescape($str)
{
	preg_match_all('/%u.{4}|&#x.{4};|&#\\d+;|.+/sU', $str, $r);
	$ar = $r[0];

	foreach ($ar as $k => $v) {
		if (substr($v, 0, 2) == '%u') {
			$ar[$k] = mb_convert_encoding(pack('H4', substr($v, 0 - 4)), 'UTF-8', 'UCS-2');
		}
		else if (substr($v, 0, 3) == '&#x') {
			$ar[$k] = mb_convert_encoding(pack('H4', substr($v, 3, 0 - 1)), 'UTF-8', 'UCS-2');
		}
		else {
			if (substr($v, 0, 2) == '&#') {
				$ar[$k] = mb_convert_encoding(pack('n', substr($v, 2, 0 - 1)), 'UTF-8', 'UCS-2');
			}
		}
	}

	return join('', $ar);
}

function trimdir($dir)
{
	$ndir = splitdir($dir);
	$str = '';

	foreach ($ndir as $dir) {
		$str .= '/' . $dir;
	}

	return $str;
}

function addClip($clip)
{
	$_SESSION['webftp_clip'] = $clip;
}

function getClip()
{
	$clip = $_SESSION['webftp_clip'];
	$_SESSION['webftp_clip'] = '';
	return $clip;
}

function my_copy_upfile($src, $dest)
{
	change_to_super();
	$fp = fopen($src, 'rb');
	change_to_user($_SESSION['webftp_user'], $_SESSION['webftp_group']);

	if (!$fp) {
		return false;
	}

	$fp2 = fopen($dest, 'wb');

	if (!$fp2) {
		fclose($fp);
		return false;
	}

	while (true) {
		$str = fread($fp, 8192);

		if ($str == FALSE) {
			break;
		}

		if (fwrite($fp2, $str) == FALSE) {
			break;
		}
	}

	fclose($fp2);
	fclose($fp);
	return true;
}

define(S_IFDIR, 16384);
define(S_IWRITE, 512);
define(FILE_ACCESS_TABLE, '!file_access');

?>