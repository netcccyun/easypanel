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

		if ($setting['backup_save_place'] == 'r') {
			$listdir = apicall('restore', 'listRemoteDirFile', array(''));
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

			$php_dir = apicall('process', 'get_easypanel_php_dir');
			$real_dir = realpath($php_dir);

			if ($real_dir) {
				$php_dir = rtrim($real_dir, '/\\') . '/';
			}

			$cmd .= $php_dir;

			if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
				$cmd .= 'php.exe';
				$cmd .= '"';
			}
			else {
				$cmd .= 'bin/php';
			}

			if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
				$cmd .= ' -c "' . $php_dir . 'phpnode.ini" ';
			}
			else {
				$cmd .= ' -c "' . $php_dir . 'etc/php-node.ini" ';
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
		$json = array();
		$json['vhs'] = $vhs;
		$json['count'] = count($vhs);
		exit(json_encode($json));
	}

	public function addForm()
	{
		$setting = daocall('setting', 'getAll', array());
		$dir = '/backup/';

		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			$dir = $GLOBALS['node_cfg']['localhost']['dev'] . '/backup/';
		}

		$remote_type = isset($setting['backup_remote_type']) ? $setting['backup_remote_type'] : '';

		if (!in_array($remote_type, array('ftp', 'sftp', 'webdav', 's3'))) {
			$remote_type = 'ftp';
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
		$this->_tpl->assign('remote_type', $remote_type);
		$this->_tpl->assign('ssh2_loaded', function_exists('ssh2_connect'));
		$ssh2_ini = php_ini_loaded_file();
		$this->_tpl->assign('ssh2_ini', $ssh2_ini ? $ssh2_ini : 'php.ini');
		$this->_tpl->assign('ssh2_ext', strncasecmp(PHP_OS, 'WIN', 3) == 0 ? 'php_ssh2.dll' : 'ssh2.so');
		return $this->_tpl->fetch('backup/index.html');
	}

	/**
	 * 目录配置归一：去首尾斜杠，禁止空格
	 */
	private function cleanDir($key)
	{
		$dir = trim(ep_request($key));
		$dir = trim($dir, '/');
		$dir = trim($dir, '\\');

		if (stripos($dir, ' ') !== false) {
			exit('目录中不能包含空格');
		}

		return $dir;
	}

	public function add()
	{
		daocall('setting', 'add', array('backup', trim(ep_request('backup'))));

		$type = strtolower(trim(ep_request('backup_remote_type')));

		if (!in_array($type, array('ftp', 'sftp', 'webdav', 's3'))) {
			$type = 'ftp';
		}

		daocall('setting', 'add', array('backup_remote_type', $type));
		daocall('setting', 'add', array('ftp_host', trim(ep_request('ftp_host'))));
		daocall('setting', 'add', array('ftp_port', trim(ep_request('ftp_port'))));
		daocall('setting', 'add', array('ftp_user', trim(ep_request('ftp_user'))));
		daocall('setting', 'add', array('ftp_passwd', trim(ep_request('ftp_passwd'))));
		daocall('setting', 'add', array('backup_all_date', intval($_REQUEST['backup_all_date'])));
		daocall('setting', 'add', array('backup_date', intval($_REQUEST['backup_date'])));
		daocall('setting', 'add', array('volumn_size', $_REQUEST['volumn_size'] ? $_REQUEST['volumn_size'] : '200'));
		$save_place = $_REQUEST['backup_save_place'] ? $_REQUEST['backup_save_place'] : 'l';
		daocall('setting', 'add', array('backup_save_place', $save_place));
		daocall('setting', 'add', array('backup_lowrun', intval($_REQUEST['backup_lowrun'])));
		daocall('setting', 'add', array('backup_mysql_single', intval($_REQUEST['backup_mysql_single'])));
		$backup_hour = intval($_REQUEST['backup_hour']);
		if ($backup_hour < 0 || 23 < $backup_hour) {
			$backup_hour = 0;
		}

		daocall('setting', 'add', array('backup_hour', $backup_hour));
		$backsavedir = trim(ep_request('backup_dir'));

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
		if ($save_place != 'l' && $type == 'ftp' && $_REQUEST['ftp_host'] && $_REQUEST['ftp_dir'] == '') {
			exit('远程目录未设置');
		}

		$ftp_dir = $this->cleanDir('ftp_dir');
		daocall('setting', 'add', array('ftp_dir', $ftp_dir));

		$sftp_host = trim(ep_request('sftp_host'));
		$sftp_port = intval(ep_request('sftp_port'));

		if ($sftp_port <= 0 || $sftp_port > 65535) {
			$sftp_port = 22;
		}

		$sftp_user = trim(ep_request('sftp_user'));
		$sftp_passwd = trim(ep_request('sftp_passwd'));
		$sftp_key = trim(ep_request('sftp_key'));
		$sftp_key_pub = trim(ep_request('sftp_key_pub'));
		$sftp_key_pass = trim(ep_request('sftp_key_pass'));
		$sftp_dir = $this->cleanDir('sftp_dir');

		$webdav_url = rtrim(trim(ep_request('webdav_url')), '/');
		$webdav_user = trim(ep_request('webdav_user'));
		$webdav_passwd = trim(ep_request('webdav_passwd'));
		$webdav_dir = $this->cleanDir('webdav_dir');

		$s3_endpoint = rtrim(trim(ep_request('s3_endpoint')), '/');
		$s3_region = trim(ep_request('s3_region'));

		if ($s3_region === '') {
			$s3_region = 'auto';
		}

		$s3_bucket = trim(ep_request('s3_bucket'));
		$s3_access_key = trim(ep_request('s3_access_key'));
		$s3_secret_key = trim(ep_request('s3_secret_key'));
		$s3_dir = $this->cleanDir('s3_dir');
		$webdav_timeout = intval(ep_request('webdav_timeout'));

		if ($webdav_timeout < 60 || $webdav_timeout > 86400) {
			$webdav_timeout = 3600;
		}

		$s3_timeout = intval(ep_request('s3_timeout'));

		if ($s3_timeout < 60 || $s3_timeout > 86400) {
			$s3_timeout = 3600;
		}

		if ($save_place != 'l') {
			if ($type == 'ftp') {
				if (trim(ep_request('ftp_host')) === '' || trim(ep_request('ftp_user')) === '') {
					exit('FTP 主机地址和账号不能为空');
				}
			}
			else if ($type == 'sftp') {
				if ($sftp_host === '' || $sftp_user === '') {
					exit('SFTP 主机地址和账号不能为空');
				}

				if ($sftp_key === '' && $sftp_passwd === '') {
					exit('SFTP 需要填写密码或私钥路径');
				}

				if ($sftp_key !== '' && !is_file($sftp_key)) {
					exit('SFTP 私钥文件不存在，请填写服务器上的绝对路径');
				}
			}
			else if ($type == 'webdav') {
				if ($webdav_url === '') {
					exit('WebDAV 地址不能为空');
				}

				if (!preg_match('!^https?://!i', $webdav_url)) {
					exit('WebDAV 地址需以 http:// 或 https:// 开头');
				}
			}
			else if ($type == 's3') {
				if ($s3_endpoint === '' || $s3_bucket === '' || $s3_access_key === '' || $s3_secret_key === '') {
					exit('S3 的 Endpoint、Bucket、AccessKey、SecretKey 必须填写');
				}

				if (!preg_match('!^https?://!i', $s3_endpoint)) {
					exit('S3 Endpoint 需以 http:// 或 https:// 开头');
				}
			}
		}

		daocall('setting', 'add', array('sftp_host', $sftp_host));
		daocall('setting', 'add', array('sftp_port', $sftp_port));
		daocall('setting', 'add', array('sftp_user', $sftp_user));
		daocall('setting', 'add', array('sftp_passwd', $sftp_passwd));
		daocall('setting', 'add', array('sftp_key', $sftp_key));
		daocall('setting', 'add', array('sftp_key_pub', $sftp_key_pub));
		daocall('setting', 'add', array('sftp_key_pass', $sftp_key_pass));
		daocall('setting', 'add', array('sftp_dir', $sftp_dir));
		daocall('setting', 'add', array('webdav_url', $webdav_url));
		daocall('setting', 'add', array('webdav_user', $webdav_user));
		daocall('setting', 'add', array('webdav_passwd', $webdav_passwd));
		daocall('setting', 'add', array('webdav_dir', $webdav_dir));
		daocall('setting', 'add', array('s3_endpoint', $s3_endpoint));
		daocall('setting', 'add', array('s3_region', $s3_region));
		daocall('setting', 'add', array('s3_bucket', $s3_bucket));
		daocall('setting', 'add', array('s3_access_key', $s3_access_key));
		daocall('setting', 'add', array('s3_secret_key', $s3_secret_key));
		daocall('setting', 'add', array('s3_dir', $s3_dir));
		daocall('setting', 'add', array('webdav_timeout', $webdav_timeout));
		daocall('setting', 'add', array('s3_timeout', $s3_timeout));
		daocall('setting', 'add', array('backup_mysql', intval($_REQUEST['backup_mysql'])));
		daocall('setting', 'add', array('backup_passwd', trim(ep_request('backup_passwd'))));
		daocall('setting', 'add', array('backup_save_day', trim(ep_request('backup_save_day'))));
		daocall('setting', 'add', array('backup_web', intval($_REQUEST['backup_web'])));
		return $this->addForm();
	}

	public function testRemote()
	{
		$type = strtolower(trim(ep_request('type')));

		if (!in_array($type, array('ftp', 'sftp', 'webdav', 's3'))) {
			exit('未知的远程存储类型');
		}

		$map = array(
			'ftp' => array('ftp_host' => 'host', 'ftp_port' => 'port', 'ftp_user' => 'user', 'ftp_passwd' => 'passwd', 'ftp_dir' => 'dir'),
			'sftp' => array('sftp_host' => 'host', 'sftp_port' => 'port', 'sftp_user' => 'user', 'sftp_passwd' => 'passwd', 'sftp_key' => 'key', 'sftp_key_pub' => 'key_pub', 'sftp_key_pass' => 'key_pass', 'sftp_dir' => 'dir'),
			'webdav' => array('webdav_url' => 'url', 'webdav_user' => 'user', 'webdav_passwd' => 'passwd', 'webdav_dir' => 'dir'),
			's3' => array('s3_endpoint' => 'endpoint', 's3_region' => 'region', 's3_bucket' => 'bucket', 's3_access_key' => 'access_key', 's3_secret_key' => 'secret_key', 's3_dir' => 'dir')
		);
		$setting = array('backup_remote_type' => $type);

		foreach ($map[$type] as $key => $param) {
			$value = trim(ep_request($param));

			if ($key == 'ftp_port' || $key == 'sftp_port') {
				$value = intval($value);
			}

			$setting[$key] = $value;
		}

		load_lib('pub:storage');
		$storage = RemoteStorage::create($setting);

		if (!$storage) {
			exit(RemoteStorage::getLastError());
		}

		if ($storage->test()) {
			exit('连接可用');
		}

		$error = $storage->getError();
		exit($error === '' ? '连接失败，请检查配置' : $error);
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
		$pdo = ep_new_pdo($dsn, $user, $passwd);

		if (!$pdo) {
			return false;
		}

		$result = $pdo->query('SHOW VARIABLES');

		if ($result) {
			foreach ($result as $ret) {
				if ($ret['Variable_name'] == 'log_bin') {
					if ($ret['Value'] == 'ON') {
						exit('success');
					}
				}
			}
		}

		exit('falied');
	}
}
