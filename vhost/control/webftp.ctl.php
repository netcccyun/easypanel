<?php
@set_time_limit(0);
needRole('vhost');
class WebftpControl extends Control
{
	public function __construct()
	{
		if ($_SESSION['webftp_user'] == '') {
			exit('please login webftp first');
		}

		change_to_user($_SESSION['webftp_user'], $_SESSION['webftp_group']);
		load_lib('pub:webftp');
		parent::__construct();
	}

	/**
	 * 文件编辑
	 * Enter description here ...
	 */
	public function editfrom()
	{
		$json['code'] = 400;
		$filext = strtolower(substr($_REQUEST['file'], strripos($_REQUEST['file'], '.') + 1));
		$array = array('001', '002', '003', '004', '005', '006', 'exe', 'msi', 'db', 'png', 'jpg', 'zip', '7z', 'bmp', 'gif', 'mov', 'wmv', 'rmvb', 'mpeg', 'avi', 'mp3', 'mp4', 'rm', 'dat', 'asf', 'flv', '3gp', 'divx', 'wmv', 'rar', 'zip', 'cab', 'jar', 'iso', 'gz', 'tar', 'bz2', 'ace', 'arj');

		if (in_array($filext, $array)) {
			$json['msg'] = '此类文件不能打开' . $filext;
			exit(json_encode($json));
		}

		$file = $this->getphyfile($_REQUEST['file']);
		$fp = fopen($file, 'rb');

		if (!$fp) {
			$json['msg'] = '不能打开文件:' . $file;
			exit(json_encode($json));
		}

		$content = @fread($fp, filesize($file));
		fclose($fp);
		$charset = 'UTF-8';

		if (!$this->is_utf8($content)) {
			$content = mb_convert_encoding($content, 'UTF-8', 'GBK');
			$charset = 'GBK';
		}

		$content = @rawurlencode($content);
		$json['code'] = 200;
		$json['content'] = $content;
		$json['charset'] = $charset;
		$json['filename'] = $_REQUEST['file'];
		exit(json_encode($json));
		header('Content-Type: text/xml; charset=' . $charset);
		$str = '<?xml version="1.0" encoding="' . $charset . '"?>';
		$str .= '<result>';
		$str .= '<content>' . $content . '</content>';
		$str .= '<charset>' . $charset . '</charset>';
		$str .= '<filename>' . $_REQUEST['file'] . '</filename>';
		$str .= '</result>';
		exit($str);
	}

	public function editsave()
	{
		$content = $_REQUEST['content'];
		$charset = $_REQUEST['charset'];
		$filename = $_REQUEST['filename'];
		if (!$content || !$charset || !$filename) {
			exit('编辑失败,请联系管理员.');
		}

		$content = unescape($content);

		if (strcasecmp($charset, 'UTF-8') != 0) {
			$content = mb_convert_encoding($content, $charset, 'UTF-8');
		}

		$file = $this->getphyfile($filename);
		$fp = fopen($file, 'wb');

		if (!$fp) {
			exit('不能打开文件:' . $file);
		}

		if (fwrite($fp, $content)) {
			exit('编辑成功');
		}

		fclose($fp);
		exit('编辑失败');
	}

	private function is_utf8($liehuo_net)
	{
		if (preg_match('/^([' . chr(228) . '-' . chr(233) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}){1}/', $liehuo_net) == true || preg_match('/([' . chr(228) . '-' . chr(233) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}[' . chr(128) . '-' . chr(191) . (']' . '{1}){1}$/'), $liehuo_net) == true || preg_match('/([' . chr(228) . '-' . chr(233) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}){2,}/', $liehuo_net) == true) {
			return true;
		}

