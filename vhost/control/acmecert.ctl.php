<?php
needRole('vhost');

/**
 * 域名一键签发证书（Let's Encrypt / ACME v2 HTTP-01）
 * 入口：?c=acmecert&a=issue&domain=xxx
 * 签发/部署核心逻辑在 framework/api/acme.api.php（与 crontab 自动续期共用）
 */
class AcmecertControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function issue()
	{
		header('Content-Type: application/json; charset=utf-8');
		$vhost = getRole('vhost');
		$domain = ep_request('domain');
		$ret = apicall('acme', 'issue', array($vhost, $domain));
		if (!is_array($ret)) {
			$ret = array('code' => -1, 'msg' => '签发失败');
		}
		echo json_encode($ret);
		exit();
	}
}
