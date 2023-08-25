<?php
define('ALL_BACKUP_LEN', 9);
define('MYSQL_LIST_FILE', 'backupmysql.txt');
class RestoreAPI extends API
{
	private $setting;
	private $key;
	private $dir;
	private $restore_dir;
	private $mysql_bin_dir;
	private $os;
	private $bin_log_start;
	private $vhs;
	private $backup_mysql_single;
	private $filesavedir;
	private $db_user;
	private $db_passwd;
	private $db_port;
	private $db_host;
	private $backup_dir;
	private $kangle_dir;
	private $kangle_bin_dir;
	private $kangle_etc_dir;
	private $fopen;
	private $databases;
	private $ftpconnect = null;
	private $vh;
	private $vhinfo;
	private $pdo;
	private $z7outtmpdir;
	private $z7outtmpdirname = 'tmp';
	private $error;
	private $process;

	public function __construct()
	{
		$setting = daocall('setting', 'getAll', array());
		$this->setting = $setting;

		if (is_win()) {
			$this->os = 'win';
		}
		else {
			$this->os = 'lin';
		}

		$this->kangle_etc_dir = $GLOBALS['safe_dir'];
		$this->kangle_dir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
		$this->kangle_bin_dir = $this->kangle_dir;

		if ($this->os == 'win') {
			$this->kangle_bin_dir .= '\\bin\\';
		}
		else {
			$this->kangle_bin_dir .= '/bin/';
		}

		$this->backup_mysql_single = $this->setting['backup_mysql_single'];
		$this->backup_dir = $this->setting['backup_dir'];
		$this->z7outtmpdir = $this->backup_dir . $this->z7outtmpdirname . '/';
		load_lib('pub:process');
	}

	public function restore($dir, $vhname = null)
	{
		$this->dir = $dir;

		if (!$this->dir) {
			$this->showMsg("restore dir is empty\n");
			return false;
		}

		if ($vhname) {
			if ($this->setting['backup_mysql'] && !$this->backup_mysql_single) {
				$this->showMsg("mysql backup mode no select Independent backup\n");
				return false;
			}

			$vh = apicall('vhost', 'getByName', array($vhname));

			if ($vh) {
				$this->vh = $vh;
			}
			else {
				$this->showMsg($vhname . ' vhost not found ,you must create the ' . $vhname . " vhost\n");
				return false;
			}
		}

		if ($this->os != 'win') {
			putenv('LANG=en_US');
		}

		echo 'now restore dir=' . $dir . "\n";
		$ftp_dirs = $this->listFtpDirFile($this->setting['ftp_dir']);
		$count = count($ftp_dirs);

		if ($count <= 0) {
			$this->showMsg("count ftp_dir min 1\n");
			return false;
		}

		$this->restore_dir = $this->getRestorDir($ftp_dirs);

		if (!$this->restore_dir) {
			exit("restore_dir is not set\n");
		}

		if (substr($this->restore_dir[0], 0 - 1) != 'f') {
			array_multisort($this->restore_dir, SORT_ASC);
		}

		if (!$this->vh) {
			$this->showMsg("restore etc .........\n");
			$this->restoreEtc();
			$this->showMsg("restore etc .........done\n");
			$vhs = daocall('vhost', 'listVhost', array());

			foreach ($vhs as $vh) {
				if ($vh['ignore_backup'] == 1) {
					continue;
				}

				$v[] = $vh;
			}

			$this->vhs = $v;
			$count = count($this->vhs);

			if ($count <= 0) {
				$this->showMsg("nothing vhost need restore\n");
				return false;
			}

			$this->showMsg($count . " vh need restore...\n");
			sleep(4);
		}

		if (!$this->backup_mysql_single && $this->setting['backup_mysql'] == 1) {
			$this->showMsg("restore mysql .........\n");
			$this->restoreMysql();
			$this->showMsg("restore mysql .........done\n");
		}

		$i = 0;
		$createdatabase = false;
		$this->changeCompetence();

		if (!$this->vh) {
			foreach ($this->vhs as $vh) {
				++$i;
				$this->showMsg('restoreing vh ' . $vh['name'] . ' (' . $i . '/' . $count . ").........\n");

				if (!apicall('vhost', 'resync', array($vh['name']))) {
					$this->showMsg('resync vhost ' . $vh['name'] . ' failed error=' . $GLOBALS['last_error']);
					apicall('vhost', 'resync', array($vh['name']));
				}

				if (!file_exists($vh['doc_root'])) {
					$this->showMsg($vh['doc_root'] . " not found\n");
					continue;
				}

				$this->restoreWeb($vh);
				$this->showMsg('restoreing vh ' . $vh['name'] . " .........done\n");
			}
		}
		else {
			apicall('vhost', 'resync', array($this->vh, $createdatabase));
			$this->restoreWeb($this->vh);
		}

		$this->restoreCompetence();
		if ($this->backup_mysql_single && $this->setting['backup_mysql'] == 1) {
			$this->showMsg("restore mysql .........\n");
			$this->restoreMysql();
			$this->showMsg("restore mysql .........done\n");
		}
	}

