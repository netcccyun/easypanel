<?php
needRole('vhost');
class MimeControl extends Control
{
	public function mimeFrom()
	{
		$mimes = daocall('vhostinfo', 'getInfo', array(getRole('vhost'), 5));

		if (0 < count($mimes)) {
			foreach ($mimes as $m) {
				$exp = explode(',', $m['value']);
				$mime[] = array('file_ext' => $m['name'], 'mime_type' => $exp[0], 'cache_time' => $exp[2], 'gzip' => $exp[1]);
			}

			$this->_tpl->assign('mime', $mime);
		}

		return $this->_tpl->fetch('mime/mimefrom.html');
	}

	/**
	 * 文件扩展名长度不能大于12
	 * mime类型长度不能大于32
	 * Enter description here ...
	 */
	public function mimeAdd()
	{
		$file_ext = filterParam($_REQUEST['file_ext']);

		if (12 < strlen($file_ext)) {
			exit('文件扩展名输入错误');
		}

		$mime_type = filterParam($_REQUEST['mime_type'], 'mime');

		if (64 < strlen($mime_type)) {
			exit('mime类型输入错误');
		}

		if ($file_ext == '' || $mime_type == '') {
			exit('error:文件扩展名和类型不能为空');
		}

		$cache_time = intval($_REQUEST['cache_time']);
		$gzip = intval($_REQUEST['gzip']);
		$type = 5;
		$value = $mime_type . ',' . $gzip . ',' . $cache_time;
		$result = apicall('vhost', 'addInfo', array(getRole('vhost'), $file_ext, $type, $value, false));

		if (!$result) {
			exit('添加失败');
		}

		exit('成功');
	}

	public function mimeDel()
	{
		$vhost = getRole('vhost');
		$type = 5;
		$file_ext = filterParam($_REQUEST['file_ext']);

		if (!$file_ext) {
			exit('错误:文件扩展名不能为空');
		}

		if (apicall('vhost', 'delInfo', array($vhost, $file_ext, $type))) {
			exit('成功');
		}

		exit('删除失败');
	}

	public function mimeUpdate()
	{
		$type = 5;
		$file_ext = filterParam($_REQUEST['file_ext']);
		$mime_type = filterParam($_REQUEST['mime_type'], 'mime');
		if ($file_ext == '' || $mime_type == '') {
			exit('error:文件扩展名和类型不能为空');
		}

		$cache_time = intval($_REQUEST['cache_time']);
		$gzip = intval($_REQUEST['gzip']);
		$arr['name'] = $file_ext;
		$arr['value'] = $mime_type . ',' . $gzip . ',' . $cache_time;

		if (apicall('vhost', 'updateInfo', array(getRole('vhost'), $file_ext, $arr, $type))) {
			exit('成功');
		}

		exit('更新失败');
	}
}

?>