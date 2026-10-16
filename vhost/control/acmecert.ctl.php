<?php
needRole('vhost');

/**
 * 域名一键签发证书（Let's Encrypt / ACME v2 HTTP-01）
 * 入口：?c=acmecert&a=issue&domain=xxx
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
		$out = function ($code, $msg, $extra = array()) {
			echo json_encode(array_merge(array('code' => $code, 'msg' => $msg), $extra));
			exit();
		};

		$vhost = getRole('vhost');
		$user  = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user)) {
			$out(-1, '账号不存在');
		}
		if (!isset($user['port']) || strpos($user['port'], 's') === false) {
			$out(-1, '您的账号不支持设置 SSL 证书');
		}

		$domain = strtolower(trim(ep_request('domain')));
		if ($domain === '' || !checkDomain($domain) || strpos($domain, '*') !== false) {
			$out(-1, '域名不合法');
		}

		$infoArr = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if (!is_array($infoArr) || empty($infoArr[0])) {
			$out(-1, '该域名不存在');
		}
		$info = $infoArr[0];

		// CDN 回源域名无法在本机投放验证文件
		if (!empty($user['cdn']) || strncasecmp($info['value'], 'server://', 9) === 0) {
			$out(-1, '该域名为 CDN 回源域名，本机无法完成 HTTP-01 验证文件投放；请在源站签发证书后上传，或改用 DNS 验证方式');
		}

		$subdir = trim($info['value'], '/');
		if ($subdir === '') {
			$subdir = '.';
		}
		$webroot = rtrim($user['doc_root'], '/') . '/' . $subdir;
		if (!is_dir($webroot)) {
			$out(-1, '站点目录不存在: ' . $webroot);
		}
		// 站点根目录须能被 kangle 工作进程以站点用户身份读取
		if (!is_readable($webroot)) {
			$out(-1, '站点目录不可读: ' . $webroot);
		}
		$challengeDir = $webroot . '/.well-known/acme-challenge';

		load_lib('pub:acme');

		$staging = daocall('setting', 'get', array('acme_staging'));
		$email   = daocall('setting', 'get', array('acme_email'));

		$accountDir = $GLOBALS['safe_dir'] . 'acme/';
		if (!is_dir($accountDir)) {
			@mkdir($accountDir, 0700, true);
		}
		$keyFile  = $accountDir . 'account.pem';
		$metaFile = $accountDir . 'account.json';

		// 面板本身以 root 运行，验证文件直接以 root 落盘后归属站点用户，
		// 避免站点目录已被 root 建过时切到站点用户反而写不进去
		$siteUid = is_numeric($user['uid']) ? intval($user['uid']) : null;
		$siteGid = is_numeric($user['gid']) ? intval($user['gid']) : null;

		$placedToken = null;
		$placeChallenge = function ($token, $keyAuth) use ($challengeDir, $siteUid, $siteGid) {
			if (!is_dir($challengeDir) && !@mkdir($challengeDir, 0755, true)) {
				throw new Exception('无法创建验证目录: ' . $challengeDir);
			}
			$file = $challengeDir . '/' . $token;
			if (@file_put_contents($file, $keyAuth) === false) {
				throw new Exception('无法写入验证文件: ' . $file);
			}
			@chmod($file, 0644);
			if ($siteUid !== null && $siteGid !== null) {
				@chown($file, $siteUid);
				@chgrp($file, $siteGid);
			}
		};
		$cleanupChallenge = function ($token) use ($challengeDir) {
			@unlink($challengeDir . '/' . $token);
		};

		$acme = null;
		try {
			$acme = new PHPACME(!empty($staging));
			$acme->loadDirectory();

			if (file_exists($keyFile)) {
				$acme->setAccountKeyPem(file_get_contents($keyFile));
			}
			else {
				$pem = $acme->createAccountKey();
				if (file_put_contents($keyFile, $pem) === false) {
					throw new Exception('无法保存账户密钥');
				}
				@chmod($keyFile, 0600);
			}

			$kid = '';
			if (file_exists($metaFile)) {
				$meta = json_decode(file_get_contents($metaFile), true);
				if (is_array($meta) && !empty($meta['directory']) && $meta['directory'] === $acme->getDirectoryUrl() && !empty($meta['kid'])) {
					$kid = $meta['kid'];
				}
			}
			if ($kid !== '') {
				$acme->setKid($kid);
			}
			else {
				$newKid = $acme->registerAccount($email ? $email : '');
				file_put_contents($metaFile, json_encode(array('directory' => $acme->getDirectoryUrl(), 'kid' => $newKid)));
				@chmod($metaFile, 0600);
			}

			$order = $acme->newOrder($domain);
			if (empty($order['order']['authorizations'][0])) {
				throw new Exception('订单未返回授权地址');
			}
			$authzUrl = $order['order']['authorizations'][0];
			$authz    = $acme->getAuthorization($authzUrl);

			$httpChallenge = null;
			if (isset($authz['challenges']) && is_array($authz['challenges'])) {
				foreach ($authz['challenges'] as $ch) {
					if (isset($ch['type']) && $ch['type'] === 'http-01') {
						$httpChallenge = $ch;
						break;
					}
				}
			}
			if ($httpChallenge === null) {
				throw new Exception('未找到 http-01 验证方式');
			}

			$token       = $httpChallenge['token'];
			$keyAuth     = $token . '.' . $acme->getThumbprint();
			$placedToken = $token;

			$placeChallenge($token, $keyAuth);
			$acme->triggerChallenge($httpChallenge['url']);
			$acme->pollAuthorization($authzUrl);
			$cleanupChallenge($token);
			$placedToken = null;

			$ck     = PHPACME::createCertKey();
			$csrDer = PHPACME::createCsrDer($domain, $ck['key']);
			if (empty($order['order']['finalize'])) {
				throw new Exception('订单未返回 finalize 地址');
			}
			$acme->finalizeOrder($order['order']['finalize'], $csrDer);
			$finalOrder = $acme->pollOrder($order['url']);
			if (empty($finalOrder['certificate'])) {
				throw new Exception('订单未返回证书地址');
			}
			$certPem = $acme->downloadCertificate($finalOrder['certificate']);

			$crtFile = rtrim($user['doc_root'], '/') . '/' . $domain . '.crt';
			$keyOut  = rtrim($user['doc_root'], '/') . '/' . $domain . '.key';

			change_to_user($user['uid'], $user['gid']);
			$w1 = @file_put_contents($crtFile, $certPem);
			$w2 = @file_put_contents($keyOut, $ck['pem']);
			@chmod($crtFile, 0600);
			@chmod($keyOut, 0600);
			change_to_super();
			if ($w1 === false || $w2 === false) {
				throw new Exception('证书文件写入失败');
			}

			// 普通虚拟主机：kangle 以账号级证书服务 443s，生效于本次签发的域名
			$arr = array('certificate' => $domain . '.crt', 'certificate_key' => $domain . '.key');
			daocall('vhost', 'updateVhost', array($vhost, $arr));
			apicall('vhost', 'setSystemFile', array($vhost, $user['doc_root'], array($domain . '.crt', $domain . '.key')));
			apicall('vhost', 'noticeChange', array('localhost', $vhost));
			notice_cdn_changed();

			$out(0, '证书签发成功');
		}
		catch (Exception $e) {
			if ($placedToken !== null) {
				try {
					$cleanupChallenge($placedToken);
				}
				catch (Exception $ignore) {
				}
			}
			$logs = ($acme !== null) ? $acme->getLogs() : array();
			$out(-1, $e->getMessage(), array('log' => array_slice($logs, -5)));
		}
	}
}