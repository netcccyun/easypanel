<?php
class CdnControl extends Control
{
	private $cdn_dir = 'cdn';
	private $cdn_prefix = '@';

	public function get_vhost_info()
	{
		$nodename = $_REQUEST['nodename'];
		$prefix = $this->cdn_prefix . $nodename . '_';
		$result = apicall('vhost', 'getflow', array($prefix, 0));
		$vhs = daocall('vhost', 'listCdnVhost', array(
	$prefix,
	array('name', 'sync_seq')
	));

		foreach ($vhs as $vh) {
			$vh['name'] = substr($vh['name'], strlen($prefix));
			$vh['flow_limit'] = 0;
			$vh['flow_cache'] = 0;
			$vh['sync_seq'] = intval($vh['sync_seq']);
			$vhs2[$vh['name']] = $vh;
		}

		if (is_object($result)) {
			$flows = (string) $result->get('flow', 0);

			if ($flows != '') {
				$fls = explode("\n", $flows);

				foreach ($fls as $fl) {
					$f = explode('	', $fl);

					if (1 < count($f)) {
						$vh_name = substr($f[0], strlen($prefix));
						$vhs2[$vh_name]['flow_limit'] = $f[1];
						$vhs2[$vh_name]['flow_cache'] = $f[2];
					}
				}
			}
		}

		$ret['vhs'] = json_encode($vhs2);
		$ret['ip'] = $_SERVER['REMOTE_ADDR'];
		whm_return(200, $ret);
	}

	/**
	 * v2.0.5
	 * 返回流量，并带本节点的所有虚拟主机,字段:name(虚拟主机名称)，sync_seq(同步版本)
	 * @deprecated use list_vhost
	 * Enter description here ...
	 */
	public function getflow2()
	{
		$nodename = $_REQUEST['nodename'];
		$prefix = $this->cdn_prefix . $nodename . '_';
		$result = apicall('cdn', 'getflow', array($prefix, 0));
		$vhs = daocall('vhost', 'listCdnVhost', array($prefix, null));

		if (count($vhs) <= 0) {
			whm_return(404);
		}

		foreach ($vhs as $vh) {
			$vh['flow_limit'] = 0;
			$vhs2[$vh['name']] = $vh;
		}

		$flows = (string) $result->get('flow', 0);

		if ($flows != '') {
			$fls = explode("\n", $flows);

			foreach ($fls as $fl) {
				$f = explode('	', $fl);

				if (1 < count($f)) {
					$vhs2[$f[0]]['flow_limit'] = $f[1];
				}
			}
		}

		$ret['vhs'] = json_encode($vhs2);
		whm_return(200, $ret);
	}

	public function get_version()
	{
		$ret['version'] = get_number_version();
		whm_return(200, $ret);
	}

	public function del_cdn()
	{
		$vhost = trim($_REQUEST['vhost']);
		$nodename = $_REQUEST['nodename'];
		$prefix = $this->cdn_prefix . $nodename . '_';
		$acess_dir = $GLOBALS['safe_dir'] . '../' . $this->cdn_dir . '/';
		daocall('vhost', 'delVhost', array($prefix . $vhost, null));
		daocall('vhostinfo', 'delAllInfo', array($prefix . $vhost));
		@unlink($acess_dir . $prefix . $vhost . '.xml');
		@unlink($acess_dir . $prefix . $vhost . '.crt');
		@unlink($acess_dir . $prefix . $vhost . '.key');
		apicall('vhost', 'noticeChange', array('localhost', $prefix . $vhost));
		whm_return(200);
	}

	/**
	 * 删除
	 * Enter description here ...
	 */
	public function del_all_cdn()
	{
		$nodename = $_REQUEST['nodename'];
		$prefix = $this->cdn_prefix . $nodename . '_';
		$len = strlen($prefix);
		$acess_dir = $GLOBALS['safe_dir'] . '../' . $this->cdn_dir . '/';
		daocall('vhost', 'delNodeCdn', array($prefix));
		daocall('vhostinfo', 'delNodeCdn', array($prefix));
		$op = opendir($acess_dir);

		while (($file = readdir($op)) !== false) {
			if ($file == '.' || $file == '..') {
				continue;
			}

			if (substr($file, 0, $len) == $prefix) {
				@unlink($acess_dir . $file);
			}
		}

		apicall('vhost', 'noticeChange', array('localhost'));
		whm_return(200);
	}

