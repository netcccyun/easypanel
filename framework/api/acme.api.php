<?php
/**
 * ACME(Let's Encrypt) 证书签发与自动续期
 * 供 vhost 控制器（一键签发）与 crontab（runDay 自动续期）共用
 */
class AcmeAPI extends API
{
	// 证书剩余有效期低于该天数时自动续期（Let's Encrypt 有效期 90 天）
	const RENEW_BEFORE_DAYS = 30;

	// 证书统一存放子目录（doc_root 相对），kangle 的 cert 解析为 doc_root + 相对路径
	const CERT_DIR = 'cert/';

	/**
	 * 证书文件相对路径（doc_root 相对），如 cert/xxx.crt
	 */
	public static function certFile($filename)
	{
		return self::CERT_DIR . basename(str_replace('\\', '/', $filename));
	}

	/**
	 * 读取证书文件内容：cert/ 子目录优先，回退旧版散放在 doc_root 根的路径
	 */
	public static function readCertFile($doc_root, $filename)
	{
		$filename = basename(str_replace('\\', '/', $filename));
		if (empty($doc_root)) {
			return false;
		}
		$pem = @file_get_contents(rtrim($doc_root, '/') . '/' . self::CERT_DIR . $filename);
		if ($pem !== false) {
			return $pem;
		}
		return @file_get_contents(rtrim($doc_root, '/') . '/' . $filename);
	}

