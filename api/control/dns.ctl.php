<?php
define('NOT_RELOAD', '201');
define('SUCCESS', '200');
define('RECORD_EMPTY', '404');
define('DATA_ERROR', '501');
define('RUN_ERROR', '500');
class DnsControl extends Control
{
	private $bind_dir = '/vhs/bind/';
	private $ip_file = '/vhs/bind/etc/ip.conf';

	public function sync_record()
	{
		$info = (array) json_decode(base64_decode($_REQUEST['info']));
		$domains = $info['domains'];

		if (!apicall('bind', 'writeDomainConfig', array($_SERVER['REMOTE_ADDR'], $domains))) {
			whm_return(500);
		}

		whm_return(200);
	}

	public function test_dns()
	{
		$bind_dir = apicall('bind', 'getBindDir', array());
		$t = $_REQUEST['t'];
		$lt = time();

		if (300 < abs($t - $lt)) {
			$json['error'] = '服务器时间相差超过5分钟';
			whm_return(200, $json);
		}

		$json = null;

		if (file_exists($bind_dir)) {
			if (!apicall('bind', 'checkBind', array())) {
				$json['error'] = 'named没启动';
				whm_return(200, $json);
			}

			whm_return(200);
		}

		$json['error'] = 'bind没有安装';
		whm_return(200, $json);
	}

	public function domain_update()
	{
		$json['status'] = 400;
		$name = trim($_REQUEST['name']);

		if (!$name) {
			$json['msg'] = 'params(name) empty';
			exit(json_encode($json));
		}

		unset($_REQUEST['c']);
		unset($_REQUEST['a']);
		unset($_REQUEST['r']);
		unset($_REQUEST['s']);
		unset($_REQUEST['PHPSESSID']);
		unset($_REQUEST['name']);

		if (daocall('domains', 'domainUpdate', array($name, $_REQUEST))) {
			$json['status'] = 200;
		}

		exit(json_encode($json));
	}

	/**
	 * name,server不能为空
	 * 其他可为空
	 * name ,server为空会返回错误信息，
	 * 200为成功,400为参数错误,500为程序创建错误
	 */
	public function domain_add()
	{
		$json['status'] = 400;
		$arr['name'] = trim($_REQUEST['name']);

		if (!$arr['name']) {
			$json['msg'] = 'name 域名不能为空';
			exit(json_encode($json));
		}

		$arr['passwd'] = $_REQUEST['passwd'] ? trim($_REQUEST['passwd']) : $this->get_salt(6);
		$arr['max_record'] = $_REQUEST['max_record'] ? intval($_REQUEST['max_record']) : 10;
		$arr['status'] = $_REQUEST['status'] ? intval($_REQUEST['status']) : '0';
		$arr['server'] = trim($_REQUEST['server']);

		if (!$arr['server']) {
			$json['msg'] = 'server 解析服务器不能为空';
			exit(json_encode($json));
		}

		$arr['salt'] = $_REQUEST['salt'] ? trim($_REQUEST['salt']) : $this->get_salt();
		$result = apicall('domain', 'domainAdd', array($arr));

		if ($result) {
			$json['status'] = 200;
			exit(json_encode($json));
		}

		$json['status'] = 500;
		exit(json_encode($json));
	}

	private function get_salt($leng = 8)
	{
		$str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$str_len = strlen($str);
		$salt = '';
		$i = 0;

		while ($i < $leng) {
			$round = rand(0, $str_len);
			$salt .= $str[$round];
			++$i;
		}

		return $salt;
	}

	/**
	 * 400有返回信息，500没有返回信息
	 * 200为成功
	 */
	public function domain_del()
	{
		$name = trim($_REQUEST['name']);
		$json['status'] = 400;

		if (!$name) {
			$json['msg'] = 'name 域名不能为空';
			exit(json_encode($json));
		}

		$result = apicall('domain', 'domainDel', array($name));

		if ($result) {
			$json['status'] = 200;
			exit(json_encode($json));
		}

		$json['status'] = 500;
		exit(json_encode($json));
	}

	public function view_list()
	{
		$view = daocall('views', 'viewsList', array());

		if ($view) {
			$json['status'] = 200;
			$json['view'] = $view;
			exit(json_encode($json));
		}

		$json['status'] = 500;
		exit(json_encode($json));
	}

