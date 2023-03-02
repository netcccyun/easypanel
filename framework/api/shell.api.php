<?php
class ShellAPI extends API
{
	private $setting;

	public function cdn_sync($argv)
	{
		$fp = fopen('php://stdin', 'r');
		$stdin = '';

		if (0 < strlen($stdin)) {
			$argv = explode("\n", $stdin);
		}

		$result = apicall('cdnPrimary', 'sync', array($argv));
		print_r($result);
	}

	/**
	 * 20120710
	 * 修改linux下,升级商业版本,会变更/vhs/kangle/etc目录权限为755的问题.这将导致php木马可以下载etc下的任何文件
	 * 该项问题已在商业版的install.sh安装中修复.为防以前版本的问题,特加此函数重新将权限改为700
	 * Enter description here ...
	 */
	public function changeEtcPermissions()
	{
		if (strncasecmp(PHP_OS, 'lin', 3) == 0) {
			if (file_exists('/vhs/kangle/etc')) {
				if ($this->setting['cron_count']) {
					if ($this->setting['cron_count'] % 12 == 0) {
						exec('chmod 700 /vhs/kangle/etc');
						return NULL;
					}
				}
				else {
					exec('chmod 700 /vhs/kangle/etc');
				}
			}
		}
	}

	/**
	 *  20120710
	 * 查询数据库使用情况开关.
	 * 每天执行一次 check_db_used
	 * Enter description here ...
	 */
	public function check_db_run()
	{
		$setting = $this->setting ? $this->setting : daocall('setting', 'getAll', array());

		if (!$setting['cron_count']) {
			daocall('setting', '_setStackValue', array('cron_count', 1));
		}

		if ($setting['cron_count'] % 288 == 0) {
			daocall('setting', 'add', array('check_db_count', $setting['cron_count']));
			$this->check_db_used();
		}
	}

	/**
	 * 20120710
	 * 查询数据库的使用情况,及关停空间.
	 * Enter description here ...
	 */
	public function check_db_used()
	{
		$db_status = 3;
		$dbobj = apicall('nodes', 'makeDbProduct', array('localhost'));
		if(!$dbobj) return false;
		$uses = $dbobj->getAllUsed();
		$dbs = daocall('vhost', 'getListvhost', array('db_use'));
		$count = count($dbs);

		if ($count < 0) {
			return false;
		}

		foreach ($dbs as $db) {
			foreach ($uses as $use) {
				if ($db['name'] == $use['name']) {
					if ($db['db_quota'] < $use['size'] / 1048576) {
						echo $db['db_quota'] . '<' . $use['size'] / 1048576 . '<br>';

						if ($db['status'] == 0) {
							apicall('vhost', 'changeStatus', array('localhost', $db['name'], $db_status));
							echo 'Pause vhost =' . $db['name'] . '<br>';
						}
					}
					else {
						if ($db['status'] === $db_status) {
							apicall('vhost', 'changeStatus', array('localhost', $db['name'], 0));
							echo 'Reopen vhost = ' . $db['name'] . '<br>';
						}
					}
				}
			}
		}
	}

	private function build_vh($vh)
	{
		$self_ip = gethostbyname($_SERVER['SERVER_NAME']);
		$local_name = $this->setting['local_cdn_name'];

		if (!$local_name) {
			$local_name = $self_ip;
		}

		$domains = daocall('vhostinfo', 'getDomain', array($vh['name']));
		$str = '<vh name=\'@' . $local_name . ':' . $vh['name'] . '\' doc_root=\'www\' status=\'' . $vh['status'] . "' inherit='off'>\n";

		if (is_array($domains)) {
			foreach ($domains as $domain) {
				$value = $domain['value'];

				if (strncasecmp($value, 'http://', 7) != 0) {
					$value = 'http://' . $self_ip . '/';
				}

				$str .= '	<host dir=\'' . $value . '\'>' . $domain['name'] . "</host>\n";
			}
		}

		$str .= "</vh>\n";
		return $str;
	}

	public function list_cdn_vh()
	{
		$vhs = daocall('vhost', 'listVhost', array(null));
		$str = "<vhs>\n";

		foreach ($vhs as $vh) {
			$result_str = $this->build_vh($vh);
			$str .= $result_str;
		}

		$str .= "</vhs>\n";
		$str .= "<!--configfileisok-->\n";
		$cdnstr['msg'] = $str;
		return $cdnstr;
	}