	private function restoreMysql()
	{
		if (count($this->restore_dir) <= 0) {
			$this->showMsg("restore_dir not found for restoremysql\n");
			return false;
		}

		$G = $GLOBALS['node_cfg']['localhost'];
		$this->db_user = $G['db_user'];
		$this->db_passwd = $G['db_passwd'];
		$this->db_port = $G['db_port'];
		if (!$this->db_user || !$this->db_passwd) {
			$this->showMsg("mysql user or passwd not set\n");
			return false;
		}

		$this->db_host = $G['db_host'] ? $G['db_host'] : 'localhost';
		$this->pdo = new PDO('mysql:host=localhost;port=' . $this->db_port, $this->db_user, $this->db_passwd);

		if (!$this->pdo) {
			$this->showMsg("connect to mysql failed for restoremysql\n");
			$this->showMsg('user=' . $this->db_user . '&passwd=' . $this->db_passwd . '&port=' . $this->db_port);
			return false;
		}

		$result = $this->pdo->query('SHOW VARIABLES');

		foreach ($result as $r) {
			if ($r['Variable_name'] == 'basedir') {
				$this->mysql_bin_dir = $r['Value'] . '/bin/';
			}

			if ($r['Variable_name'] == 'log_bin') {
				if (strtolower($r['Value']) != 'on') {
					$this->bin_log_start = 'off';
				}
			}
		}

		$i = 0;

		while ($i < count($this->restore_dir)) {
			if ($this->restore_dir[$i] == $this->z7outtmpdirname) {
				continue;
			}

			if (substr($this->restore_dir[$i], 0 - 1) == 'f') {
				if ($i != 0) {
					$this->showMsg("BUG! you may catch  a bug\n");
				}

				if ($this->backup_mysql_single) {
					$this->restoreMysqlSingleFull($this->restore_dir[$i]);
				}
				else {
					$this->restoreMysqlFull($this->restore_dir[$i]);
				}
			}
			else if ($this->backup_mysql_single) {
				$this->restoreMysqlSingleInc($this->restore_dir[$i]);
			}
			else {
				$this->restoreMysqlInc($this->restore_dir[$i]);
			}

			++$i;
		}
	}

