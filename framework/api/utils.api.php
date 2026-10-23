<?php
class UtilsAPI extends API
{
	public function createDnsDb()
	{
		$dns_file = $GLOBALS['safe_dir'] . 'dns.db';

		if (!file_exists($dns_file)) {
			$pdo = ep_new_pdo('sqlite:' . $dns_file);
			$sqls = array("CREATE TABLE [domains] (\r\n\t\t\t\t\t[name] VARCHAR(255)  PRIMARY KEY NULL,\r\n\t\t\t\t\t[passwd] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[salt] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[status] INTEGER  NULL,\r\n\t\t\t\t\t[max_record] INTEGER  NULL,\r\n\t\t\t\t\t[server] VARCHAR(255)  NULL\r\n\t\t\t)", "CREATE TABLE [records] (\r\n\t\t\t\t\t[id] INTEGER  PRIMARY KEY AUTOINCREMENT NOT NULL,\r\n\t\t\t\t\t[domain] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[name] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[type] VARCHAR(16)  NULL,\r\n\t\t\t\t\t[value] TEXT  NULL,\r\n\t\t\t\t\t[view] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[ttl] INTEGER  NULL,\r\n\t\t\t\t\t[status] INTEGER DEFAULT '0' NULL,\r\n\t\t\t\t\t[prio] INTEGER  NULL,\r\n\t\t\t\t\t[change_date] TIMESTAMP  NULL\r\n\t\t\t)", "CREATE TABLE [servers] (\r\n\t\t\t\t\t[server] VARCHAR(255)  PRIMARY KEY NOT NULL,\r\n\t\t\t\t\t[ns] VARCHAR(255)  NULL\r\n\t\t\t)", "CREATE TABLE [slaves] (\r\n\t\t\t\t\t[server] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[slave] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[ns] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[skey] VARCHAR(255)  NULL,\r\n\t\t\t\t\tPRIMARY KEY ([server],[slave])\r\n\t\t\t)", "CREATE TABLE [views] (\r\n\t\t\t\t\t[name] VARCHAR(255)  PRIMARY KEY NULL,\r\n\t\t\t\t\t[desc] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[key] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[id] INTEGER  UNIQUE NOT NULL\r\n\t\t\t)");

			foreach ($sqls as $sql) {
				$pdo->exec($sql);
			}

			return true;
		}
	}

	public function checkInput($str)
	{
		$str = str_replace('"', '', $str);
		$str = str_replace('\'', '', $str);
		$str = str_replace('\\', '', $str);
		$str = str_replace('{', '', $str);
		return $str;
	}

	/**
	 * ipv4验证
	 * @param unknown_type $str
	 * @return boolean
	 */
	public function is_ipv4($str)
	{
		$p = array();
		preg_match('/^http\\:\\/\\/\\d+?[.]\\d+?[.]\\d+?[.]\\d+?[\\/]?$|\\d+?[.]\\d+?[.]\\d+?[.]\\d+?/', $str, $p);
		return $p ? true : false;
	}

	/**
	 * 获取mac地址
	 * @return unknown
	 */
	public function getMac()
	{
		if (is_win()) {
			$macarray = $this->getMacForWin();
		}
		else {
			$macarray = $this->getMacForLin();
		}

		$macarray = is_array($macarray) ? $macarray : array();
		$temp_array = array();
		$macaddr = '';

		foreach ($macarray as $value) {
			if (preg_match('/[0-9a-f][0-9a-f][:-]' . '[0-9a-f][0-9a-f][:-]' . '[0-9a-f][0-9a-f][:-]' . '[0-9a-f][0-9a-f][:-]' . '[0-9a-f][0-9a-f][:-]' . '[0-9a-f][0-9a-f]/i', $value, $temp_array)) {
				$macaddr = $temp_array[0];
				break;
			}
		}

		unset($temp_array);
		return $macaddr;
	}

	/**
	 * 获取windows的mac地址
	 * @return unknown
	 */
	private function getMacForWin()
	{
		$macarr = array();
		@exec('ipconfig /all', $macarr);

		if (!$macarr) {
			$ipconfig = $_SERVER['WINDIR'] . '\\system32\\ipconfig.exe';

			if (is_file($ipconfig)) {
				@exec($ipconfig . ' /all', $macarr);
			}
			else {
				@exec($_SERVER['WINDIR'] . '\\system\\ipconfig.exe /all', $macarr);
			}
		}

		return $macarr;
	}