		return false;
	}

	public function enter()
	{
		$this->setcwd('/');
		return $this->index();
	}

	public function index()
	{
		$cwd = $this->getcwd();
		$cwds = splitdir($cwd);

		$wgetdir = '';
		foreach ($cwds as $dir) {
			$wgetdir = $wgetdir . '/' . $dir;
		}

		$this->assign('wgetdir', $wgetdir);
		$this->assign('cwds', $cwds);
		$this->assign('cwd', $cwd);
		$fa = new FileAccess(false);
		$files = ls($this->getphyfile(''), $this->getcwd(), $fa->list_files());

		if ($files === false) {
			$this->_tpl->assign('msg', '不能打开目录，请检查权限');
		}
		else {
			$dirs = array();
			$newfiles = array();

			foreach ($files as $row) {
				if ($row['dir']) {
					$dirs[] = $row;
				}
				else {
					$newfiles[] = $row;
				}
			}

			sort($dirs);
			sort($newfiles);
			$sortfile = array_merge($dirs, $newfiles);
			$this->assign('files', $sortfile);
		}

		return $this->fetch('webftp/ls.html');
	}

	private function getcwd()
	{
		return $_SESSION['webftp_cwd'];
	}

	private function setcwd($dir)
	{
		$dir = @unescape($dir);

		if ($dir[0] != '/') {
			$dir = $_SESSION['webftp_cwd'] . '/' . $dir;
		}

		$_SESSION['webftp_cwd'] = trimdir($dir);
		return $_SESSION['webftp_cwd'];
	}

	private function getusrfile($dir, $curdir = null)
	{
		$dir = unescape($dir);

		if ($curdir == null) {
			$curdir = $_SESSION['webftp_cwd'];
		}

		if ($dir[0] != '/') {
			$dir = $curdir . '/' . $dir;
		}

		$dir = tolocal(trimdir($dir));

		if ($dir[0] == '/') {
			return $dir;
		}

		return '/' . $dir;
	}

	private function getphyfile($dir, $curdir = null)
	{
		return $_SESSION['webftp_docroot'] . $this->getusrfile($dir, $curdir);
	}

	public function cd()
	{
		$this->setcwd($_REQUEST['file']);
		return $this->index();
	}

	public function getfile()
	{
		$dir = $_REQUEST['dir'];

		if ($dir) {
			return $this->cd();
		}

		return $this->downfile($this->getphyfile($_REQUEST['file']));
	}

	private function downfile($file_name)
	{
		$file = fopen($file_name, 'rb');

		if (!$file) {
			exit('不能下载文件，请联系管理员');
		}

		Header('Content-type:application/octet-stream ');
		Header('Content-Disposition: attachment; filename=' . basename($file_name));

		while (true) {
			$str = fread($file, 8192);

			if ($str == FALSE) {
				break;
			}

			echo $str;
			flush();
		}

		fclose($file);
		exit();
	}

	public function upsave()
	{
		$total_size = 0;
		$total_count = 0;

		foreach ($_FILES as $file) {
			if ($file['tmp_name'] == '') {
				continue;
			}

			if (my_copy_upfile($file['tmp_name'], $this->getphyfile($file['name']))) {
				$total_size += $file['size'];
				++$total_count;
			}
		}

		if (1024 * 1024 * 1024 < $total_size) {
			$size = number_format($total_size / 1024 / 1024 / 1024, 2, '.', '') . 'G';
		}
		else if (1024 * 1024 < $total_size) {
			$size = number_format($total_size / 1024 / 1024, 2, '.', '') . 'M';
		}
		else if (1024 < $total_size) {
			$size = number_format($total_size / 1024, 2, '.', '') . 'KB';
		}
		else {
			$size = $total_size . 'B';
		}

		$this->assign('msg', '成功上传文件数:' . $total_count . ',总大小:' . $size);
		return $this->index();
	}

	public function readonly()
	{
		load_lib('pub:whm');
		$ro = $_REQUEST['ro'];
		$success_count = 0;
		$whmCall = new WhmCall('vhost.whm', 'acl');
		$i = 0;

		if (is_array($_REQUEST['files'])) {
			foreach ($_REQUEST['files'] as $file) {
				if (strncasecmp($file, '/access.xml', strlen('/access.xml')) == 0) {
					continue;
				}

				if (strncasecmp($file, '/logs/', strlen('/logs/')) == 0) {
					continue;
				}

				if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
					if (strncasecmp($file, '/database/', strlen('/database/')) == 0) {
						continue;
					}

					$whmCall->addParam('file' . $i, $file);
				}
				else {
					$filename = $this->getphyfile($file);

					if (is_dir($filename)) {
						if ($ro) {
							chmod($filename, 320);
						}
						else {
							chmod($filename, 493);
						}
					}
					else if ($ro) {
						chmod($filename, 256);
					}
					else {
						chmod($filename, 420);
					}
				}

				++$i;
			}
		}

		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			$whm = apicall('nodes', 'makeWhm', array('localhost'));
			$whmCall->addParam('subdir', '0');
			$whmCall->addParam('vh', getRole('vhost'));

			if ($ro) {
				$ur = 'Administrators:ro,system:ro,' . $GLOBALS['user']['uid'] . ':ro';
			}
			else {
				$ur = '-';
			}

			$whmCall->addParam('ur', $ur);
			$whm->call($whmCall, 60);
		}

		return $this->index();
	}

	public function rm()
	{
		$success_count = 0;

		if (is_array($_REQUEST['files'])) {
			foreach ($_REQUEST['files'] as $file) {
				$filename = $this->getphyfile($file);

				if (is_dir($filename)) {
					myrmdir($filename, $success_count);
				}
				else {
					if (unlink($filename)) {
						++$success_count;
					}
				}
			}
		}

		$this->assign('msg', '成功删除文件/目录数:' . $success_count);
		return $this->index();
	}

	public function mkdir()
	{
		$dir = $this->getphyfile(filterParam($_REQUEST['dir'], 'dir'));

		if (@mkdir($dir)) {
			$this->assign('msg', '成功创建目录');
		}
		else {
			$this->assign('msg', '创建目录失败');
		}

		return $this->index();
	}

	public function copy()
	{
		return $this->clipFiles('copy');
	}

	public function cut()
	{
		return $this->clipFiles('cut');
	}

	private function clipFiles($op)
	{
		$dir = $this->getcwd();

		if ($dir == '') {
			$dir = '/';
		}

		$clip = array('dir' => $dir, 'op' => $op, 'files' => $_REQUEST['files']);
		addClip($clip);
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		$str .= '<result code=\'200\'/>';
		exit($str);
	}

	public function rename()
	{
		$oldname = $this->getphyfile($_REQUEST['oldname']);
		$newname = $this->getphyfile($_REQUEST['newname']);

		if (rename($oldname, $newname)) {
			$this->assign('重命名成功');
		}
		else {
			$this->assign('重命名失败');
		}

		return $this->index();
	}

	public function parse()
	{
		$clip = getClip();
		if ($clip == null || $clip['op'] == '') {
			$this->assign('msg', '没有进行复制或剪切');
			return $this->index();
		}

		$op = $clip['op'];
		$success_count = 0;

		foreach ($clip['files'] as $file) {
			if ($file == '') {
				continue;
			}

			$newfile = $this->getphyfile(basename($file));
			$oldfile = $this->getphyfile($file, $clip['dir']);

			switch ($op) {
			case 'cut':
				if (rename($oldfile, $newfile)) {
					++$success_count;
				}

				break;

			case 'copy':
				if (strncmp($oldfile, $newfile, strlen($oldfile)) == 0) {
					break;
				}

				if (is_dir($oldfile)) {
					@mkdir($newfile);
					mycopydir($oldfile, $newfile, $success_count);
				}
				else {
					if (copy($oldfile, $newfile)) {
						++$success_count;
					}
				}
			}
		}

		$this->assign('msg', '成功' . ($op == 'cut' ? '剪切' : '复制') . '了' . $success_count . '个文件或目录');
		return $this->index();
	}

	public function compress()
	{
		return $this->whmopfile('compress', $_REQUEST['files'], $_REQUEST['dst'], array('password' => $_REQUEST['password']));
	}

	public function decompress()
	{
		return $this->whmopfile('decompress', $_REQUEST['files'], $_REQUEST['dst'], array('password' => $_REQUEST['password']));
	}

	private function whmopfile($op, $files, $dst, $arr = null)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('webapp.whm', $op);
		$whmCall->addParam('vh', getRole('vhost'));
		$whmCall->addParam('dst', $this->getusrfile($dst));

		if ($arr) {
			foreach ($arr as $k => $v) {
				$whmCall->addParam($k, $v);
			}
		}

		$index = 0;

		foreach ($files as $file) {
			$whmCall->addParam('file' . $index++, $this->getusrfile($file));
		}

		$result = $whm->call($whmCall);
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		if ($result && $result->getCode() == 200) {
			$str .= '<result code=\'200\'/>';
		}
		else {
			$str .= '<result code=\'500\'/>';
		}

		exit($str);
	}

	public function fileaccess()
	{
		$file = $_REQUEST['file'];
		$is_dir = $_REQUEST['is_dir'];

		if ($is_dir) {
			$is_dir = 1;
		}

		$action = $_REQUEST['act'];
		$fa = new FileAccess();

		if ($action == 'clear') {
			$fa->del($file);
		}
		else {
			if ($action == 'auth') {
				$action = 'auth:' . $_REQUEST['auth_user'];
			}

			if ($action == 'ip') {
				$action = 'ip:' . $_REQUEST['ip'];
			}

			$fa->add($file, $is_dir, $action);
		}

		return $this->index();
	}

	public function syncaccess()
	{
		$fa = new FileAccess();
		$fa->sync();
		return $this->index();
	}
}

?>