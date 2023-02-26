<?php
define('MYSQL_FILE_EXT', '.sql.7z');
define('WEB_FILE_EXT', '.web.7z');
class MigrateAPI extends API
{
	private $setting;
	private $os;
	private $save_dir;

	public function __construct()
	{
		$setting = daocall('setting', 'getAll', array());
		$this->setting = $setting;

		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			$this->os = 'win';
			return NULL;
		}

		$this->os = 'lin';
	}

	public function migrate()
	{
		$setting = $this->setting;
		if (!$setting['migrate_host'] || !$setting['migrate_skey']) {
			return false;
		}

		$save_dir = $GLOBALS['safe_dir'] . '../tmp/';
		$this->save_dir = $save_dir;
		$vh = daocall('vhost', 'getVhost', array('phptt'));

		if (!$this->zipvh($vh, $save_dir)) {
			return false;
		}
	}

	public function zipVh($vh, $save_dir)
	{
		return true;
		$this->delLocalZipFile($vh);
		return false;
	}

	public function migrateComplete($vh, $save_dir)
	{
		@unlink($save_dir . $vh . MYSQL_FILE_EXT);
		@unlink($save_dir . $vh . WEB_FILE_EXT);
		return true;
	}

	private function delLocalZipFile($vh)
	{
	}

	public function zipVhWeb($vh, $save_dir, $nolog = 1)
	{
		$attr['file'] = $save_dir . $vh . WEB_FILE_EXT;

		if ($nolog == 1) {
			$attr['ext_arg'] = '-xr0!logs';
		}

		$call = 'backup_web_system';
		return $this->whmShell($call, $vh, $attr);
	}

	public function zipVhSql($vh, $save_dir)
	{
		$G = $GLOBALS['node_cfg']['localhost'];
		$attr['passwd'] = $G['db_passwd'];
		$attr['dbname'] = $vh;
		$attr['user'] = $G['db_user'];
		$attr['file'] = $save_dir . $vh . MYSQL_FILE_EXT;
		$call = 'mysql_dumpout_sql_system';
		return $this->whmShell($call, $vh, $attr);
	}

	public function whmShell($call, $vh, $attr)
	{
		$result = apicall('shell', 'whmshell', array($call, $vh, $attr));

		if ($result === false) {
			trigger_error('调用shell失败');
			return false;
		}

		return $result;
	}

	public function query($session, $vh)
	{
		$attr['session'] = $session;
		$call = 'query';
		return $this->whmShell($call, $vh, $attr);
	}

	/**
	 * 控制错误信息处理，显示
	 * Enter description here ...
	 * @param $msg
	 */
	private function error_msg($msg)
	{
		echo $msg;
	}
}

?>