	/**
	 * 获取linux的mac地址
	 * @return unknown
	 */
	private function getMacForLin()
	{
		$macarr = array();
		@exec('ifconfig -a', $macarr);
		return $macarr;
	}

	/**
	 * 删除smarty缓存目录
	 * Enter description here ...
	 * @param dir $temp_dir
	 */
	public function delTempleteFile($temp_dir = null)
	{
		if (!$temp_dir) {
			$view_dir = daocall('setting', 'get', array('view_dir'));
			$view_dir = $view_dir ? $view_dir : 'default';
			$temp_dir = dirname(__FILE__) . '/../templates_c/' . $view_dir . '/';
		}

		$op = opendir($temp_dir);

		if (!$op) {
			return false;
		}

		while (($file = readdir($op)) !== false) {
			if ($file != '.' && $file != '..') {
				$path = $temp_dir . $file;
				if (is_dir($path) && !is_link($path)) {
					$this->delTempleteFile($path . '/');
				}
				else {
					unlink($path);
				}
			}
		}

		closedir($op);
		rmdir($temp_dir);
	}

	/**
	 * speed.ctl
	 * 由于在限速时limit，和speed_limit项在kangle:3311里会自动转换单位，所以ep取回数据需要对数据识别
	 * Enter description here ...
	 * @param string or int $size
	 */
	public function get_size($size)
	{
		$last_str = substr($size, 0 - 1);

		if (strcasecmp($last_str, 'k') == 0) {
			return substr($size, 0, 0 - 1) * 1024;
		}

		if (strcasecmp($last_str, 'm') == 0) {
			return substr($size, 0, 0 - 1) * 1024 * 1024;
		}

		if (strcasecmp($last_str, 'g') == 0) {
			return substr($size, 0, 0 - 1) * 1024 * 1024 * 1024;
		}

		return intval($size);
	}