	private function restoreMysqlInc($dir)
	{
		if ($this->bin_log_start === 'off') {
			$this->showMsg("mysql bin_log not start\n");
			return false;
		}

		if ($this->mysql_bin_dir == '') {
			$this->showMsg("mysql_bin_dir not found\n");
			return false;
		}

		$filename = 'mysql.sql.7z.001';

		if (file_exists($this->backup_dir . $filename)) {
			$this->delLocalFile($this->backup_dir . $filename);
		}

		if (file_exists($this->backup_dir . MYSQL_LIST_FILE)) {
			$this->delLocalFile($this->backup_dir . MYSQL_LIST_FILE);
		}

		$wget_file = $this->wgetFtpfile($filename, $dir);

		if (!$wget_file) {
			$this->showMsg('restore: get file ' . $filename . " failed\n");
			return false;
		}

		if ($this->os == 'win') {
			$mcmd = $this->getMysqlChCmd();
		}
		else {
			$mcmd = $this->getMysqlCmd();
		}

		$zcmd = $this->get7zCmd();
		$zzcmd = $zcmd;
		$zzcmd .= $wget_file . ' -o' . $this->backup_dir . ' -aoa';
		$this->outCmd("restore mysqlinc cmd=\n");
		$this->outCmd($zzcmd);
		$this->outCmd("\n");
		exec($zzcmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->showMsg('restore error : zip file ' . $wget_file . " failed\n");
			$this->delLocalFile($wget_file);
			return false;
		}

		if (!file_exists($this->backup_dir . MYSQL_LIST_FILE)) {
			$this->showMsg('restore error: file ' . $this->backup_dir . MYSQL_LIST_FILE . ' not found\\n');
			$this->delLocalFile($wget_file);
			return false;
		}

		$files = file_get_contents($this->backup_dir . MYSQL_LIST_FILE);
		$files = explode("\n", $files);

		if (count($files) <= 0) {
			return false;
		}

		if ($this->os == 'win') {
			if (!chdir($this->mysql_bin_dir)) {
				$this->showMsg("not chdir to mysql_bin_dir\n");
				return false;
			}

			$logcmd = 'mysqlbinlog';
		}
		else {
			$logcmd = $this->mysql_bin_dir . '/mysqlbinlog';
		}

		if ($this->os == 'win') {
			$logcmd .= '.exe';
		}

		foreach ($files as $f) {
			if ($f == '') {
				continue;
			}

			$acmd = $logcmd . ' ' . $this->backup_dir . $f . ' | ';
			$bcmd = $acmd . $mcmd;
			exec($bcmd, $out, $status);
			$this->outCmd("restore mysqlinc cmd=\n");
			$this->outCmd($bcmd);
			$this->outCmd("\n");
			if ($status != 0 && $status != 0 - 1) {
				$this->showMsg('restor: ' . $f . " failed for restore restoremysqlinc\n");
				$this->delLocalFile($this->backup_dir . $f);
				continue;
			}

			$this->delLocalFile($this->backup_dir . $f);
		}

		$this->delLocalFile($wget_file);
		$this->delLocalFile($this->backup_dir . MYSQL_LIST_FILE);
		return true;
	}

	private function restoreMysqlFull($dir)
	{
		$file = $this->backup_dir . $dir . '/mysql.sql.7z.001';

		if (!file_exists($file)) {
			return false;
		}

		if (!chdir($this->kangle_bin_dir)) {
			$this->showMsg('chdir to ' . $this->kangle_bin_dir . " failed\n");
			return false;
		}

		$cmd = $this->get7zCmdArray($file);
		$cmd2 = $this->getMysqlCmdArray();
		$process = new Process();
		$processhandle = $process->run(array($cmd, $cmd2), null, $pipes);
		$result = $process->result($processhandle, $pipes, null, null, '-', $stderr);
		if ($result != 0 && $result != 0 - 1) {
			$status = $this->runCmd($cmd, $cmd2);
			if ($status != 0 && $status != 0 - 1) {
				$this->showMsg('restore mysql full failed result=' . $result . "\n");
				return false;
			}
		}

		return true;
	}

	/**
	 * 依据vh['db_name']来恢复，如果没有，略过
	 * @param unknown_type $dir
	 */
	private function restoreMysqlSingleFull($dir)
	{
		if (!chdir($this->kangle_bin_dir)) {
			$this->showMsg('not chdir to ' . $this->kangle_bin_dir . "\n");
			return false;
		}

		$this->process = new Process();

		if ($this->vh) {
			if ($this->vh['db_name']) {
				$this->mysqlFullOne($this->vh, $dir);
			}
		}
		else {
			$file = $this->backup_dir . $dir . '/' . 'mysql.sql.7z.001';

			if (!file_exists($file)) {
				$this->showMsg($file . " not found\n");
				return false;
			}

			$cmd = $this->get7zCmdArray($file);
			$cmd2 = $this->getMysqlCmdArray('mysql');
			$processhandle = $this->process->run(array($cmd, $cmd2), null, $pipes);
			$result = $this->process->result($processhandle, $pipes, null, null, '-', $stderr);
			if ($result != 0 && $result != 0 - 1) {
				$status = $this->runCmd($cmd, $cmd2);
				if ($status != 0 && $status != 0 - 1) {
					echo 'stderr=' . $stderr . "\n";
					$this->showMsg('error:restoreMysqlSingleFull mysql failed result=' . $result . "\n");
				}
			}

			foreach ($this->vhs as $vh) {
				if (!$vh['db_name']) {
					continue;
				}

				$this->pdo->query('CREATE DATABASE IF NOT EXISTS `' . $vh['db_name'] . '`');
				$this->mysqlFullOne($vh, $dir);
			}
		}

		$this->pdo->query('flush privileges');
	}

