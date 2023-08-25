<?php
needRole('admin');
class BackupControl extends control
{
	public function resetTime()
	{
		daocall('setting', 'add', array('last_all_time', 0));
		daocall('setting', 'add', array('backup_last_time', 0));
		return $this->addForm();
	}

	public function restore()
	{
		$setting = daocall('setting', 'getAll', array());
		$ftp_dir = daocall('setting', 'get', array('ftp_dir'));

		if ($setting['backup_save_place'] == 'r') {
			$listdir = apicall('restore', 'listFtpDirFile', array($ftp_dir));
		}
		else {
			$listdir = apicall('restore', 'listLocalDirFile', array($setting['backup_dir']));
		}

		if ($listdir !== false) {
			$this->_tpl->assign('listdir', $listdir);
		}

		if ($_REQUEST['dir']) {
			$dir = $_REQUEST['dir'];
			$cmd = '<font color=\'red\'>由于恢复数据时运行时间超过PHP限制，请在命令行下运行以下命令来恢复<a href=javascript:show_vhost()>恢复单个网站</a><br>';

			if (strncasecmp(PHP_OS, 'WIN', 3) != 0) {
				$cmd .= 'nohup ';
			}
			else {
				$cmd .= '"';
			}

			$cmd .= $GLOBALS['safe_dir'] . '../ext/php56/';

			if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
				$cmd .= 'php.exe';
				$cmd .= '"';
			}
			else {
				$cmd .= 'bin/php';
			}

			if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
				$cmd .= ' -c "' . $GLOBALS['safe_dir'] . '../ext/php56/phpnode.ini" ';
			}
			else {
				$cmd .= ' -c "' . $GLOBALS['safe_dir'] . '../ext/php56/etc/php-node.ini" ';
			}

			$cmd .= ' -f "';
			$cmd .= dirname(dirname(dirname(__FILE__))) . '/framework/shell.php"' . ' restore ' . $dir;

			if ($_REQUEST['vhost']) {
				$cmd .= ' ' . $_REQUEST['vhost'];
			}

