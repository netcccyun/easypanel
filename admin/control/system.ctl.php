<?php
needRole('admin');
class SystemControl extends Control
{
	public function setPhpiniFrom()
	{
		return $this->_tpl->fetch('system/phpini.html');
	}

	/**
	 * old
	 * @deprecated
	 * @return Ambigous <string, void, unknown>
	 */
	public function editFileForm()
	{
		exit('not suppor');
		$file = $GLOBALS['safe_dir'] . '../ext/tpl_php52/php-templete.ini';
		$fp = fopen($file, 'rb');

		if (!$fp) {
			$this->assign('msg', '不能打开文件:' . $file);
			return $this->fetch('msg.html');
		}

		$str = fread($fp, filesize($file));
		fclose($fp);
		$charset = 'UTF-8';

		if (!$this->is_utf8($str)) {
			$str = mb_convert_encoding($str, 'UTF-8', 'GBK');
			$charset = 'GBK';
		}

		$this->assign('file', $file);
		$this->assign('charset', $charset);
		$this->assign('content', $str);
		return $this->fetch('system/file.html');
	}

	/**
	 * old @deprecated
	 * @return Ambigous <string, void, unknown>
	 */
	public function editFile()
	{
		exit('not suppor');
		$file = $GLOBALS['safe_dir'] . '../ext/tpl_php52/php-templete.ini';
		$charset = $_REQUEST['charset'];
		$content = $_REQUEST['content'];

		if (strcasecmp($charset, 'UTF-8') != 0) {
			$content = mb_convert_encoding($content, $charset, 'UTF-8');
		}

		$fp = fopen($file, 'wb');

		if (!$fp) {
			$this->assign('msg', '不能写入文件:' . $file);
			return $this->fetch('msg.html');
		}

		fwrite($fp, $content);
		fclose($fp);
		$this->assign('msg', '编辑成功');
		return $this->fetch('msg.html');
	}

	public function is_utf8($liehuo_net)
	{
		if (preg_match('/^([' . chr(228) . '-' . chr(233) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}){1}/', $liehuo_net) == true || preg_match('/([' . chr(228) . '-' . chr(233) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}[' . chr(128) . '-' . chr(191) . (']' . '{1}){1}$/'), $liehuo_net) == true || preg_match('/([' . chr(228) . '-' . chr(233) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}[' . chr(128) . '-' . chr(191) . ']{1}){2,}/', $liehuo_net) == true) {
			return true;
		}

		return false;
	}
}

?>