	private function mysqlFullOne($vh, $dir)
	{
		$this->showMsg('restore ' . $vh['name'] . '.sql...');
		$file = $this->backup_dir . $dir . '/' . $vh['db_name'] . '.sql.7z.001';

		if (!file_exists($file)) {
			$this->showMsg($file . " not found\n");
			return false;
		}

		$this->showMsg($file . " is find\n");
		$cmd = $this->get7zCmdArray($file);
		$cmd2 = $this->getMysqlCmdArray($vh['db_name']);

		if (!$this->process) {
			$this->process = new Process();
		}

		$processhandle = $this->process->run(array($cmd, $cmd2), null, $pipes);
		$result = $this->process->result($processhandle, $pipes, null, null, '-', $stderr);
		$this->showMsg('result=' . $result . "\n");
		if ($result != 0 && $result != 0 - 1) {
			$status = $this->runCmd($cmd, $cmd2);
			if ($status != 0 && $status != 0 - 1) {
				echo 'stderr=' . $stderr . "\n";
				$this->showMsg('error:restoreMysqlSingleFull ' . $vh['db_name'] . (' failed result=' . $result . "\n"));
				return false;
			}
		}

		$this->pdo->query('flush privileges');
		$this->showMsg("+++done\n");
	}

	/**
	 * 单独恢复binlog
	 * @param unknown_type $dir
	 */
	private function restoreMysqlSingleInc($dir)
	{
		$this->showMsg("restore(single) mysql inc ...\n");

		if ($this->bin_log_start == 'off') {
			$this->showMsg("bin-log not start\n");
			return false;
		}

		if (!chdir($this->kangle_bin_dir)) {
			$this->showMsg('chdir to ' . $this->kangle_bin_dir . " failed\n");
			return false;
		}

		if ($this->vh) {
			if ($this->vh['db_name']) {
				$this->mysqlIncOne($this->vh, $dir);
				return NULL;
			}
		}
		else {
			foreach ($this->vhs as $vh) {
				if (!$vh['db_name']) {
					continue;
				}

				$this->mysqlIncOne($vh, $dir);
			}
		}
	}

	/**
	 * 恢复一个数据库。
	 * @param unknown_type $vh
	 * @return boolean
	 */
	private function mysqlIncOne($vh, $dir)
	{
		$this->showMsg('restore ' . $vh['db_name'] . '...');
		$file = $this->backup_dir . $dir . '/' . $vh['db_name'] . '.sql.7z.001';

		if (!file_exists($file)) {
			$this->showMsg("+++++failed\n");
			$this->showMsg($file . " not found\n");
			return false;
		}

		$cmd = $this->get7zCmdArray($file);
		$cmd2 = $this->getMysqlCmdArray($vh['db_name']);
		$process = new Process();
		$processhandle = $process->run(array($cmd, $cmd2), null, $pipes);
		$result = $process->result($processhandle, $pipes, null, null, $stderr);
		if ($result != 0 && $result != 0 - 1) {
			$status = $this->runCmd($cmd, $cmd2);
			if ($status != 0 && $status != 0 - 1) {
				$this->showMsg('error:restoreMysqlSingleInc ' . $vh['db_name'] . (' failed result=' . $result . "\n"));
				return false;
			}
		}

		$this->showMsg("...done\n");
	}

	/**
	 * 
	 */
	private function runCmd($cmd, $cmd2 = null)
	{
		$command0 = '';

		foreach ($cmd as $val) {
			if ($command0 != '') {
				$command0 .= ' ';
			}

			$command0 .= '"' . $val . '"';
		}

		if ($cmd2) {
			$command0 .= '|';
			$command2 = '';

			foreach ($cmd2 as $val) {
				if ($command2 != '') {
					$command2 .= ' ';
				}

				$command2 .= '"' . $val . '"';
			}
		}

		$command = $command0 . $command2;
		exec($command, $out, $status);
		$this->outCmd($command . "\n");
		if ($status != 0 && $status != 0 - 1) {
			print_r($out);
		}

		return $status;
	}