	/**
	 * 写证书文件到 cert/ 子目录（自动建目录），返回完整物理路径；失败返回 false
	 */
	public static function writeCertFile($doc_root, $filename, $content)
	{
		$filename = basename(str_replace('\\', '/', $filename));
		$dir = rtrim($doc_root, '/') . '/' . self::CERT_DIR;
		if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
			return false;
		}
		$file = $dir . $filename;
		if (@file_put_contents($file, $content) === false) {
			return false;
		}
		@chmod($file, 0600);
		return $file;
	}

	/**
	 * 读取域名证书剩余有效天数（负数表示已过期），无证书或解析失败返回 null
	 */
	public function getCertDays($doc_root, $domain)
	{
		$domain = strtolower(trim($domain));
		if (empty($doc_root) || !preg_match('/^[a-z0-9.-]+$/', $domain)) {
			return null;
		}
		$pem = self::readCertFile($doc_root, $domain . '.crt');
		if ($pem === false) {
			return null;
		}
		$cert = @openssl_x509_parse($pem);
		if (!is_array($cert) || empty($cert['validTo_time_t'])) {
			return null;
		}
		return (int) floor(($cert['validTo_time_t'] - time()) / 86400);
	}

	/**
	 * 签发（或续期）一个域名的证书并部署为该域名的单域名证书
	 * @return array('code'=>0/-1, 'msg'=>string, 'log'=>array 失败时附最近日志)
	 */
	public function issue($vhost, $domain)
	{
		$domain = strtolower(trim($domain));
		$user = daocall('vhost', 'getVhost', array($vhost));
		if (!is_array($user)) {
			return array('code' => -1, 'msg' => '账号不存在');
		}
		if (!isset($user['port']) || strpos($user['port'], 's') === false) {
			return array('code' => -1, 'msg' => '您的账号不支持设置 SSL 证书');
		}
		if ($domain === '' || !checkDomain($domain) || strpos($domain, '*') !== false) {
			return array('code' => -1, 'msg' => '域名不合法');
		}

		$infoArr = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if (!is_array($infoArr) || empty($infoArr[0])) {
			return array('code' => -1, 'msg' => '该域名不存在');
		}
		$info = $infoArr[0];

		// CDN 回源域名无法在本机投放验证文件
		if (!empty($user['cdn']) || strncasecmp($info['value'], 'server://', 9) === 0) {
			return array('code' => -1, 'msg' => '该域名为 CDN 回源域名，本机无法完成 HTTP-01 验证文件投放；请在源站签发证书后上传，或改用 DNS 验证方式');
		}

		$subdir = trim($info['value'], '/');
		if ($subdir === '') {
			$subdir = '.';
		}
		$webroot = rtrim($user['doc_root'], '/') . '/' . $subdir;
		if (!is_dir($webroot)) {
			return array('code' => -1, 'msg' => '站点目录不存在: ' . $webroot);
		}
		// 站点根目录须能被 kangle 工作进程以站点用户身份读取
		if (!is_readable($webroot)) {
			return array('code' => -1, 'msg' => '站点目录不可读: ' . $webroot);
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

			change_to_user($user['uid'], $user['gid']);
			$crtFile = self::writeCertFile($user['doc_root'], $domain . '.crt', $certPem);
			$keyOut  = self::writeCertFile($user['doc_root'], $domain . '.key', $ck['pem']);
			change_to_super();
			if ($crtFile === false || $keyOut === false) {
				throw new Exception('证书文件写入失败');
			}

			// 部署为该域名的单域名SSL证书（svh级），与"单域名ssl证书设置"页状态一致；
			// 多个域名各自签发互不影响。旧版后缀（无 cert/ 前缀）在续期时规范化到新目录
			$p = strpos($info['value'], ';');
			$newSuffix = ';' . self::CERT_DIR . $domain . '.crt|' . self::CERT_DIR . $domain . '.key';
			if ($p === false) {
				apicall('vhost', 'updateInfo', array($vhost, $domain, array('value' => $info['value'] . $newSuffix)));
			} elseif (strpos($info['value'], self::CERT_DIR) === false) {
				apicall('vhost', 'updateInfo', array($vhost, $domain, array('value' => substr($info['value'], 0, $p) . $newSuffix)));
			}
			// 主机级默认证书不自动设置，需要默认证书请在 SSL 证书设置页显式配置
			apicall('vhost', 'setSystemFile', array($vhost, $user['doc_root'], array(self::CERT_DIR . $domain . '.crt', self::CERT_DIR . $domain . '.key')));
			apicall('vhost', 'noticeChange', array('localhost', $vhost));
			notice_cdn_changed();

			return array('code' => 0, 'msg' => '证书签发成功');
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
			return array('code' => -1, 'msg' => $e->getMessage(), 'log' => array_slice($logs, -5));
		}
	}

	/**
	 * 判断域名证书是否为 Let's Encrypt 签发（含 staging），用于避免自动续期覆盖手动上传的其他 CA 证书
	 */
	public function isLetsEncryptCert($doc_root, $domain)
	{
		$domain = strtolower(trim($domain));
		if (empty($doc_root) || !preg_match('/^[a-z0-9.-]+$/', $domain)) {
			return false;
		}
		$pem = self::readCertFile($doc_root, $domain . '.crt');
		if ($pem === false) {
			return false;
		}
		$cert = @openssl_x509_parse($pem);
		if (!is_array($cert) || empty($cert['issuer'])) {
			return false;
		}
		foreach ((array) $cert['issuer'] as $v) {
			if (is_string($v) && stripos($v, "let's encrypt") !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 自动续期：遍历所有虚拟主机的域名证书，剩余不足 RENEW_BEFORE_DAYS 天时重签
	 * 由 crontab day（php crontab.php day）调用
	 */
	public function autoRenew()
	{
		$vhs = daocall('vhost', 'listVhost', array(null, 'rows'));
		if (!is_array($vhs)) {
			echo "acme_renew: no vhost\n";
			return false;
		}

		foreach (ep_iter($vhs) as $user) {
			if (!is_array($user) || empty($user['doc_root']) || empty($user['name'])) {
				continue;
			}
			// status=1 为已暂停
			if (isset($user['status']) && intval($user['status']) === 1) {
				continue;
			}
			if (!isset($user['port']) || strpos($user['port'], 's') === false) {
				continue;
			}

			$infos = daocall('vhostinfo', 'getDomain', array($user['name']));
			foreach (ep_iter($infos) as $info) {
				$domain = strtolower($info['name']);
				// 仅续期已启用单域名证书的域名（关闭证书即不续）
				$p = strpos($info['value'], ';');
				if ($p === false || strpos($info['value'], '.crt') === false || strpos($info['value'], '.key') === false) {
					continue;
				}
				// CDN 回源域名本机无法完成 HTTP-01 验证，跳过
				if (!empty($user['cdn']) || strncasecmp($info['value'], 'server://', 9) === 0) {
					continue;
				}
				$days = $this->getCertDays($user['doc_root'], $domain);
				if ($days === null || $days >= self::RENEW_BEFORE_DAYS) {
					continue;
				}
				// 仅续 Let's Encrypt 签发的证书，避免覆盖手动上传的其他 CA 证书
				if (!$this->isLetsEncryptCert($user['doc_root'], $domain)) {
					continue;
				}
				echo "acme_renew: {$user['name']}/{$domain} 剩余 {$days} 天，开始续期\n";
				$ret = $this->issue($user['name'], $domain);
				if (is_array($ret) && $ret['code'] == 0) {
					echo "acme_renew: 续期成功\n";
				}
				else {
					$msg = is_array($ret) ? $ret['msg'] : '未知错误';
					echo "acme_renew: 续期失败 {$msg}\n";
				}
			}
		}
		return true;
	}
}