	public function reboot_system()
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('vhost.whm', 'reboot_system');
		return $whm->call($whmCall, 10);
	}

	/**
	 * 安装vhms时，为防数据冲突<重名>将本节点数据同步到vhms数据库，
	 * Enter description here ...
	 * @param $db_host
	 * @param $db_user
	 * @param $db_passwd
	 * @param $db_name
	 * @param $user
	 * @param $db_port
	 */
	public function syncVhost($db_host, $db_user, $db_passwd, $db_name, $user, $db_port = '3306')
	{
		$vhs = daocall('vhost', 'getAllVhostByProduct_id', array());

		if (is_array($vhs)) {
			$dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name;
			$pdo = ep_new_pdo($dsn, $db_user, $db_passwd, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

			if (!$pdo) {
				exit('pdo not connect');
			}

			foreach ($vhs as $vh) {
				$name = $vh['name'];
				$passwd = 'null';
				$doc_root = $vh['doc_root'];
				$gid = '1100';
				$templete = $vh['templete'];
				$subtemplete = $vh['subtemplete'];
				$create_time = date('Y-m-d H:i:s', $vh['create_time']);
				$expire_time = date('Y-m-d H:i:s', $vh['create_time'] + 31536000);
				$status = $vh['status'];
				$node = '';
				$product_id = '0';
				$username = $user;
				$flow = $vh['flow_limit'] ? $vh['flow_limit'] : '0';
				$sql = 'insert into vhost(name,passwd,doc_root,gid,templete,subtemplete,create_time,expire_time,status,node,product_id,username,flow) ';
				$sql .= 'values(\'' . str_replace('\'', '\'\'', ep_str($name)) . '\',\'' . str_replace('\'', '\'\'', ep_str($passwd)) . '\',\'' . str_replace('\'', '\'\'', ep_str($doc_root)) . '\',\'' . str_replace('\'', '\'\'', ep_str($gid)) . '\',\'' . str_replace('\'', '\'\'', ep_str($templete)) . '\',\'' . str_replace('\'', '\'\'', ep_str($subtemplete)) . '\',\'' . str_replace('\'', '\'\'', ep_str($create_time)) . '\',\'' . str_replace('\'', '\'\'', ep_str($expire_time)) . '\',\'' . str_replace('\'', '\'\'', ep_str($status)) . '\'';
				$sql .= ',\'' . str_replace('\'', '\'\'', ep_str($node)) . '\',\'' . str_replace('\'', '\'\'', ep_str($product_id)) . '\',\'' . str_replace('\'', '\'\'', ep_str($username)) . '\',\'' . str_replace('\'', '\'\'', ep_str($flow)) . '\')';

				if ($pdo->query($sql)) {
					echo 'sync ';
					echo $vh['name'];
					echo ' success<br>';
				}
				else {
					echo '<font color=red>sync ';
					echo $vh['name'];
					echo ' failed</font><br>';
				}
			}
		}
	}

	public function fixPriv($db_host, $db_user, $old_passwd, $db_passwd, $db_port = 3306)
	{
		if (!$db_host) {
			$db_host = 'localhost';
		}

		if ($db_user == '') {
			return true;
		}

		$db_port = (int) $db_port;
		if ($db_port < 1 || $db_port > 65535) {
			$GLOBALS['last_error'] = '数据库端口无效';
			return false;
		}
		$db_passwd = (string) $db_passwd;
		$driver = new mysqli_driver();
		$report = $driver->report_mode;
		$connect = false;
		$verify = false;
		mysqli_report(MYSQLI_REPORT_OFF);

		try {
			// 保留 old_passwd 参数供现有调用使用，但普通保存只校验填写的密码。
			$passwords = array($db_passwd);
			// 只有本地账号仍能用空密码认证时，才允许首次设置密码。
			if (in_array(strtolower($db_host), array('localhost', '127.0.0.1', '::1'), true)) {
				$passwords[] = '';
			}
			foreach (array_unique($passwords) as $password) {
				$connect = @mysqli_connect($db_host, $db_user, $password, null, $db_port);
				if ($connect) {
					$connected_password = $password;
					break;
				}
			}
			if (!$connect) {
				$GLOBALS['last_error'] = '数据库连接失败（错误码 ' . mysqli_connect_errno() . '），请检查账号密码';
				return false;
			}

			if ($connected_password === '' && $db_passwd !== '') {
				$result = mysqli_query($connect, 'SELECT CURRENT_USER() AS account_name');
				if (!$result) {
					$GLOBALS['last_error'] = '无法读取当前数据库账号';
					return false;
				}
				$row = mysqli_fetch_assoc($result);
				mysqli_free_result($result);
				$separator = isset($row['account_name']) ? strrpos($row['account_name'], '@') : false;
				if ($separator === false) {
					$GLOBALS['last_error'] = '当前数据库账号格式无效';
					return false;
				}
				// 修改实际认证的账号，不创建 root@%，不删除 host，不直接更新权限表。
				$user = substr($row['account_name'], 0, $separator);
				$host = substr($row['account_name'], $separator + 1);
				if (!$this->changeDbPassword($connect, $user, $host, $db_passwd)) {
					return false;
				}
			}

			// 必须用填写的密码重新连接成功，调用方才可以保存 EP 的数据库配置。
			$verify = @mysqli_connect($db_host, $db_user, $db_passwd, null, $db_port);
			if (!$verify) {
				$GLOBALS['last_error'] = '数据库密码校验失败（错误码 ' . mysqli_connect_errno() . '）';
				return false;
			}
			return true;
		}
		finally {
			if ($verify) {
				mysqli_close($verify);
			}
			if ($connect) {
				mysqli_close($connect);
			}
			mysqli_report($report);
		}
	}

	/**
	 * 修改当前连接实际认证的账号密码；旧服务器不支持 ALTER USER 时才回退 SET PASSWORD。
	 */
	private function changeDbPassword($connect, $db_user, $db_host, $db_passwd)
	{
		$user = mysqli_real_escape_string($connect, $db_user);
		$host = mysqli_real_escape_string($connect, $db_host);
		$passwd = mysqli_real_escape_string($connect, $db_passwd);
		$account = '\'' . $user . '\'@\'' . $host . '\'';

		if (mysqli_query($connect, 'ALTER USER ' . $account . ' IDENTIFIED BY \'' . $passwd . '\'')) {
			return true;
		}

		$error = mysqli_errno($connect);
		if (in_array($error, array(1064, 1235), true)) {
			if (mysqli_query($connect, 'SET PASSWORD FOR ' . $account . ' = PASSWORD( \'' . $passwd . '\' )')) {
				return true;
			}
			$error = mysqli_errno($connect);
		}
		$GLOBALS['last_error'] = '数据库密码设置失败（错误码 ' . $error . '），请检查账号权限及密码策略';
		return false;
	}

	public function mergeKeyword($keyword)
	{
		$va = '';
		foreach (ep_iter($keyword) as $key) {
			$va = '(' . $key['value'] . ')' . '|' . $va;
		}

		return trim($va, '|');
	}

	public function writeConfig($nodes, $keyname, $cfg_name, $dir = null)
	{
		if ($dir == null) {
			$dir = dirname(dirname(__FILE__)) . '/configs/';
		}

		@mkdir($dir);
		$file = $dir . $cfg_name . '.cfg.php';
		$fp = fopen($file, 'wt');

		if (!$fp) {
			return trigger_error('cann\'t open ' . $file . ' to write!Please check right');
		}

		fwrite($fp, "<?php\r\n");

		foreach ($nodes as $node) {
			$this->writeItemConfig($fp, $node, $keyname, $cfg_name);
		}

		fwrite($fp, '?>');
		fclose($fp);
		@chmod($file, 384);
		return true;
	}

	private function writeItemConfig($fp, $node, $keyname, $cfg_name)
	{
		$str = '$GLOBALS[\'' . $cfg_name . '_cfg\'][\'' . $node[$keyname] . '\']=array(';
		$item = '';
		$keys = array_keys($node);
		$i = 0;

		while ($i < count($keys)) {
			$key = $keys[$i];
			$value = $node[$key];

			if ($item != '') {
				$item .= ',';
			}

			$item .= '\'' . $key . '\'=>"' . addcslashes(ep_str($value), '\\"$') . '"';
			++$i;
		}

		$str .= $item . ");\r\n";
		fwrite($fp, $str);
	}

	public function xmlencode($str)
	{
		$len = strlen($str);
		$i = 0;
		$dst = '';

		while ($i < $len) {
			switch ($str[$i]) {
			case '\'':
				$dst .= '&#39;';
				break;

			case '"':
				$dst .= '&#34;';
				break;

			case '&':
				$dst .= '&amp;';
				break;

			case '>':
				$dst .= '&gt;';
				break;

			case '<':
				$dst .= '&lt;';
				break;

			default:
				$dst .= $str[$i];
			}

			++$i;
		}

		return $dst;
	}

	public function klencode($str, $html = false)
	{
		if (!$html) {
			$str = str_replace('<', '&lt;', $str);
			$str = str_replace('>', '&gt;', $str);
		}

		$str = str_replace(chr(34), '&quot;', $str);
		$str = str_replace("\n", '<br>', $str);
		$str = str_replace('  ', ' &nbsp;', $str);
		return $str;
	}

	public function kldecode($msg, $html = false)
	{
		$msg = str_replace('<br />', chr(10), $msg);
		$msg = str_replace('<br>', chr(10), $msg);
		$msg = str_replace('&quot;', chr(34), $msg);
		$msg = str_replace('&nbsp;', ' ', $msg);

		if (!$html) {
			$msg = str_replace('&lt;', '<', $msg);
			$msg = str_replace('&gt;', '>', $msg);
		}

		return $msg;
	}

	public function writeDomainConfig($fp, $domain_array, $del_domain = null)
	{
		$str = "<?php\n";
		$str .= '$GLOBALS[\'reserv_domain\'] = array(';

		foreach ($domain_array as $domain) {
			if ($del_domain != null) {
				if ($domain != $del_domain) {
					$str .= '\'' . $domain . '\',';
				}
			}
			else {
				$str .= '\'' . $domain . '\',';
			}
		}

		$str .= ');';
		$str .= "\n?>";
		fwrite($fp, $str);
	}
}
