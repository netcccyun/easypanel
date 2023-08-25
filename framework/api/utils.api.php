<?php
class UtilsAPI extends API
{
	public function createDnsDb()
	{
		$dns_file = $GLOBALS['safe_dir'] . 'dns.db';

		if (!file_exists($dns_file)) {
			$pdo = new PDO('sqlite:' . $dns_file);
			$sqls = array("CREATE TABLE [domains] (\r\n\t\t\t\t\t[name] VARCHAR(255)  PRIMARY KEY NULL,\r\n\t\t\t\t\t[passwd] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[salt] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[status] INTEGER  NULL,\r\n\t\t\t\t\t[max_record] INTEGER  NULL,\r\n\t\t\t\t\t[server] VARCHAR(255)  NULL\r\n\t\t\t)", "CREATE TABLE [records] (\r\n\t\t\t\t\t[id] INTEGER  PRIMARY KEY AUTOINCREMENT NOT NULL,\r\n\t\t\t\t\t[domain] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[name] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[type] VARCHAR(16)  NULL,\r\n\t\t\t\t\t[value] TEXT  NULL,\r\n\t\t\t\t\t[view] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[ttl] INTEGER  NULL,\r\n\t\t\t\t\t[status] INTEGER DEFAULT '0' NULL,\r\n\t\t\t\t\t[prio] INTEGER  NULL,\r\n\t\t\t\t\t[change_date] TIMESTAMP  NULL\r\n\t\t\t)", "CREATE TABLE [servers] (\r\n\t\t\t\t\t[server] VARCHAR(255)  PRIMARY KEY NOT NULL,\r\n\t\t\t\t\t[ns] VARCHAR(255)  NULL\r\n\t\t\t)", "CREATE TABLE [slaves] (\r\n\t\t\t\t\t[server] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[slave] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[ns] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[skey] VARCHAR(255)  NULL,\r\n\t\t\t\t\tPRIMARY KEY ([server],[slave])\r\n\t\t\t)", "CREATE TABLE [views] (\r\n\t\t\t\t\t[name] VARCHAR(255)  PRIMARY KEY NULL,\r\n\t\t\t\t\t[desc] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[key] VARCHAR(255)  NULL,\r\n\t\t\t\t\t[id] INTEGER  UNIQUE NOT NULL\r\n\t\t\t)");

			foreach ($sqls as $sql) {
				$pdo->exec($sql);
			}

			return true;
		}
	}

	public function getEntKey()
	{
		if (defined('ASDF_10_BVCX')) {
			return ASDF_10_BVCX . 'nBvc2tkZnBhc2tkLWYwaTIzPS1pbzIzNC0yNGtzcC0wZGZrYXNwLT';
		}

		return '';
	}

	public function checkEnt($domain)
	{
		$ent_check_file = '';

		if (!file_exists($ent_check_file)) {
			return false;
		}

		$f = file_get_contents($ent_check_file);

		if (!$f) {
			return false;
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

		$temp_array = array();

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
				rmdir($temp_dir . $file);

				if (is_dir($temp_dir . $file)) {
					if (substr($file, 0 - 1) != '/' || substr($file, 0 - 1) != '\\') {
						$dir_r = '/';
					}
					else {
						$dir_r = '';
					}

					$this->delTempleteFile($temp_dir . $file . $dir_r);
				}
				else {
					unlink($temp_dir . $file);
				}
			}
		}

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
			$pdo = new PDO($dsn, $db_user, $db_passwd, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

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
				$sql .= 'values(\'' . $name . '\',\'' . $passwd . '\',\'' . $doc_root . '\',\'' . $gid . '\',\'' . $templete . '\',\'' . $subtemplete . '\',\'' . $create_time . '\',\'' . $expire_time . '\',\'' . $status . '\'';
				$sql .= ',\'' . $node . '\',\'' . $product_id . '\',\'' . $username . '\',\'' . $flow . '\')';

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

	public function fixPriv($db_host, $db_user, $old_passwd, $db_passwd)
	{
		if (!$db_host) {
			$db_host = 'localhost';
		}

		if ($db_user != '') {
			$connect = mysqli_connect($db_host, $db_user, $db_passwd);

			if (!$connect) {
				$connect = mysqli_connect($db_host, $db_user, $old_passwd);

				if (!$connect) {
					return false;
				}
			}

			mysqli_query($connect, 'delete from `mysql`.`user` where `user`=\'' . $db_user . '\' and host!=\'%\' and host!=\'localhost\'');
			mysqli_query($connect, 'DELETE FROM `mysql`.`user` where `user`=\'\'');
			mysqli_query($connect, 'SET PASSWORD FOR \'' . $db_user . '\'@\'localhost\' = PASSWORD( \'' . $db_passwd . '\' )');
			mysqli_query($connect, 'SET PASSWORD FOR \'' . $db_user . '\'@\'%\' = PASSWORD( \'' . $db_passwd . '\' )');
			$roots = array('Create_tablespace_priv', 'Trigger_priv', 'Event_priv', 'Create_user_priv', 'Alter_routine_priv');
			$i = 0;

			while ($i < count($roots)) {
				mysqli_query($connect, 'update mysql.`user` set ' . $roots[$i] . '=\'Y\' where  `user`=\'' . $db_user . '\'');
				++$i;
			}

			mysqli_query($connect, 'flush privileges');
			mysqli_close($connect);
		}

		return true;
	}

	public function mergeKeyword($keyword)
	{
		foreach ($keyword as $key) {
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

			$item .= '\'' . $key . '\'=>"' . addcslashes($value, '\\"$') . '"';
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

?>