	public function sync()
	{
		$nodename = $_REQUEST['nodename'];
		$sync = (array) json_decode($_REQUEST['sync'], true);
		$del = (array) json_decode($_REQUEST['del'], true);
		$url = json_decode($_REQUEST['url'], true);
		apicall('cdnSlave', 'sync', array($nodename, $sync, $del, $url));
		whm_return(200);
	}

	/**
	 * @deprecated 请用新接口sync
	 * @return boolean
	 */
	public function sync_vhost_cdn()
	{
		$cdn_dir = 'cdn';
		$acess_dir = $GLOBALS['safe_dir'] . '../' . $this->cdn_dir . '/';

		if (!file_exists($acess_dir)) {
			@mkdir($acess_dir, '0700');
		}

		$nodename = $_REQUEST['nodename'];
		$prefix = $this->cdn_prefix . $nodename . '_';
		$vhostinfo = (array) json_decode(base64_decode($_REQUEST['info']));
		$arr = $vhostinfo;
		$arr['name'] = $prefix . $vhostinfo['name'];
		$oldvhost = daocall('vhost', 'getVhost', array($arr['name']));
		$uid = $oldvhost['uid'] ? $oldvhost['uid'] : null;
		$xmlfilename = $arr['name'] . '.xml';
		$arr['web_quota'] = 0;
		$arr['db_quota'] = 0;
		$arr['uid'] = $uid;
		$arr['cdn'] = 1;
		$arr['access'] = $xmlfilename;
		$arr['db_name'] = null;
		$arr['ftp'] = 0;
		$arr['templete'] = 'html';
		$arr['subtemplete'] = null;
		$arr['product_id'] = 0;
		$arr['doc_root'] = $this->cdn_dir;
		daocall('vhost', 'insertVhost', array($arr));
		$domain = $this->stdClassToArray(json_decode(base64_decode($_REQUEST['domain'])));
		$access = base64_decode($_REQUEST['access']);
		$fp = fopen($acess_dir . $xmlfilename, 'wt');

		if ($fp) {
			fwrite($fp, $access);
			fclose($fp);
		}

		global $default_db;
		$db = $default_db;

		if (!$db) {
			whm_return(1043);
		}

		try {
			$db->beginTransaction();
		}
		catch (PDOException $e) {
			try {
				$db = $this->getDbLink();
				$db->beginTransaction();
			}
			catch (PDOException $c) {
				$ret['msg'] = $c->getMessage();
				whm_return(500, $ret);
			}
		}

		$result = daocall('vhostinfo', 'delAllInfo', array($arr['name']));

		if (0 < count($domain)) {
			if ($result === false) {
				$db->rollBack();
				apicall('vhost', 'noticeChange', array('localhost', $arr['name']));
				return false;
			}

			foreach ($domain as $in) {
				$check_value = apicall('utils', 'is_ipv4', array($in['value']));
				$infovalue = $in['value'];

				if (!$check_value) {
					$infovalue = 'http://' . $_SERVER['REMOTE_ADDR'] . '/';
				}

				if (!daocall('vhostinfo', 'addInfo', array($prefix . $in['vhost'], $in['name'], $in['type'], $infovalue))) {
					$db->rollBack();
					break;
				}
			}
		}

		if (!$db->commit()) {
			$default_db->rollBack();
		}

		apicall('vhost', 'noticeChange', array('localhost', $arr['name']));
		whm_return(200);
	}

	private function getDbLink()
	{
		global $db_cfg;
		$dsn = $db_cfg['default']['dsn'];
		return new PDO($dsn);
	}

	public function test()
	{
		whm_return(200);
	}

	/**
	 * 将stdclass转为数组
	 * Enter description here ...
	 * @param unknown_type $arr
	 */
	public function stdClassToArray($arr)
	{
		foreach ($arr as $a) {
			$ar[] = (array) $a;
		}

		return $ar;
	}

	/**
	 * v2.0.5
	 * @deprecated
	 * 数组，需要用json传送
	 * Enter description here ...
	 */
	public function sync_cdn_domain()
	{
		$info = (array) json_decode($_REQUEST['info']);
		$prefix = trim($_REQUEST['nodename']) . '_';

		if (!$info) {
			exit("info is empty\r\n");
		}

		$vhost = trim($_REQUEST['vhost']);

		if (!$vhost) {
			exit("vhost is empty\r\n");
		}

		$mode = trim($_REQUEST['mode']);
		$check_value = apicall('utils', 'is_ipv4', array($info['value']));
		$infovalue = $info['value'];

		if (!$check_value) {
			$infovalue = 'http://' . $_SERVER['REMOTE_ADDR'] . '/';
		}

		switch ($mode) {
		case 'add':
			apicall('vhost', 'addInfo', array($prefix . $vhost, $info['name'], $info['type'], $infovalue));
			break;

		case 'del':
			apicall('vhost', 'delInfo', array($prefix . $vhost, $info['name'], $info['type'], $infovalue));
			break;

		default:
			break;
		}
	}

