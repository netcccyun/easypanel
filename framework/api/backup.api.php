<?php
define('MYSQL_LIST_FILE', 'backupmysql.txt');
class BackupAPI extends API
{
	private $key;
	private $setting;
	private $os;
	private $savedir;
	private $time;
	private $date;
	private $filesavedir;
	private $backup_mysql_single;
	private $vhs;
	private $mysql_bin_dir;
	private $mysql_data_dir;
	private $db_user;
	private $db_passwd;
	private $db_host;
	private $db_port;
	private $kangle_dir;
	private $kangle_bin_dir;
	private $kangle_etc_dir;
	private $fopen;
	private $databases;
	private $ftpconnect = null;
	private $ignoredatabase;
	private $isbackup_web;
	private $isbackup_mysql;

	public function __construct()
	{
		$setting = daocall('setting', 'getAll', array());
		$this->setting = $setting;

		if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
			$this->os = 'win';
		}
		else {
			$this->os = 'lin';
		}

		$this->time = time();
		$this->date = date('Ymd', $this->time);
		$this->backup_mysql_single = $this->setting['backup_mysql_single'];
		$this->kangle_dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
		$this->kangle_bin_dir = $this->kangle_dir;

		if ($this->os == 'win') {
			$this->kangle_bin_dir .= '\\bin\\';
		}
		else {
			$this->kangle_bin_dir .= '/bin/';
		}