	public function sync_expire()
	{
		$vhosts = daocall('vhost', 'ListByExpiretime', array(0));

		if ($vhosts) {
			foreach ($vhosts as $vhost) {
				if (!($return = apicall('vhost', 'changeStatus', array('localhost', $vhost['name'], 1)))) {
					echo 'sync_expire ';
					echo $vhost['name'] . " failed<---sync failed--->\r\n";
					continue;
				}

				echo 'sync_expire ';
				echo $vhost['name'] . " success\r\n";
			}
		}

		echo "nothing vhost need sync_expire\r\n";
	}

	/**
	 * 超流量关停
	 * Enter description here ...
	 */
	public function check_flow()
	{
		echo "check flow start......\r\n";

		if ($this->setting == null) {
			$this->setting = daocall('setting', 'getAll', array());
		}

		if ($this->setting['kangle_type'] != 'enterprise') {
			return true;
		}

		$list_vhosts = daocall('vhost', 'getListvhost', array('flow_limit'));

		if (count($list_vhosts) < 0) {
			return true;
		}

		$t = date('Ym');
		$list_flows = apicall('flow', 'getListflow', array('flow_month', $t));

		if (count($list_flows) < 0) {
			return true;
		}

		$flow_stop_status = 2;

		foreach ($list_vhosts as $list_vhost) {
			$result = apicall('flow', 'getMonthFlow', array($list_vhost['name'], $t));
			$month_flow = $result['flow'] / 1024;
			if (0 < $list_vhost['flow_limit'] && $list_vhost['flow_limit'] * 1024 <= $month_flow) {
				if ($list_vhost['status'] == 0) {
					apicall('vhost', 'changeStatus', array('localhost', $list_vhost['name'], $flow_stop_status));
					echo 'change ' . $list_vhost['name'] . " status successfuly\r\n";
				}
			}
			else {
				if ($list_vhost['status'] == $flow_stop_status) {
					apicall('vhost', 'changeStatus', array('localhost', $list_vhost['name'], 0));
				}
			}
		}

		echo "check flow stop......\r\n";
	}

	/**
	 * 远程调用
	 * 将本节点的cdn设置在取得辅节点流量时，post过去。
	 * 主节点同步所有主机的流量,后台shell执行流量统计
	 */
	public function sync_flow()
	{
		daocall('setting', '_setStackValue', array('cron_count', 1));
		$setting = daocall('setting', 'getAll', array());
		$this->setting = $setting;
		$t = date('YmdH', time(NULL));
		$month = substr($t, 0, 6);
		$day = substr($t, 0, 8);
		$hour = $t;
		$nodes = daocall('manynode', 'get');
		$dbname = 'global.db';
		apicall('cdnPrimary', 'sync_all');
		$this->sync_expire();
		$this->check_flow();
		$this->check_db_run();
		$this->backup();
		$this->changeEtcPermissions();
	}

	/**
	 * 主节点远程调用辅节点,得到辅节点流量
	 * $node ip
	 * @param  $node
	 * @param  $t
	 *
	 */
	public function sync_host_flow($node, $t, $month, $day, $hour, $poststr)
	{
		$opts = array(
			'http' => array('timeout' => 120, 'method' => 'POST', 'content' => http_build_query($poststr, '', '&'))
			);
		daocall('manynode', 'updateManynode', array(
	$node['host'],
	array('synctime' => time(), 'syncstatus' => 0)
	));
		$url = 'http://' . $node['host'] . ':'.$node['port'].'/api/?c=cdn&a=getflow';
		srand((double) microtime() * 1000000);
		$setting = daocall('setting', 'getAll', array());
		$skey = $node['skey'];

		if (!$skey) {
			$skey = $this->setting['skey'];
		}

		$nodename = $setting['local_cdn_name'];
		$url .= '&nodename=' . $nodename;
		$r = time() . rand();
		$s = md5('getflow' . $skey . $r);
		$url .= '&r=' . $r . '&s=' . $s;
		echo 'url=';
		echo $url . "\r\n";
		$result = @file_get_contents($url, false, stream_context_create($opts));

		if (!$result) {
			echo "sync_host_flow not result\r\n";
			return false;
		}

		daocall('manynode', 'updateManynode', array(
	$node['host'],
	array('synctime' => time(), 'syncstatus' => 1)
	));
		$flow = explode('=200|', $result);
		$lines = explode("\n", $flow[1]);

		if (count($lines) < 0) {
			echo "sync_host_flow not flow to lines\r\n";
			return false;
		}

		$this->insert_flow($lines, $hour, $day, $month, 'global.db');
	}

