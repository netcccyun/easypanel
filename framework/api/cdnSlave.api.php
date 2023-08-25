<?php
class CdnSlaveAPI extends API
{
	private $acess_dir;
	public function sync($nodename, $sync, $del, $url)
	{
		$this->acess_dir = $GLOBALS['safe_dir'] . '../cdn/';
		if (!is_dir($this->acess_dir)) {
			@mkdir($this->acess_dir, '0700');
		}
		$prefix = '@' . $nodename . '_';
		$names = '';
		if ($del || $sync) {
			daocall('vhost', 'begin');

			foreach ($del as $vh) {
				$vh_name = $prefix . $vh;
				$names .= $vh_name . ',';
				daocall('vhostinfo', 'delAllInfo', array($vh_name));
				daocall('vhost', 'delVhost', array($vh_name));
				@unlink($this->get_access_file($nodename, $vh));
			}

			foreach ($sync as $vh) {
				$this->sync_vhost($nodename, $vh, $names);
			}

			daocall('vhost', 'commit');
			$this->noticeKangle($names);
		}

		if (is_array($url)) {
			foreach ($url as $u) {
				$result = apicall('vhost', 'cleanCache', array($u, null, false));
			}
		}

		return true;
	}

	private function sync_vhost($nodename, $vh, &$names)
	{
		$v = $vh['v'];
		$vh_name = '@' . $nodename . '_' . $v['name'];
		$names .= $vh_name . ',';
		$infos = $vh['info'];
		$access = $vh['access'];
		$certificate = $vh['certificate'];
		$certificate_key = $vh['certificate_key'];
		$v['uid'] = 'cdn' . $nodename . '_' . $v['uid'];
		$arr = $v;
		$arr['name'] = $vh_name;
		$access_file = '';

		if ($access) {
			$access_file = $this->get_access_file($nodename, $v['name']);
		}
		if ($certificate) {
			$certificate_file = $this->get_certificate_file($nodename, $v['name']);
		}
		if ($certificate_key) {
			$certificate_key_file = $this->get_certificate_key_file($nodename, $v['name']);
		}

		$arr['log_file'] = '/nolog';
		$arr['web_quota'] = 0;
		$arr['db_quota'] = 0;
		$arr['cdn'] = 1;
		$arr['access'] = $access_file;
		$arr['db_name'] = null;
		$arr['ftp'] = 0;
		$arr['templete'] = 'html';
		$arr['subtemplete'] = null;
		$arr['product_id'] = 0;
		$arr['doc_root'] = 'cdn';
		$arr['certificate'] = $certificate_file;
		$arr['certificate_key'] = $certificate_key_file;
		$ip = $arr['ip'];

		if (!$ip) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		daocall('vhost', 'insertVhost', array($arr));
		daocall('vhostinfo', 'delAllInfo', array($vh_name));

		foreach ($infos as $info) {
			$infovalue = $info['value'];
			if ($info['type'] == 0 && strncasecmp($infovalue, 'http://', 7) != 0 && strncasecmp($infovalue, 'https://', 8) != 0 && strncasecmp($infovalue, 'server://', 9) != 0) {
				$infovalue = 'http://' . $ip . ':0/';
			}

			daocall('vhostinfo', 'addInfo', array($vh_name, $info['name'], $info['type'], $infovalue, true, $info['id']));
		}

		if ($arr['port']) {
			daocall('vhostinfo', 'addBind', array($vh_name, '', $arr['port']));
		}

		if ($access) {
			$fp = @fopen($this->acess_dir . $access_file, 'wb');

			if ($fp) {
				@fwrite($fp, base64_decode($access));
				fclose($fp);
			}
		}

		if ($certificate) {
			$fp = @fopen($this->acess_dir . $certificate_file, 'wb');

			if ($fp) {
				@fwrite($fp, base64_decode($certificate));
				fclose($fp);
			}
		}

		if ($certificate_key) {
			$fp = @fopen($this->acess_dir . $certificate_key_file, 'wb');

			if ($fp) {
				@fwrite($fp, base64_decode($certificate_key));
				fclose($fp);
			}
		}
		if ($vh['certs'] && count($vh['certs'])>0) {
			foreach($vh['certs'] as $filename=>$filedata){
				$fp = @fopen($this->acess_dir . $filename, 'wb');
				if ($fp) {
					@fwrite($fp, base64_decode($filedata));
					fclose($fp);
				}
			}
		}
	}

	private function noticeKangle($vhs)
	{
		$whm = apicall('nodes', 'makeWhm', array('localhost'));
		$whmCall = new WhmCall('core.whm', 'reload_vh');
		$whmCall->addParam('names', $vhs);

		if (!$whm->call($whmCall)) {
			return false;
		}

		return true;
	}

	private function get_access_file($nodename, $name)
	{
		return '@' . $nodename . '_' . $name . '.xml';
	}

	private function get_certificate_file($nodename, $name)
	{
		return '@' . $nodename . '_' . $name . '.crt';
	}

	private function get_certificate_key_file($nodename, $name)
	{
		return '@' . $nodename . '_' . $name . '.key';
	}
}

?>