		$this->kangle_etc_dir = $GLOBALS['safe_dir'];
		$this->isbackup_web = $this->setting['backup_web'];
		$this->isbackup_mysql = $this->setting['backup_mysql'];
		load_lib('pub:process');
	}

	public function backup($argv = null)
	{
		if (!$this->check_ent()) {
			$this->showMsg("Not Support(Please contact us to buy)\n");
			return false;
		}

		if ($argv && $argv[0] == 'all') {
			$this->key = 'all';
			$this->changeLastAllTime($this->time);
		}
		else {
			if ($argv && $argv[0] == 'inc') {
				$this->key = 'inc';
			}
			else {
				if (!$this->checkBackup($this->time)) {
					return false;
				}
			}
		}

		if ($this->setting['backup_save_place'] != 'l' && !$this->setting['ftp_dir']) {
			$this->showMsg("ftp dir not set\n");
			return false;
		}

		$this->showMsg('backup model ');

		if ($this->key != 'all') {
			$this->savedir = date('YmdfH', time());
			$this->showMsg("inc\n");
		}
		else {
			$this->savedir = date('Ymd', time());
			$this->savedir .= 'f';
			$this->showMsg("full\n");
		}

		if ($this->os != 'win') {
			putenv('LANG=en_US');

			if ($this->setting['backup_lowrun'] && function_exists('pcntl_setpriority')) {
				pcntl_setpriority(20);
			}
		}
		else {
			if ($this->setting['backup_lowrun'] && function_exists('win32_lowrun')) {
				win32_lowrun();
			}
		}

		$this->changeBackupTime($this->time);
		$this->vhs = daocall('vhost', 'listVhost', array());
		$count = count($this->vhs);
		if (!$this->vhs || $count <= 0) {
			$this->showMsg("vh count is less than 1\n");
			return false;
		}

		foreach ($this->vhs as $vh) {
			if ($vh['ignore_backup'] == 1) {
				if ($vh['db_name']) {
					$this->ignoredatabase[] = $vh['db_name'];
				}
			}
		}

		if (!$this->setting['backup_dir']) {
			$this->showMsg("backup save dir not set\n");
			return false;
		}

		$this->filesavedir = $this->setting['backup_dir'] . $this->savedir . '/';

		if (!file_exists($this->setting['backup_dir'])) {
			mkdir($this->setting['backup_dir'], 448);
		}

		if (!file_exists($this->filesavedir)) {
			mkdir($this->filesavedir, 448);
		}

		if ($this->isbackup_web) {
			$this->showMsg($count . " vh need backup\n");
			$i = 0;

			foreach ($this->vhs as $vh) {
				++$i;
				$this->showMsg('backuping vh ' . $vh['name'] . ' (' . $i . '/' . $count . ') ...');

				if ($vh['ignore_backup'] == 1) {
					$this->showMsg(" Ignore done\n");
					continue;
				}

				$savefilename = $this->backupWeb($vh);

				if ($savefilename === false) {
					$this->showMsg($vh['name'] . " backupweb savefilename is return false\n");
					continue;
				}

				$this->ftpPush($savefilename);
				$this->showMsg("done\n");
			}
		}

		$this->showMsg('backuping etc ....');
		$this->backupEtc();
		$this->showMsg("done\n");
		$this->showMsg('backuping mysql....');
		$this->backupMysql();
		$this->showMsg("done\n");

		if ($this->setting['backup_save_place'] != 'l') {
			$this->delBackupFile();
		}

		if ($this->setting['backup_save_place'] != 'r') {
			$this->delLocalDir($this->setting['backup_dir'] . $this->savedir);
		}

		$this->delLocalExDir();
		$this->showMsg('success');
		//echo 'backup out please view ' . $this->kangle_dir . '/tmp/' . $this->time . ".backup.out.txt\n";
	}

	/**
	 * 删除本地备份目录
	 * r为只保存远程
	 */
	private function delLocalDir($dir)
	{
		if ($this->setting['backup_save_place'] == 'r') {
			return @rmdir($dir);
		}

		return true;
	}

	/**
	 * 删除本地备份过期保存文件
	 * 条件取决于备份保存时间
	 * Enter description here ...
	 */
	private function delLocalExDir()
	{
		$rd = opendir($this->setting['backup_dir']);

		if (!$rd) {
			$this->showMsg('opendir ' . $this->setting['backup_dir'] . " failed\n");
			return false;
		}

		$save_day = $this->setting['backup_save_day'];
		$del_time = $this->setting['backup_last_time'] - $save_day * 86400;
		$del_date = date('Ymd', $del_time);

		while (($dir = readdir($rd)) !== false) {
			if ($dir == '.' || $dir == '..') {
				continue;
			}

			$localdir = substr($dir, 0, 8);
			$f = substr($dir, 8, 1);

			if ($f != 'f') {
				echo $dir . " is not backup dir\n";
				continue;
			}

			if (is_dir($this->setting['backup_dir'] . $dir) && $localdir < $del_date) {
				$op = opendir($this->setting['backup_dir'] . $dir);

				while (($file = readdir($op)) !== false) {
					if ($file == '.' || $file == '..') {
						continue;
					}

					unlink($this->setting['backup_dir'] . $dir . '/' . $file);
				}

				closedir($op);
				chdir($this->setting['backup_dir']);
				rmdir($this->setting['backup_dir'] . $dir);
			}
		}

		closedir($rd);
	}

	/**
	 * 删除本地压缩文件
	 * ftp上传完以后调用
	 * @param  $file
	 */
	private function delLocalfile($file)
	{
		if ($this->setting['backup_save_place'] == 'r') {
			return unlink($file);
		}

		return true;
	}

	/**
	 * 备份单个的网站,属循环调用.
	 * Enter description here ...
	 * @param  $vh array
	 * @param  $backsavedir
	 * @param  $time
	 * @param  $backupfile
	 */
	private function backupWeb($vh)
	{
		$doc_root = $vh['doc_root'];
		$time = $this->date;
		$end_char = substr($doc_root, 0 - 1);
		if ($end_char == '/' || $end_char == '\\') {
			$doc_root = substr($doc_root, 0, strlen($doc_root) - 1);
		}

		$fdir = $doc_root;
		$txt = $fdir . '/' . $this->getFilename($vh['name']);
		@unlink($txt);
		$nbtxt = $fdir . '/' . $this->getFilename($vh['name'] . '.nb');
		@unlink($nbtxt);
		$result = $this->listBackupfile($doc_root);
		$this->writeListFile($result[0], $txt, $vh);
		$result[1][] = $nbtxt;
		$result[1][] = $txt;

		if (!$this->writeListFile($result[1], $nbtxt, $vh)) {
			$this->showMsg("web: writelistfile failed\n");
			return false;
		}

		$filename = $this->getFilename($vh['name'] . '.nb');
		chdir($fdir);
		$savefilename = $this->filesavedir . $vh['name'] . '.web.7z';
		$cmd = $this->get7zcmd();
		$cmd .= $savefilename;

		if ($this->os == 'win') {
			$cmd .= ' -scsWIN';
		}

		$cmd .= ' -y @' . $filename;
		$this->showCmd("backupweb cmd=\n");
		$this->showCmd($cmd);
		$this->showCmd("\n");
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			print_r($out);
			$this->showMsg('backup ' . $vh['name'] . " not successfuly\n");
			return false;
		}

		unlink($txt);
		unlink($nbtxt);
		return $savefilename;
	}

	/**
	 * mysql备份控制函数
	 * @param $key
	 * @param $backsavedir
	 */
	private function backupMysql()
	{
		if ($this->setting['backup_mysql'] != 1) {
			$this->showMsg("mysql: backup_mysql not start\n");
			return false;
		}

		$G = $GLOBALS['node_cfg']['localhost'];
		$this->db_user = $G['db_user'];
		$this->db_passwd = $G['db_passwd'];
		$this->db_port = $G['db_port'];
		$this->db_host = $G['db_host'] ? $G['db_host'] : 'localhost';
		if (!$this->db_user || !$this->db_passwd) {
			$this->showMsg("mysql:  db user or passwd not set\n");
			return false;
		}

		$dsn = 'mysql:host=localhost;port=' . $this->db_port;
		$pdo = new PDO($dsn, $this->db_user, $this->db_passwd);

		if (!$pdo) {
			$this->showMsg("mysql :pdo not connect\n");
			return false;
		}

		$result = $pdo->query('SHOW VARIABLES');

		foreach ($result as $r) {
			if ($r['Variable_name'] == 'log_bin') {
				$this->log_bin = 'on';
			}

			if ($r['Variable_name'] == 'datadir') {
				$datadir = $r['Value'];
			}
		}

		if (!$datadir) {
			$this->showMsg("mysql: not find mysql datadir\n");
			return false;
		}

		$this->mysql_data_dir = $datadir;

		if ($this->backup_mysql_single) {
			$this->backupMysqlSingle($pdo);
			return NULL;
		}

		if ($this->key != 'all') {
			$file = $this->backupMysqlInc($pdo);
		}
		else {
			$file = $this->backupMysqlFull($pdo);
		}

		$this->ftpPush($file);
	}

	private function backupMysqlSingleInc($pdo)
	{
		$selectresult0 = $pdo->query('show databases');
		$databases = $selectresult0->fetchAll();

		foreach ($databases as $row) {
			if ($row[0] == 'information_schema' || $row[0] == 'performance_schema') {
				continue;
			}

			$this->databases[] = $row[0];
		}

		$selectresult = $pdo->query('show master logs');
		$binfile = $selectresult->fetchAll();

		if (count($binfile) <= 0) {
			$this->showMsg("log-bin file count 0\n");
			return false;
		}

		foreach ($binfile as $row) {
			$binfile0[] = $row[0];
		}

		$pdo->query('flush logs');
		$selectlog = $pdo->query('show master logs');
		$binfile = $selectlog->fetchAll();

		foreach ($binfile as $row) {
			$binfile1[] = $row[0];
		}

		$count = count($binfile1);
		$last_log_file = $binfile1[$count - 1];
		$logbin = $this->getBinlogCmd();

		if (!chdir($this->mysql_data_dir)) {
			$this->showMsg('chdir ' . $this->mysql_data_dir . " failed\n");
			return false;
		}

		$filestr = '';
		$filestr = trim($filestr);
		$process = new Process();

		foreach ($this->databases as $database) {
			unset($cmd);
			unset($cmd2);
			$cmd[] = $logbin;

			foreach ($binfile0 as $file) {
				$cmd[] = $this->mysql_data_dir . $file;
			}

			$cmd[] = '--database=' . $database;
			$savefile = $this->filesavedir . $database . '.sql.7z';
			$cmd2 = $this->get7zcmdArray($savefile);
			$cmd2[] = '-si' . $database . '.sql';
			$processhandle = $process->run(array($cmd, $cmd2), null, $pipes, null, null, $stderr);
			$result = $process->result($processhandle, $pipes);
			if ($result != 0 && $result != 0 - 1) {
				$this->showMsg('error:backupMysqlSingleInc ' . $database . ' result=' . $result . "\n");
				continue;
			}

			$this->ftpPush($savefile);
		}

		$this->purgeMysqlLogs($pdo, $last_log_file);
	}

	/**
	 * mysql增量备份
	 * */
	private function backupMysqlInc($pdo)
	{
		$pdo->query('flush logs');
		$result = $pdo->query('SHOW master LOGS');
		$log_ret = $result->fetchAll();
		$count = count($log_ret);

		if ($count <= 0) {
			$this->showMsg("mysqlInc: show master logs  count is less than 1\n");
			return false;
		}

		$log_list = array();
		$file_list = array();
		$i = 0;

		while ($i < $count - 1) {
			$log_list[] = $log_ret[$i]['Log_name'];
			$file_list[] = $log_ret[$i]['Log_name'];
			++$i;
		}

		$file_list[] = MYSQL_LIST_FILE;
		$this->writeListFile($log_list, $this->mysql_data_dir . '/' . MYSQL_LIST_FILE);
		$this->writeListFile($file_list, $this->mysql_data_dir . '/mysql.nb.txt');
		$max_log_file = $log_ret[$count - 1]['Log_name'];
		$filename = 'mysql.sql.7z';
		$cmd = $this->get7zcmd();
		$cmd .= $this->filesavedir . $filename;
		$cmd .= ' @mysql.nb.txt';
		chdir($this->mysql_data_dir);
		$this->showMsg("backupmysqlinc cmd=\n");
		$this->showCmd($cmd);
		$this->showMsg("\n");
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->showMsg("backup mysqlinc falied\n");
			return false;
		}

		unlink($this->mysql_data_dir . '/' . MYSQL_LIST_FILE);
		unlink($this->mysql_data_dir . '/mysql.nb.txt');
		$this->purgeMysqlLogs($pdo, $max_log_file);
		return $this->filesavedir . $filename;
	}

	/**
	 * mysql全备份
	 * */
	private function backupMysqlFull($pdo)
	{
		$filename = 'mysql.sql.7z';

		if ($this->os == 'win') {
			$cmd = $this->getMysqldumpChCmd();
		}
		else {
			$cmd = $this->getMsqldumpCmd();
		}

		$cmd .= ' --flush-logs --single-transaction --master-data --all-databases |';

		if ($this->os == 'win') {
			$cmd .= $this->get7zChCmd();
		}
		else {
			$cmd .= $this->get7zcmd();
		}

		$cmd .= ' ';
		$cmd .= $this->filesavedir . $filename;
		$cmd .= ' -simysql.sql';

		if (!chdir($this->kangle_bin_dir)) {
			$this->showMsg('chdir to ' . $this->kangle_bin_dir . ' failed' . "\n");
			return false;
		}

		$this->showMsg("backupmysqlfull cmd=\n");
		$this->showCmd($cmd);
		$this->showMsg("\n");
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->showMsg('backupmysqlfull failed status=.' . $status . "\n");
			return false;
		}

		$this->purgeMysqlLogs($pdo);
		return $this->filesavedir . $filename;
	}

	/**
	 * 更新mysql的binlog，如果已备份，则通知mysql重新生成一份新的binlog文件
	 * @param unknown_type $pdo
	 * @param unknown_type $max_log_file
	 * @return boolean
	 */
	private function purgeMysqlLogs($pdo, $max_log_file)
	{
		if (!$max_log_file) {
			$pdo->query('flush logs');
			$result = $pdo->query('SHOW master LOGS');

			if (!$result) {
				$this->showMsg("bin-log not start\n");
				return true;
			}

			$log_ret = $result->fetchAll();

			if (count($log_ret) <= 0) {
				return false;
			}

			foreach ($log_ret as $log) {
				$log_names[] = $log[0];
			}

			$max_log_file = end($log_names);
		}

		$purge_sql = 'PURGE master LOGS TO \'' . $max_log_file . '\'';
		return $pdo->query($purge_sql);
	}

	/**
	 * 取得指定N个/所在位置
	 * 返回所在长度。
	 * @deprecated
	 * @param $doc_root
	 * @param $str
	 * @param $len
	 */
	private function getFwDirLen($doc_root, $str = '/', $len = 2)
	{
		$doclen = strlen($doc_root);
		$loc = 0;

		if (substr($doc_root, 0 - 1) != $str) {
			--$len;
		}

		$i = $doclen - 1;

		while (0 < $i) {
			if ($doc_root[$i] == $str) {
				++$loc;
			}

			if ($loc == $len) {
				return $i;
			}

			--$i;
		}

		return false;
	}

	/**
	 * 确认是否执行
	 * 已开启验证
	 * 是否已经在备份
	 * 是否为设置的天数
	 * 是否为设置的小时数
	 */
	private function checkBackup($now_time)
	{
		if (intval($this->setting['backup']) != 1) {
			$this->showMsg("backup not start\n");
			return false;
		}

		$all_time = $this->setting['backup_all_date'] * 86400 + $this->setting['last_all_time'] - 300;

		if ($all_time < $now_time) {
			$this->key = 'all';
			$now_hour = date('H', $now_time);

			if (intval($now_hour) != $this->setting['backup_hour']) {
				$this->showMsg($now_hour . '!=' . $this->setting['backup_hour'] . "\n");
				return false;
			}

			$this->changeLastAllTime($now_time);
		}
		else {
			$this->key = 'inc';
			$set_date = $this->setting['backup_date'] ? $this->setting['backup_date'] : 168;
			$backup_time = $this->setting['backup_last_time'] + $set_date * 3600;

			if ($now_time < $backup_time) {
				$this->showMsg($now_time . '<' . $backup_time . "\n");
				return false;
			}
		}

		return true;
	}

	/**
	 * ftp上传，同步模式
	 * @param  $file
	 */
	private function ftpPush($file)
	{
		if ($this->setting['backup_save_place'] == 'l') {
			return true;
		}

		if ($this->ftpconnect === false) {
			return false;
		}

		$ftp_backup_dir = '/' . $this->setting['ftp_dir'] . '/';
		$ftpdir = $ftp_backup_dir . $this->savedir;
		$this->ftpconnect = $this->getFtpCon();
		$i = 1;

		while ($i < 1000) {
			$new_file = $file . sprintf('.%03d', $i);

			if (!file_exists($new_file)) {
				break;
			}

			if ($this->ftpconnect === false) {
				return false;
			}

			$ret = ftp_pasv($this->ftpconnect, true);

			if ($i == 1) {
				@ftp_mkdir($this->ftpconnect, $ftp_backup_dir);
				@ftp_mkdir($this->ftpconnect, $ftpdir);
			}

			if (!ftp_chdir($this->ftpconnect, $ftpdir)) {
				return false;
			}

			$len = strrpos($new_file, '/') ? strrpos($new_file, '/') : strrpos($new_file, '\\');
			$filename = substr($new_file, $len + 1);

			if (!ftp_put($this->ftpconnect, $filename, $new_file, FTP_BINARY)) {
				$msg = $new_file . " ftpput failed\n";
				$this->showMsg($msg);
				ftp_close($this->ftpconnect);
				return false;
			}

			ftp_close($this->ftpconnect);
			$this->delLocalfile($new_file);
			++$i;
		}

		return true;
	}

	private function getFtpCon()
	{
		if (!$this->setting['ftp_host'] || !$this->setting['ftp_user'] || !$this->setting['ftp_passwd']) {
			$this->showMsg("put: not ftp connect parameter\n");
			$this->ftpconnect = false;
			return false;
		}

		$ftpcon = ftp_connect($this->setting['ftp_host'], $this->setting['ftp_port']);

		if (!$ftpcon) {
			$this->ftpconnect = false;
			$this->showMsg("put: ftp not connect\n");
			return false;
		}

		$ftplogin = ftp_login($ftpcon, $this->setting['ftp_user'], $this->setting['ftp_passwd']);

		if (!$ftplogin) {
			$this->ftpconnect = false;
			$this->showMsg("put: ftp not login\n");
			return false;
		}

		$this->ftpconnect = $ftpcon;
		return $ftpcon;
	}

	public function check_ent()
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('core.whm', 'info');
		$result = $whm->call($whmCall);
		$type = $result->get('type');
		return true;
	}

	private function backupEtc()
	{
		$filename = 'etc.7z';

		if ($this->os == 'win') {
			$cmd = $this->get7zChCmd();
		}
		else {
			$cmd = $this->get7zcmd();
		}

		$cmd .= ' ' . $this->filesavedir . $filename . ' ' . $this->kangle_etc_dir . '*';

		if (!chdir($this->kangle_bin_dir)) {
			return false;
		}

		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			print_r($out);
			$this->showMsg("backup etc failed\n");
			return false;
		}

		$this->ftpPush($this->filesavedir . $filename);
	}

	private function getBinlogCmd()
	{
		$filename = 'mysqlbinlog';

		if ($this->os == 'win') {
			$filename .= '.exe';
		}

		if (file_exists($this->kangle_bin_dir . $filename)) {
			return $this->kangle_bin_dir . $filename;
		}

		return $this->mysql_bin_dir . $filename;
	}

	private function getMysqldumpCmdArray()
	{
		$mysqldumpexe = 'mysqldump';

		if ($this->os == 'win') {
			$mysqldumpexe .= '.exe';
		}

		$cmd = array($mysqldumpexe, '-f', '-h', $this->db_host);
	}

	private function getMysqldumpChCmd()
	{
		if ($this->os == 'win') {
			$cmd = 'mysqldump.exe';
		}
		else {
			$cmd = $this->kangle_bin_dir . 'mysqldump';
		}

		$cmd .= ' -f -h ' . $this->db_host . ' -u ' . $this->db_user . ' -p' . $this->db_passwd;
		return $cmd;
	}

	private function getMsqldumpCmd()
	{
		$cmd = '"';
		$cmd .= $this->kangle_bin_dir . 'mysqldump';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		$cmd .= '" -f -h ' . $this->db_host . ' -u ' . $this->db_user . ' -p' . $this->db_passwd;
		return $cmd;
	}

	private function get7zcmdArray($file)
	{
		$z7exe = $this->kangle_bin_dir . '7z';

		if ($this->os == 'win') {
			$z7exe .= '.exe';
		}

		$cmd[] = $z7exe;
		$cmd[] = 'a';
		$cmd[] = $file;

		if ($this->setting['backup_passwd']) {
			$cmd[] = '-p' . $this->setting['backup_passwd'];
			$cmd[] = '-mhe';
		}

		if ($this->os == 'win') {
			$cmd[] = '-ssw';
		}

		if ($this->os == 'win') {
			$cmd2[7] = '-scsWIN';
		}

		$cmd[] = '-v' . $this->setting['volumn_size'] . 'm';
		return $cmd;
	}

	private function get7zcmd()
	{
		$cmd = '';

		if ($this->os == 'win') {
			$cmd = '"';
		}

		$cmd .= $this->kangle_bin_dir . '7z';

		if ($this->os == 'win') {
			$cmd .= '.exe';
			$cmd .= '"';
		}

		$cmd .= '  a';

		if ($this->setting['backup_passwd']) {
			$cmd .= ' -p' . $this->setting['backup_passwd'] . ' -mhe';
		}

		if ($this->os == 'win') {
			$cmd .= ' -ssw';
		}

		$cmd .= ' -v' . $this->setting['volumn_size'] . 'm ';
		return $cmd;
	}

	/**
	 *
	 * .' -mhe'防止加密后还可以看到压缩包内文件列表,把头也加密
	 * */
	private function get7zChCmd()
	{
		if ($this->os == 'win') {
			$cmd = '7z.exe';
		}
		else {
			$cmd = $this->kangle_bin_dir . '7z';
		}

		$cmd .= ' a';

		if ($this->setting['backup_passwd']) {
			$cmd .= ' -p' . $this->setting['backup_passwd'] . ' -mhe';
		}

		if ($this->os == 'win') {
			$cmd .= ' -ssw';
		}

		$cmd .= ' -v' . $this->setting['volumn_size'] . 'm ';
		return $cmd;
	}

	/**
	 * @deprecated
	 * Enter description here ...
	 */
	private function get7zUpCmd()
	{
		$cmd = '"';
		$cmd .= $this->kangle_bin_dir . '7z';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		$cmd .= '"';
		$cmd .= ' u';

		if ($this->setting['backup_passwd']) {
			$cmd .= ' -p' . $this->setting['backup_passwd'] . ' -mhe';
		}

		if ($this->os == 'win') {
			$cmd .= ' -ssw';
		}

		$cmd .= ' ';
		return $cmd;
	}

	private function delBackupFile()
	{
		if (intval($this->setting['backup_save_day']) <= 0) {
			return false;
		}

		$del_time = $this->setting['backup_last_time'] - $this->setting['backup_save_day'] * 86400;
		$ftpcon = $this->getFtpCon();
		$ret = ftp_pasv($ftpcon, true);
		ftp_chdir($ftpcon, $this->setting['ftp_dir']);
		$dirlist = ftp_nlist($ftpcon, '.');

		if (count($dirlist) <= 0) {
			return false;
		}

		$ddate = date('Ymd', $del_time);

		foreach ($dirlist as $d) {
			if (intval($d) <= 0) {
				continue;
			}

			if (substr($d, 0, 8) < $ddate) {
				ftp_chdir($ftpcon, $d);
				$dellist = ftp_nlist($ftpcon, '.');

				foreach ($dellist as $df) {
					ftp_delete($ftpcon, $df);
				}

				ftp_chdir($ftpcon, $this->setting['ftp_dir']);
				ftp_rmdir($ftpcon, $d);
			}
		}

		ftp_close($ftpcon);
	}

	/**
	 * @param array $list_content
	 * @param string $filename
	 * @param array $vh|null=mysql
	 * @return boolean
	 */
	private function writeListFile($list_content, $filename, $vh = null)
	{
		$dir = $vh['doc_root'];
		$name = $vh['name'];

		if (!$vh) {
			$dir = $this->mysql_data_dir;
			$name = 'backupmysql';
		}

		$cut_len = strlen($dir);
		if (substr($dir, 0 - 1) != '\\' && substr($dir, 0 - 1) != '/') {
			$cut_len += 1;
		}

		$this->delLocalfile($filename);
		$fp = fopen($filename, 'wt');

		if (!$fp) {
			$this->showMsg('open ' . $filename . " to write failed\n");
			return false;
		}

		if (count($list_content) <= 0) {
			$this->showMsg("list_content count is less than 1 \n");
			return false;
		}

		foreach ($list_content as $file) {
			$nbtxt = $this->getFilename($name . '.nb');

			if (strstr($file, $nbtxt)) {
				continue;
			}

			if (0 < $cut_len) {
				$file = substr($file, $cut_len);
			}

			fwrite($fp, $file . "\n");
		}

		fclose($fp);
		return true;
	}

	/**
	 * 备份，列出需要备份的文件
	 * 如果有上次备份时间，返回增量备份，否则返回整个目录文件
	 * 返回数组
	 * */
	private function listBackupfile($dir)
	{
		$last_all_time = intval($this->setting['last_all_time']);
		$backuptime = intval($this->setting['backup_last_time']);
		$ctime = 0;

		if ($this->key != 'all') {
			$ctime = $backuptime;
		}

		$result = array();
		$this->listFileEx($result, $dir, $ctime);
		return $result;
	}

	private function getFilename($vhost)
	{
		return $vhost . '.txt';
	}

	/**
	 *
	 * Enter description here ...
	 * @param unknown_type $result
	 * @param unknown_type $dir
	 * @param unknown_type $ctime
	 */
	public function listFileEx(&$result, $dir, $ctime, $level = 0)
	{
		$rd = opendir($dir);

		if (!file_exists($dir)) {
			echo $dir . " not found\n";
			return false;
		}

		while (($file = readdir($rd)) !== false) {
			if ($file == '.' || $file == '..') {
				continue;
			}

			if ($level == 0) {
				if ($file == 'backup' || $file == 'logs') {
					continue;
				}
			}

			$info = stat($dir . '/' . $file);

			if ($info[2] & S_IFDIR) {
				$this->listFileEx($result, $dir . '/' . $file, $ctime, $level + 1);
			}
			else {
				$result[0][] = $dir . '/' . $file;
				if ($ctime < $info[9] || $ctime < $info[10]) {
					$result[1][] = $dir . '/' . $file;
				}
			}
		}

		closedir($rd);
	}

	private function showCmd($cmd)
	{
		if ($this->setting['showCmd'] == 'out') {
			echo $cmd;
		}
	}

	private function showMsg($msg)
	{
		echo $msg . "\n";
	}

	/**
	 * 全备份时间
	 * */
	private function changeLastAllTime($time)
	{
		return daocall('setting', 'add', array('last_all_time', $time));
	}

	/**
	 * 备份成功后，更改备份时间
	 * */
	private function changeBackupTime($time)
	{
		return daocall('setting', 'add', array('backup_last_time', $time));
	}

	/**
	 * 备份顺序
	 */
	private function changeBackupKey($key)
	{
		return daocall('setting', 'add', array('backup_key', $key));
	}

	private function backupMysqlSingle($pdo)
	{
		if ($this->key != 'all') {
			$this->backupMysqlSingleInc($pdo);
			return NULL;
		}

		$this->backupMysqlSingleFull($pdo);
	}

	/**
	 * @deprecated
	 * 全备份，每个数据库分别备份
	 * @param $vh
	 * @param $backsavedir
	 * @param $time
	 */
	public function backupMysqlSingleFull($pdo)
	{
		$sql = 'SHOW DATABASES';
		$conn = mysqli_connect($this->db_host, $this->db_user, $this->db_passwd);

		if (!$conn) {
			$this->showMsg('不能连接数据库');
			return false;
		}

		$result = mysqli_query($conn, $sql);

		while ($row = mysqli_fetch_row($result)) {
			$databasename = $row[0];

			if (in_array($databasename, $this->ignoredatabase)) {
				$this->showMsg($databasename . " ignore\n");
				continue;
			}

			$filename = $databasename . '.sql.7z';

			if ($this->os == 'win') {
				$cmd = $this->getMysqldumpChCmd();
			}
			else {
				$cmd = $this->getMsqldumpCmd();
			}

			$cmd .= ' ' . $databasename . ' |';
			$cmd .= $this->get7zcmd();
			$cmd .= $this->filesavedir . '/' . $filename;
			$cmd .= ' -si' . $databasename . '.sql';
			exec($cmd, $msg, $status);
			if ($status != 0 && $status != 0 - 1) {
				print_r($msg);
			}

			$this->ftpPush($this->filesavedir . '/' . $filename);
		}
	}
}

?>