	/**
	 * 由于一个节点可能是多个主节点的辅节点，并且它还可能是主节点
	 * 本节点作辅节点时，主机名字同步过来是@打头，所以传入@查询辅节点的账号名。存储本地的流量，用于辅节点的流量查询
	 * 主节点存储一份流量global.db，辅节点自已再存储一份flow.db。
	 * @param $db_name
	 * 如果是主节点，则写入到global.db，如果辅节点，则写入到flow.db
	 * @param #db_name2
	 * @param $prefix
	 * 如果是作主节点，主节点的虚拟主机名字没有@打头，则为空，如果是辅节点，则要传入@
	 * @param $revers 0翻转prefix,1为翻转不是prefix,1为取本机时使用
	 */
	public function sync_localhost_flow($db_name, $db_name2 = null, $prefix, $revers = 0)
	{
		$t = date('YmdH', time(NULL));
		$month = substr($t, 0, 6);
		$day = substr($t, 0, 8);
		$hour = $t;
		$result = apicall('cdn', 'getFlow', array($prefix, $revers));

		if (!$result) {
			echo "sync_host_flow not result\r\n";
			return false;
		}

		$flows = (string) $result->get('flow', 0);
		$lines = explode("\n", $flows);

		if (count($lines) < 0) {
			echo "sync_localhost_flow not flow to lines\r\n";
			return false;
		}

		$this->insert_flow($lines, $hour, $day, $month, $db_name);

		if ($db_name2) {
			$this->insert_flow($lines, $hour, $day, $month, $db_name2);
		}
	}

	/**
	 * 插入数据库
	 * @param $lines array()
	 * @param $hour
	 * @param $day
	 * @param $month
	 * @param $db_name global.db || flow.db
	 * @param $prefix @ || ""
	 * @param $prfix_len int || 0
	 */
	private function insert_flow($lines = array(), $hour, $day, $month, $db_name)
	{
		load_lib('pub:flow');
		$flowobj = new flow($db_name);

		foreach ($lines as $line) {
			if (strlen($line) < 2) {
				continue;
			}

			$item = explode('	', $line);
			if (strcasecmp($item[0][0], '@') == 0 && ($s = explode(':', $item[0]))) {
				$name = $s[1];
			}
			else {
				$name = $item[0];
			}

			$flow = $item[1];
			$count = $item[2];
			echo $name . ' flow=' . $flow . ' db_name=' . $db_name . "\n";
			$flowobj->addFlow('flow_hour', $name, $hour, $flow);
			$flowobj->addFlow('flow_day', $name, $day, $flow);
			$flowobj->addFlow('flow_month', $name, $month, $flow);
		}
	}

	/**
	 * 重建本节点所有网站
	 */
	public function sync_all_vhost()
	{
		$vhs = daocall('vhost', 'listVhost', array());

		if (!is_array($vhs)) {
			echo "Nothing vhosts need sync\r\n";
			return false;
		}

		foreach ($vhs as $vh) {
			if (apicall('vhost', 'resync', array($vh['name']))) {
				echo 'sync  ' . $vh['name'] . "  success\n";
			}
			else {
				echo 'sync  ' . $vh['name'] . "  failed\n";
			}
		}
	}

	public function restore($params)
	{
		return apicall('restore', 'restore', array($params[0], $params[1]));
	}

	public function backup($argv = null)
	{
		return apicall('backup', 'backup', array($argv));
	}

	public function cron_add()
	{
		return apicall('cron', 'install_system_cron', array());
	}

	/**
	 * whm shell调用
	 * @param $call
	 * @param $attr
	 * @return WhmResult
	 */
	public function whmshell($call, $vh, $attr = array())
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('shell.whm', $call);

		if ($vh) {
			$whmCall->addParam('vh', $vh);
		}

		if (is_array($attr)) {
			foreach ($attr as $k => $v) {
				$whmCall->addParam($k, $v);
			}
		}

		return $whm->call($whmCall);
	}

	/**
	 *
	 * 查询whm shell
	 * @param  $session
	 * @param  $vh
	 */
	public function query($session, $vh)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('shell.whm', 'query');

		if ($vh) {
			$whmCall->addParam('vh', $vh);
		}

		$whmCall->addParam('session', $session);
		return $whm->call($whmCall);
	}

	/**
	 * 中止一个whm shell(还未实现)
	 * @param $session
	 * @param $vhost
	 */
	public function terminate($session, $vh)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('shell.whm', 'terminate');

		if ($vh) {
			$whmCall->addParam('vh', $vh);
		}

		$whmCall->addParam('session', $session);
		return $whm->call($whmCall);
	}
}

?>