	/**
	 * 非数组，不用json
	 * $_REQUEST['access'] base64
	 * $_REQUEST['nodename']
	 * $_REQUEST['vhost']
	 * @deprecated
	 */
	public function sync_cdn_access()
	{
		$access = base64_decode($_REQUEST['access']);
		$prefix = trim($_REQUEST['nodename']) . '_';
		$vhost = $_REQUEST['vhost'];
		if (!$access || !$vhost || !$_REQUEST['nodename']) {
			exit("error: access or nodename or vhost is empty\r\n");
		}

		if (!daocall('vhost', 'getVhost', array($vhost))) {
			exit('vhost ' . $vhost . ' not found');
		}

		$acess_dir = $GLOBALS['safe_dir'] . 'access.d/';

		if (!file_exists($acess_dir)) {
			@mkdir($acess_dir, '0700');
		}

		$xmlfilename = $acess_dir . $prefix . $vhost . '.xml';
		$fp = fopen($xmlfilename, 'wt');

		if (!$fp) {
			exit("open xmlfile failed\r\n");
		}

		fwrite($fp, $access);
		fclose($fp);
		exit();
	}

	/**
	 * @deprecated
	 * Enter description here ...
	 * @param unknown_type $vh
	 */
	private function build_vh($vh)
	{
		$self_ip = gethostbyname($_SERVER['SERVER_NAME']);
		$domains = daocall('vhostinfo', 'getDomain', array($vh['name']));
		echo '<vh name=\'@' . $self_ip . ':' . $vh['name'] . '\' doc_root=\'www\' status=\'' . $vh['status'] . "' inherit='off'>\n";

		if (is_array($domains)) {
			foreach ($domains as $domain) {
				$value = $domain['value'];

				if (strncasecmp($value, 'http://', 7) != 0) {
					$value = 'http://' . $self_ip . '/';
				}

				echo '	<host dir=\'' . $value . '\'>' . $domain['name'] . "</host>\n";
			}
		}

		echo "</vh>\n";
	}

	/**
	 * @deprecated
	 * Enter description here ...
	 */
	public function list_vh()
	{
		$vhs = daocall('vhost', 'listVhost', array(null));
		echo "<vhs>\n";

		foreach ($vhs as $vh) {
			$this->build_vh($vh);
		}

		echo "</vhs>\n";
		echo "<!--configfileisok-->\n";
	}

	/**
	 * 主节点获取辅节点流量API,同时启动同步主节点的cdn设置
	 * 同步主节点cdn,虚拟主机设置。$_SERVER['REMOTE_ADDR']主节点IP
	 * 为防止其他意外<错误信息等>，在流量信息之前 加上status=200|分割,方便取数据时，以此分割
	 * 之后需要通知kangle 重新加载同步过来的虚拟主机
	 * @deprecated
	 */
	public function getflow()
	{
		$msg = $_REQUEST['msg'];
		$nodename = $_REQUEST['nodename'];

		if (!$nodename) {
			$nodename = $_SERVER['REMOTE_ADDR'];
		}

		$this->sync_cdn($nodename, $msg);
		$ip = gethostbyname($_SERVER['SERVER_NAME']);
		$prefix = '@' . $ip . ':';
		apicall('vhost', 'noticeChange', array('localhost', false));
		$result = apicall('cdn', 'getflow', array($prefix, 0));
		echo 'status=200|';
		return $result->get('flow', 0);
	}

	/**
	 * 接收post过来的cdn数据，并写入到文件
	 *@deprecated
	 */
	private function sync_cdn($nodename, $msg)
	{
		if (!$nodename || !$msg) {
			return true;
		}

		$filename = $nodename . '.xml';
		$file = $GLOBALS['safe_dir'] . 'vh.d/';
		@mkdir($file);
		$file .= $filename;
		$fp = fopen($file . '.tmp', 'wb');

		if ($fp === false) {
			trigger_error('cann\'t write file ' . $file . '.tmp');
			return false;
		}

		$msglen = strlen($msg);

		if ($msglen != fwrite($fp, $msg, $msglen)) {
			fclose($fp);
			unlink($file . '.tmp');
			trigger_error('may no space to write ' . $file . '.tmp');
			return false;
		}

		fclose($fp);
		@unlink($file);
		return rename($file . '.tmp', $file) == 0;
	}
}

?>