	private function delLocalFile($file)
	{
		$suffix_file = substr($file, 0 - 4);

		if ($suffix_file == '.001') {
			$base_file = substr($file, 0, strlen($file) - 4);
			$i = 1;

			while ($i < 1000) {
				$new_file = $base_file . sprintf('.%03d', $i);

				if (!file_exists($new_file)) {
					return true;
				}

				@unlink($new_file);
				++$i;
			}

			return true;
		}

		return @unlink($file);
	}

	private function get7zCmdArray($file = null, $outdir = null, $outfile = null)
	{
		$z7exe = $this->kangle_bin_dir . '7z';

		if ($this->os == 'win') {
			$z7exe .= '.exe';
		}

		$cmd = array($z7exe, 'x');

		if ($file) {
			$cmd[] = $file;
		}

		$cmd[] = '-aoa';

		if ($outfile) {
			$cmd[] = '-o' . $outfile;
		}
		else {
			$cmd[] = '-so';
		}

		if ($outdir) {
			$cmd[] = '-o' . $outdir;
		}

		if ($this->setting['backup_passwd']) {
			$cmd[] = '-p' . $this->setting['backup_passwd'];
		}

		return $cmd;
	}

	private function getMysqlCmdArray($database = null)
	{
		$mysqlexe = $this->kangle_bin_dir . 'mysql';

		if ($this->os == 'win') {
			$mysqlexe .= '.exe';
		}

		$cmd = array($mysqlexe, '-f', '-u', $this->db_user, '-p' . $this->db_passwd, '-h', $this->db_host);

		if ($database) {
			$cmd[] = $database;
		}

		return $cmd;
	}

	private function get7zCmd()
	{
		$cmd = '"';
		$cmd .= $this->kangle_bin_dir . '7z';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		$cmd .= '" x ';

		if ($this->setting['backup_passwd']) {
			$cmd .= ' -p' . $this->setting['backup_passwd'] . ' ';
		}

		return $cmd;
	}

	private function get7zChCmd()
	{
		$cmd .= '7z';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		if ($setting['backup_passwd']) {
			$cmd .= ' -p' . $this->setting['backup_passwd'] . ' ';
		}

		return $cmd;
	}

	private function getMysqlChCmd()
	{
		$cmd = 'mysql';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		$cmd .= ' -h ' . $this->db_host . '  -f -u ' . $this->db_user . ' -p' . $this->db_passwd;
		return $cmd;
	}

	private function getMysqlCmd()
	{
		$cmd = '"';
		$cmd .= $this->kangle_bin_dir . 'mysql';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		$cmd .= '"';
		$cmd .= ' -h ' . $this->db_host . '  -f -u ' . $this->db_user . ' -p' . $this->db_passwd;
		return $cmd;
	}

	/**
	 * 只恢复一次
	 * 因为需要恢复最新的数据，所以只恢复最后的一次
	 * Enter description here ...
	 */
	private function restoreEtc()
	{
		close_all_db();
		$wget_file = $this->wgetFtpfile('etc.7z.001', $this->dir);

		if (!$wget_file) {
			$this->showMsg('wget file etc.7z.001 to ' . $this->dir . " failed\n");
			return false;
		}

		$cmd = '"';
		$cmd .= $this->kangle_bin_dir . '7z';

		if ($this->os == 'win') {
			$cmd .= '.exe';
		}

		$cmd .= '"';
		$cmd .= ' x ';

		if ($this->setting['backup_passwd']) {
			$cmd .= ' -p' . $this->setting['backup_passwd'] . ' ';
		}

		$cmd .= $wget_file;
		$cmd .= ' -o' . $this->kangle_etc_dir;
		$cmd .= ' -aoa';

		if ($this->os == 'win') {
			$this->netstopCmd('kangle');
			$this->netstopCmd('linxftp');
		}
		else {
			$this->outCmd("/vhs/kangle/bin/kangle -q\n");
			exec('/vhs/kangle/bin/kangle -q');
		}

		exec($cmd, $out, $status);
		$this->outCmd("restore etc cmd=\n" . $cmd . "\n");
		if ($status != 0 && $status != 0 - 1) {
			print_r($out);

			if ($this->os == 'win') {
				$this->netstartCmd('kangle');
				$this->netstartCmd('linxftp');
			}
			else {
				$this->outCmd("/vhs/kangle/bin/kangle\n");
				exec('/vhs/kangle/bin/kangle');
			}

			$this->delLocalFile($wget_file);
			$this->showMsg("restore etc failed\n");
			return false;
		}

		if ($this->os == 'win') {
			$this->netstartCmd('kangle');
			$this->netstartCmd('linxftp');
		}
		else {
			$this->outCmd("/vhs/kangle/bin/kangle\n");
			exec('/vhs/kangle/bin/kangle');
		}

		$this->delLocalFile($wget_file);
		return true;
	}

