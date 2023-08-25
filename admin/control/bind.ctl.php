<?php
needRole('admin');
class BindControl extends Control
{
	private $bind_dir;

	public function __construct()
	{
		parent::__construct();
		$this->bind_dir = apicall('bind', 'getBindDir', array());
	}

	public function bindInit()
	{
		$bind_dir = $this->bind_dir;

		if (!file_exists($bind_dir . 'sbin/named')) {
			exit('bind未安装');
		}

		$views = daocall('views', 'viewsList', array());

		if (count($views) <= 0) {
			exit('请先同步线路');
		}

		if (apicall('bind', 'bindInit', array())) {
			exit('初始化成功');
			return NULL;
		}

		exit('初始化失败');
	}

	public function bindInstall()
	{
		exec('/vhs/kangle/bind_install.sh');
	}

	public function bindInstallSelect()
	{
		$bind_dir = $this->bind_dir;
		$json['code'] = 400;

		if (file_exists($bind_dir . 'sbin/named')) {
			$json['code'] = 200;
		}

		exit(json_encode($json));
	}

	public function getInit()
	{
		$json['init'] = daocall('setting', 'get', array('dns_init'));
		echo json_encode($json);
	}

	public function init()
	{
		$bind_dir = $this->bind_dir;

		if (!file_exists($bind_dir . 'sbin/named')) {
			exit('bind未安装');
		}

		$server = daocall('servers', 'serverGet', array());

		if (count($server) <= 0) {
			exit('未添加DNS服务器');
		}

		$step = intval($_REQUEST['step']);

		switch ($step) {
		case '1':
			if (!apicall('bind', 'rundViewKey', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		case '2':
			if (!apicall('bind', 'mk_dir', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		case '3':
			if (!apicall('bind', 'generateConf', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		case '4':
			if (!apicall('bind', 'writeAllDomainConf', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		case '5':
			if (!apicall('bind', 'namedRestart', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		case '6':
			if (!apicall('dnssync', 'syncAllInit', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		case '7':
			if (!apicall('bind', 'writeDomainConfig', array())) {
				exit($GLOBALS['bind_error_msg'] ? $GLOBALS['bind_error_msg'] : '失败');
			}

			break;

		default:
			break;
		}

		exit('成功');
	}
}

?>