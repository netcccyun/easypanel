<?php
needRole('vhost');
class ShellControl extends Control
{
	/**
	 * index
	 * @see nodewww/webftp/framework/Control::index()
	 */
	public function index()
	{
		return $this->_tpl->fetch('shell/index.html');
	}

	/**
	 * wget 
	 */
	public function wget()
	{
		$arr = array();
		$arr['url'] = filterParam($_REQUEST['url'], 'url');

		if ($arr['url'] == '') {
			return $this->shellError('请输入下载地址');
		}

		if ($_REQUEST['filename'] == '') {
			return $this->shellError('请输入存放文件名');
		}

		$arr['file'] = filterParam($_REQUEST['dir'], 'dir') . '/' . filterParam($_REQUEST['filename']);

		if ($_REQUEST['-c']) {
			$arr['ext_arg'] = '-c';
		}

		return $this->whmshell('wget', $arr);
	}

	/**
	 * 数据库导出
	 * Enter description here ...
	 */
	public function mysqldumpout()
	{
		$attr = array();
		$attr['passwd'] = filterParam($_REQUEST['passwd']);

		if ($attr['passwd'] == '') {
			return $this->shellError('请输入数据库密码');
		}

		$attr['file'] = '/backup/' . getRole('vhost') . '_' . date('YmdHis', time()) . '.sql.7z';
		$attr['host'] = 'localhost';
		return $this->whmshell('mysql_dump_out', $attr);
	}

	/**
	 * 数据库远程导入
	 */
	public function remotemysqldumpin()
	{
		$arr['rehost'] = filterParam($_REQUEST['rehost']);
		$arr['reuser'] = filterParam($_REQUEST['reuser']);
		$arr['repasswd'] = filterParam($_REQUEST['repasswd']);
		$arr['redate'] = filterParam($_REQUEST['redate']);
		$arr['passwd'] = filterParam($_REQUEST['passwd']);
		if ($arr['rehost'] == '' || $arr['reuser'] == '' || $arr['repasswd'] == '' || $arr['redate'] == '' || $arr['passwd'] == '') {
			return $this->shellError('请填写完整的远程数据库和本地数据库信息');
		}

		$arr['host'] = 'localhost';
		return $this->whmshell('remote_mysql_dumpin_sql', $arr);
	}

	/**
	 * 数据库导入
	 * Enter description here ...
	 */
	public function mysqldumpin()
	{
		$attr['passwd'] = filterParam($_REQUEST['passwd']);
		$attr['file'] = filterParam($_REQUEST['file']);
		if ($attr['passwd'] == '' || $attr['file'] == '') {
			return $this->shellError('请输入数据库密码并选择导入文件');
		}

		$attr['host'] = 'localhost';
		if (substr($attr['file'], 0 - 3) == '.7z' || substr($attr['file'], 0 - 4) == '.zip') {
			return $this->whmshell('mysql_dump_in_compress', $attr);
		}

		return $this->whmshell('mysql_dump_in', $attr);
	}

	/**
	 * 网站备份
	 * file 
	 * backup_logs
	 */
	public function bakupweb()
	{
		if ($_REQUEST['backup_logs']) {
			$attr['ext_arg'] = '-xr0!logs';
		}

		if ($_REQUEST['password']) {
			$attr['password'] = '-p' . filterParam($_REQUEST['password']);
		}

		$attr['file'] = '/backup/' . getRole('vhost') . '_' . date('Ymd_His', time()) . '.web.7z';
		return $this->whmshell('backup_web', $attr);
	}

	/**
	 * 恢复网站
	 * input file
	 */
	public function restoreweb()
	{
		$attr['file'] = filterParam($_REQUEST['file'], 'dir');

		if ($attr['file'] == '') {
			return $this->shellError('请选择要恢复的备份文件');
		}

		if ($_REQUEST['password']) {
			$attr['password'] = '-p' . filterParam($_REQUEST['password']);
		}

		if ($_REQUEST['coverfile'] == 1) {
			$attr['ext_arg'] = '-aoa';
		}
		else {
			$attr['ext_arg'] = '-aos';
		}

		return $this->whmshell('restore_web', $attr);
	}

	/**
	 * 备份所有，数据库和网站
	 */
	public function baupall()
	{
	}

	/**
	 * 恢复所有
	 * Enter description here ...
	 */
	public function restoreall()
	{
	}

	/**
	 * 
	 * Enter description here ...
	 */
	public function query()
	{
		$vh = getRole('vhost');
		$session = filterParam($_REQUEST['session']);
		if ($session == '') {
			return $this->shellError('缺少任务编号');
		}
		$result = apicall('shell', 'query', array($session, $vh));
		$this->shellJson($this->shellResult($result));
	}

	private function shellJson($data)
	{
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		exit(json_encode($data));
	}

	private function shellError($message)
	{
		if (is_ajax_request()) {
			$this->shellJson(array('code' => 400, 'msg' => $message, 'out' => ''));
		}
		exit($message);
	}

	private function shellResult($result)
	{
		if ($result === false) {
			return array('code' => 500, 'msg' => '调用命令执行接口失败', 'out' => '');
		}
		return array('code' => $result->getCode(), 'session' => (string) $result->get('session'),
			'out' => (string) $result->get('out'), 'errno' => (string) $result->get('errno'));
	}

	/**
	 * @param  $call
	 * @param  $attr
	 */
	private function whmshell($call, $attr)
	{
		$result = apicall('shell', 'whmshell', array($call, getRole('vhost'), $attr));
		if (is_ajax_request()) {
			$this->shellJson($this->shellResult($result));
		}

		if ($result === false) {
			trigger_error('调用shell失败');
			return false;
		}

		$this->assign('code', $result->getCode());
		$this->assign('session', $result->get('session'));
		$this->display('shell.html');
		exit();
	}
}