	private function netstartCmd($server_name)
	{
		$this->outCmd('net start ' . $server_name . "\n");
		exec('net start ' . $server_name);
	}

	private function netstopCmd($server_name)
	{
		$this->outCmd('net stop ' . $server_name . "\n");
		exec('net stop ' . $server_name);
	}

	private function restoreWeb($vh)
	{
		$is_first = true;
		$list_file = $vh['doc_root'] . '/' . $vh['name'] . '.txt';

		foreach ($this->restore_dir as $d) {
			if ($d == $this->z7outtmpdirname) {
				continue;
			}

			$this->showMsg('restore dir ' . $d . " ...\n");
			$file = $this->backup_dir . $d . '/' . $vh['name'] . '.web.7z.001';

			if (!file_exists($file)) {
				$this->showMsg($file . " not found\n");
				continue;
			}

			$cmd = $this->get7zCmd();
			$cmd .= ' ' . $file;
			$cmd .= ' -o' . $vh['doc_root'] . ' ' . $vh['name'] . '.txt';
			$cmd .= ' -aoa';
			exec($cmd, $out, $status);
			$this->outCmd("restoreweb cmd=\n");
			$this->outCmd($cmd);
			$this->outCmd("\n");
			$call = 'restore';
			$attr['file'] = $file;
			$attr['out_dir'] = $vh['doc_root'];
			$attr['mode'] = '-aos';

			if ($this->setting['backup_passwd']) {
				$attr['passwd'] = '-p' . $this->setting['backup_passwd'];
			}

			if (!$is_first) {
				$attr['listfile'] = '@' . $list_file;
			}

			$is_first = false;

			if (!file_exists($list_file)) {
				$this->showMsg($list_file . " not found\n");
				continue;
			}

			$result = apicall('shell', 'whmshell', array($call, $vh['name'], $attr));
			$session = $result->get('session');
			$code = $this->shellResult($session, $vh['name']);

			if ($code == '201') {
				$this->showMsg("restore In progress Please wait.....(201)\n");
				sleep(2);
				$code = $this->shellResult($session, $vh['name']);
			}

			if ($code == '200') {
				$this->showMsg("restore in progress result code=200\n");
				$this->delLocalFile($list_file);
			}
			else {
				$this->showMsg('shell call ' . $call . ' result ' . $code . "\n");
				continue;
			}

			$this->showMsg('restore dir ' . $d . "...done\n");
		}
	}

	private function shellResult($session, $vh)
	{
		$result = apicall('shell', 'query', array($session, $vh));

		if ($result === false) {
			return false;
		}

		$code = $result->getCode();
		return $code;
	}

	/**
	 * 需要设置下载文件临时存放目录
	 * Enter description here ...
	 * @param  $vh
	 * @param  $dir ftp下载目录
	 */
	private function wgetFtpfile($file, $dir)
	{
		if ($this->setting['backup_save_place'] != 'r') {
			copy($this->setting['backup_dir'] . $dir . '/' . $file, $this->setting['backup_dir'] . '/' . $file);
			return $this->setting['backup_dir'] . '/' . $file;
		}

		$suffix_file = substr($file, 0 - 4);
		$base_file = substr($file, 0, strlen($file) - 4);
		$this->delLocalFile($this->setting['backup_dir'] . $file);
		$i = 1;

		while ($i < 1000) {
			$new_file = $base_file . sprintf('.%03d', $i);

			if ($this->setting['backup_save_place'] == 'r') {
				$ftpcon = $this->getFtpCon();

				if (!$ftpcon) {
					$this->showMsg("cann't connect ftp server for wgetftpfile\n");
					return false;
				}

				ftp_chdir($ftpcon, '/' . $this->setting['ftp_dir'] . '/' . $dir);
				$get_result = ftp_get($ftpcon, $this->setting['backup_dir'] . $new_file, $new_file, FTP_BINARY);
				ftp_close($ftpcon);

				if (!$get_result) {
					if ($i == 1) {
						$this->showMsg('cann\'t get file ' . $new_file . " for wgetftpfile\n");
						return false;
					}

					break;
				}
			}
			else {
				if (!file_exists($this->setting['backup_dir'] . $dir . '/' . $new_file)) {
					break;
				}

				if (!copy($this->setting['backup_dir'] . $dir . '/' . $new_file, $this->setting['backup_dir'] . $new_file)) {
					return false;
				}
			}

			++$i;
		}

		return $this->setting['backup_dir'] . $file;
	}