			$cmd .= '</font>';
			$this->_tpl->assign('dir', $dir);
			$this->_tpl->assign('cmd', $cmd);
		}

		return $this->fetch('backup/restore.html');
	}

	public function getVhosts()
	{
		$vhs = apicall('vhost', 'get', array(
	null,
	'rows',
	array('name')
	));
		$json['vhs'] = $vhs;
		$json['count'] = count($vhs);
		exit(json_encode($json));
	}

	public function addForm()
	{
		$setting = daocall('setting', 'getAll', array());
		$ftp_dir = daocall('setting', 'get', array('ftp_dir'));
		$dir = '/backup/';

		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			$dir = $GLOBALS['node_cfg']['localhost']['dev'] . '/backup/';
		}

		$volumn_size = array('200', '650', '1800');
		$backup_save_place = array(
			array('l', '本地'),
			array('r', '远程'),
			array('lr', '本地和远程')
			);
		$this->_tpl->assign('backup_save_place', $backup_save_place);
		$this->_tpl->assign('volumn_size', $volumn_size);
		$this->_tpl->assign('dir', $dir);
		$this->_tpl->assign('setting', $setting);
		return $this->_tpl->fetch('backup/index.html');
	}

	public function add()
	{
		daocall('setting', 'add', array('backup', trim($_REQUEST['backup'])));
		daocall('setting', 'add', array('ftp_host', trim($_REQUEST['ftp_host'])));
		daocall('setting', 'add', array('ftp_port', trim($_REQUEST['ftp_port'])));
		daocall('setting', 'add', array('ftp_user', trim($_REQUEST['ftp_user'])));
		daocall('setting', 'add', array('ftp_passwd', trim($_REQUEST['ftp_passwd'])));
		daocall('setting', 'add', array('backup_all_date', intval($_REQUEST['backup_all_date'])));
		daocall('setting', 'add', array('backup_date', intval($_REQUEST['backup_date'])));
		daocall('setting', 'add', array('volumn_size', $_REQUEST['volumn_size'] ? $_REQUEST['volumn_size'] : '200'));
		daocall('setting', 'add', array('backup_save_place', $_REQUEST['backup_save_place'] ? $_REQUEST['backup_save_place'] : 'l'));
		daocall('setting', 'add', array('backup_lowrun', intval($_REQUEST['backup_lowrun'])));
		daocall('setting', 'add', array('backup_mysql_single', intval($_REQUEST['backup_mysql_single'])));
		$backup_hour = intval($_REQUEST['backup_hour']);
		if ($backup_hour < 0 || 23 < $backup_hour) {
			$backup_hour = 0;
		}

		daocall('setting', 'add', array('backup_hour', $backup_hour));
		$backsavedir = trim($_REQUEST['backup_dir']);

		if (substr($backsavedir, 0 - 1) == '\\') {
			$len = strlen($backsavedir);
			$backsavedir[$len - 1] = '/';
		}

		if (substr($backsavedir, 0 - 1) != '/') {
			$backsavedir .= '/';
		}

		if (stripos($backsavedir, ' ') !== false) {
			exit('目录中不能包含空格');
		}

		daocall('setting', 'add', array('backup_dir', $backsavedir));
		if ($_REQUEST['ftp_host'] && $_REQUEST['ftp_dir'] == '') {
			exit('远程目录未设置');
		}

		$ftp_dir = trim($_REQUEST['ftp_dir']);
		$ftp_dir = trim($ftp_dir);
		$ftp_dir = trim($ftp_dir, '/');
		$ftp_dir = trim($ftp_dir, '\\');

		if (stripos($ftp_dir, ' ') !== false) {
			exit('目录中不能包含空格');
		}

		daocall('setting', 'add', array('ftp_dir', $ftp_dir));
		daocall('setting', 'add', array('backup_mysql', intval($_REQUEST['backup_mysql'])));
		daocall('setting', 'add', array('backup_passwd', trim($_REQUEST['backup_passwd'])));
		daocall('setting', 'add', array('backup_save_day', trim($_REQUEST['backup_save_day'])));
		daocall('setting', 'add', array('backup_web', intval($_REQUEST['backup_web'])));
		return $this->addForm();
	}

	public function testFtp()
	{
		$user = trim($_REQUEST['user']);
		$host = trim($_REQUEST['host']);
		$passwd = trim($_REQUEST['passwd']);
		$port = trim($_REQUEST['port']);
		$ftpcon = ftp_connect($host, $port);
		$ftplogin = ftp_login($ftpcon, $user, $passwd);

		if ($ftplogin) {
			exit('success');
		}

		exit('failed');
	}

	public function backupNow()
	{
		$cmd = apicall('process', 'get_easypanel_shell');
		$cmd .= ' backup all';

		if (strncasecmp(PHP_OS, 'WIN', 3) != 0) {
			$cmd .= ' &';
		}

		$cmd .= '</font>';
		$cmd .= '<script>$("#backup").hide();$("#hid").html("显示");$("#xsnazzy").css(\'display\',\'block\');$("#cmdt").html("\\<font color=\'red\'>请在命令行下运行以下命令备份\\<br\\>");</script>';
		exit($cmd);
	}

	/**
 	 * Enter description here ...
 	 */
	public function check_mysql()
	{
		$G = $GLOBALS['node_cfg']['localhost'];
		$user = $G['db_user'];
		$passwd = $G['db_passwd'];
		$port = $G['db_port'];
		if (!$user || !$passwd) {
			return false;
		}

		$dsn = 'mysql:host=localhost;port=' . $port;
		$pdo = new PDO($dsn, $user, $passwd);

		if (!$pdo) {
			return false;
		}

		$result = $pdo->query('SHOW VARIABLES');

		foreach ($result as $ret) {
			if ($ret['Variable_name'] == 'log_bin') {
				if ($ret['Value'] == 'ON') {
					exit('success');
				}
			}
		}

		exit('falied');
	}
}

?>