	/**
	 * 400有返回信息，500没有返回信息
	 * 200为成功
	 */
	public function record_get()
	{
		$json['status'] = 400;

		if ($_REQUEST['id']) {
			$arr['id'] = intval($_REQUEST['id']);
		}

		if ($_REQUEST['domain']) {
			$arr['domain'] = trim($_REQUEST['domain']);
		}

		if ($_REQUEST['name']) {
			$arr['name'] = trim($_REQUEST['name']);
		}

		if (!$arr) {
			exit(json_encode($json));
		}

		$result = daocall('records', 'recordGet', array($arr));

		if (!$result) {
			$json['status'] = 500;
			exit(json_encode($json));
		}

		$json['status'] = 200;
		$json['record'] = $result;
		exit(json_encode($json));
	}

	/**
	 *
	 * Enter description here ...
	 */
	public function record_del()
	{
		$json['status'] = 400;

		if ($_REQUEST['id']) {
			$arr['id'] = intval($_REQUEST['id']);
		}

		if ($_REQUEST['domain']) {
			$arr['domain'] = trim($_REQUEST['domain']);
		}

		if ($_REQUEST['name']) {
			$arr['name'] = trim($_REQUEST['name']);
		}

		if (!$arr) {
			exit(json_encode($json));
		}

		$result = apicall('record', 'recordDel', array($arr));

		if (!$result) {
			$json['status'] = 500;
			exit(json_encode($json));
		}

		$json['status'] = 200;
		exit(json_encode($json));
	}

	/**
	 * 400为参数或者其他错误
	 * 500为执行错误
	 * 200为成功
	 * Enter description here ...
	 */
	public function record_add()
	{
		$json['status'] = 400;
		$arr['domain'] = trim($_REQUEST['domain']);

		if (!$arr['domain']) {
			$json['msg'] = 'domain 域名不能为空';
			exit(json_encode($json));
		}

		$arr['name'] = $_REQUEST['name'] ? trim($_REQUEST['name']) : '@';
		$arr['type'] = $_REQUEST['type'] ? trim($_REQUEST['type']) : 'A';
		$arr['value'] = trim($_REQUEST['value']);

		if (!$arr['value']) {
			$json['msg'] = 'value 解析值不能为空';
			exit(json_encode($json));
		}

		$arr['ttl'] = $_REQUEST['ttl'] ? intval($_REQUEST['ttl']) : 3600;
		$arr['view'] = $_REQUEST['view'] ? trim($_REQUEST['view']) : 'any';

		if ($_REQUEST['prio']) {
			$arr['prio'] = intval($_REQUEST['prio']);
		}

		$attr['name'] = $arr['domain'];
		$fields = array('max_record');
		$max = daocall('domains', 'getDomain', array($attr, $fields));

		if (intval($max['max_record']) != 0) {
			$max_re = daocall('records', 'recordGetCount', array($arr['domain']));

			if ($max['max_record'] <= $max_re['max_record']) {
				$json['msg'] = '已达到最多解析条数';
				exit(json_encode($json));
			}
		}

		$result = apicall('record', 'recordAdd', array($arr));

		if (!$result) {
			$json['status'] = 500;
			exit(json_encode($json));
		}

		$json['status'] = 200;
		exit(json_encode($json));
	}

	public function sync_init()
	{
		$info = (array) json_decode(base64_decode($_REQUEST['info']));
		$views = $info['views'];

		if (0 < count($views)) {
			foreach ($views as $view) {
				$view = (array) $view;
				daocall('views', 'viewsAdd', array($view));
			}
		}

		if ($info['ip']) {
			$ip_tmp_file = $this->ip_file . '.tmp';

			if (file_exists($this->ip_file)) {
				$fp = fopen($ip_tmp_file, 'wt');

				if (!$fp) {
					whm_return(500);
				}

				fwrite($fp, $info['ip']);
				fclose($fp);
				$check = apicall('bind', 'checkConf', array($ip_tmp_file));

				if ($check !== true) {
					whm_return(500);
				}

				@unlink($this->ip_file);

				if (!rename($ip_tmp_file, $this->ip_file)) {
					whm_return(500);
				}
			}
			else {
				$fp = fopen($this->ip_file, 'wt');

				if (!$fp) {
					whm_return(500);
				}

				fwrite($fp, $info['ip']);
				fclose($fp);
			}
		}

		if (apicall('bind', 'bindInit', array(false, false))) {
			whm_return(200);
		}

		whm_return(500);
	}
}

?>