	private function getRestorDir($ftp_dirs)
	{
		array_multisort($ftp_dirs, SORT_DESC);
		$count = count($ftp_dirs);
		$start_index = false;
		$i = 0;

		while ($i < $count) {
			if ($ftp_dirs[$i] == $this->dir) {
				$start_index = $i;
				break;
			}

			++$i;
		}

		if ($start_index === false) {
			return false;
		}

		$i = $start_index;

		while ($i < $count) {
			$dir = $ftp_dirs[$i];
			$restore_dir[] = $dir;

			if (substr($dir, 0 - 1, 1) == 'f') {
				break;
			}

			++$i;
		}

		return $restore_dir;
	}

	/**
	 * 获取保存目录下的所有备份目录
	 * @param unknown_type $ftp_dir
	 * @return Ambigous <boolean, multitype:string >|boolean|multitype:
	 */
	public function listFtpDirFile($ftp_dir)
	{
		if ($this->setting['backup_save_place'] != 'r') {
			return $this->listLocalDirFile($this->setting['backup_dir']);
		}

		if (!($ftpcon = $this->getFtpCon())) {
			$this->showMsg("not ftpcon\n");
			return false;
		}

		if (!@ftp_chdir($ftpcon, $ftp_dir)) {
			return false;
		}

		$listfile = ftp_nlist($ftpcon, '.');
		ftp_close($ftpcon);
		return $listfile;
	}

	public function listLocalDirFile($dir)
	{
		$rd = @opendir($dir);

		if (!$rd) {
			return false;
		}

		$result = array();

		while (($file = readdir($rd)) !== false) {
			if ($file == '.' || $file == '..' || $file == 'tmp') {
				continue;
			}

			if (is_dir($dir . '/' . $file)) {
				$result[] = $file;
			}
		}

		return $result;
	}

	private function getFtpCon()
	{
		if ($this->ftpconnect !== false && $this->ftpconnect != null) {
			return $this->ftpconnect;
		}

		$ftpcon = ftp_connect($this->setting['ftp_host'], $this->setting['ftp_port']);

		if (!$ftpcon) {
			$this->showMsg('can\'t connect this ftp host ' . $this->setting['ftp_host'] . " for getftpcon\n");
			return false;
		}

		$ftplogin = ftp_login($ftpcon, $this->setting['ftp_user'], $this->setting['ftp_passwd']);

		if (!$ftplogin) {
			$this->showMsg('can\'t login this ftp ' . $this->setting['ftp_host'] . " for getftpcon\n");
			return false;
		}

		$this->ftpconnect = $ftpcon;
		ftp_pasv($ftpcon, true);
		return $ftpcon;
	}

	private function showMsg($msg)
	{
		echo $msg;
	}

	private function outCmd($cmd)
	{
		if ($this->setting['outcmd'] == 'out') {
			echo $cmd;
		}
	}

	private function restoreCompetence()
	{
		if ($this->os == 'lin') {
			$cmd = 'chmod 700 ' . $this->backup_dir . ' -R *';
		}
		else {
			$cmd = 'cacls ' . $this->backup_dir . ' /E /D  users';
		}

		exec($cmd);
	}

	private function changeCompetence()
	{
		if ($this->os != 'win') {
			$cmd = 'chmod  755 ' . $this->backup_dir . ' -R *';
		}
		else {
			$cmd = 'cacls ' . $this->backup_dir . ' /t /E /g users:r';
		}

		exec($cmd);
	}
}

?>