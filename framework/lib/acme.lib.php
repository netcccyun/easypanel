<?php
/**
 * 纯 PHP 实现的 ACME v2 (RFC 8555) 客户端
 * 仅依赖 openssl + curl，无外部命令行工具、无计划任务
 * 用途：为单个域名申请 Let's Encrypt 证书（HTTP-01 验证）
 */
class PHPACME
{
	const DIRECTORY_PROD    = 'https://acme-v02.api.letsencrypt.org/directory';
	const DIRECTORY_STAGING = 'https://acme-staging-v02.api.letsencrypt.org/directory';

	private $directoryUrl;
	private $directory = array();
	private $accountKey;              // OpenSSLAsymmetricKey
	private $jwkCache = null;
	private $thumbprint = '';
	private $kid = '';
	private $nonce = '';
	private $logs = array();

	public function __construct($staging = false, $directoryUrl = null)
	{
		if ($directoryUrl) {
			$this->directoryUrl = $directoryUrl;
		}
		else {
			$this->directoryUrl = $staging ? self::DIRECTORY_STAGING : self::DIRECTORY_PROD;
		}
	}

	public function getDirectoryUrl()
	{
		return $this->directoryUrl;
	}

	public function getLogs()
	{
		return $this->logs;
	}

	private function log($msg)
	{
		$this->logs[] = $msg;
	}

	/* ---------------- 编码工具 ---------------- */

	public static function b64url($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private static function pemToDer($pem)
	{
		$pem = preg_replace('/-----BEGIN [^-]+-----/', '', $pem);
		$pem = preg_replace('/-----END [^-]+-----/', '', $pem);
		$pem = preg_replace('/\s+/', '', $pem);
		return base64_decode($pem);
	}

	/**
	 * DER 编码的 ECDSA 签名 -> JOSE 需要的 R||S 定长拼接
	 */
	private static function derToJose($der, $partLen)
	{
		$pos = 0;
		if (strlen($der) < 2 || ord($der[$pos++]) !== 0x30) {
			return false;
		}
		$len = ord($der[$pos++]);
		if ($len & 0x80) {
			$pos += ($len & 0x7f);
		}
		if (ord($der[$pos++]) !== 0x02) {
			return false;
		}
		$rLen = ord($der[$pos++]);
		$r = substr($der, $pos, $rLen);
		$pos += $rLen;
		if (ord($der[$pos++]) !== 0x02) {
			return false;
		}
		$sLen = ord($der[$pos++]);
		$s = substr($der, $pos, $sLen);

		$r = ltrim($r, "\x00");
		$s = ltrim($s, "\x00");
		$r = str_pad($r, $partLen, "\x00", STR_PAD_LEFT);
		$s = str_pad($s, $partLen, "\x00", STR_PAD_LEFT);
		return $r . $s;
	}

	private function jwk()
	{
		if ($this->jwkCache !== null) {
			return $this->jwkCache;
		}
		if (!$this->accountKey) {
			throw new Exception('账户密钥未设置');
		}
		$details = openssl_pkey_get_details($this->accountKey);
		if (!isset($details['ec'])) {
			throw new Exception('账户密钥不是 EC 密钥');
		}
		$map = array('prime256v1' => 'P-256', 'secp384r1' => 'P-384', 'secp521r1' => 'P-521');
		$curve = isset($details['ec']['curve_name']) ? $details['ec']['curve_name'] : 'prime256v1';
		$crv = isset($map[$curve]) ? $map[$curve] : 'P-256';
		$this->jwkCache = array(
			'crv' => $crv,
			'kty' => 'EC',
			'x'   => self::b64url($details['ec']['x']),
			'y'   => self::b64url($details['ec']['y']),
		);
		return $this->jwkCache;
	}

	private function computeThumbprint()
	{
		$jwk = $this->jwk();
		// RFC 7638：键名按字典序排列
		$json = json_encode(array(
			'crv' => $jwk['crv'],
			'kty' => $jwk['kty'],
			'x'   => $jwk['x'],
			'y'   => $jwk['y'],
		), JSON_UNESCAPED_SLASHES);
		$this->thumbprint = self::b64url(hash('sha256', $json, true));
		return $this->thumbprint;
	}

	public function getThumbprint()
	{
		if ($this->thumbprint === '') {
			$this->computeThumbprint();
		}
		return $this->thumbprint;
	}

	/* ---------------- HTTP ---------------- */

	private function http($method, $url, $body = null)
	{
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT        => 90,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_USERAGENT      => 'Easypanel-PHPACME/1.0',
		));
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/jose+json'));
		}
		$resp = curl_exec($ch);
		if ($resp === false) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new Exception('网络请求失败: ' . $err);
		}
		$hSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$rawHeader = substr($resp, 0, $hSize);
		$rawBody   = substr($resp, $hSize);
		$headers   = array();
		foreach (explode("\n", $rawHeader) as $line) {
			$line = trim($line);
			if ($line === '' || strpos($line, ':') === false) {
				continue;
			}
			$parts = explode(':', $line, 2);
			$headers[strtolower(trim($parts[0]))] = trim($parts[1]);
		}
		if (isset($headers['replay-nonce'])) {
			$this->nonce = $headers['replay-nonce'];
		}
		return array('status' => $status, 'headers' => $headers, 'body' => $rawBody);
	}

	private function getNonce()
	{
		if ($this->nonce !== '') {
			return $this->nonce;
		}
		$this->http('HEAD', $this->directory['newNonce']);
		if ($this->nonce === '') {
			throw new Exception('无法获取 ACME nonce');
		}
		return $this->nonce;
	}

	private function signedRequest($url, $payload, $useJwk = false, $retryNonce = true)
	{
		$this->getNonce();
		$protected = array(
			'alg'   => 'ES256',
			'nonce' => $this->nonce,
			'url'   => $url,
		);
		if ($useJwk) {
			$protected['jwk'] = $this->jwk();
		}
		else {
			$protected['kid'] = $this->kid;
		}

		$protected64 = self::b64url(json_encode($protected, JSON_UNESCAPED_SLASHES));
		if ($payload === null) {
			$payload64 = '';
		}
		else {
			$payload64 = self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
		}
		$signingInput = $protected64 . '.' . $payload64;

		$der = '';
		if (!openssl_sign($signingInput, $der, $this->accountKey, OPENSSL_ALGO_SHA256)) {
			throw new Exception('JWS 签名失败');
		}
		$sig = self::derToJose($der, 32);
		if ($sig === false) {
			throw new Exception('签名格式转换失败');
		}

		$jws = array(
			'protected' => $protected64,
			'payload'   => $payload64,
			'signature' => self::b64url($sig),
		);
		$this->nonce = '';

		$resp = $this->http('POST', $url, json_encode($jws, JSON_UNESCAPED_SLASHES));

		if ($resp['status'] >= 400) {
			$errBody = json_decode($resp['body'], true);
			$type    = (is_array($errBody) && isset($errBody['type'])) ? $errBody['type'] : '';
			if ($retryNonce && strpos($type, 'badNonce') !== false) {
				$this->log('nonce 过期，重试请求');
				return $this->signedRequest($url, $payload, $useJwk, false);
			}
			$detail = (is_array($errBody) && isset($errBody['detail'])) ? $errBody['detail'] : substr($resp['body'], 0, 300);
			throw new Exception('ACME 请求被拒绝 [' . $resp['status'] . '] ' . $detail);
		}
		return $resp;
	}

	/* ---------------- 目录 / 账户 ---------------- */

	public function loadDirectory()
	{
		$resp = $this->http('GET', $this->directoryUrl);
		if ($resp['status'] !== 200) {
			throw new Exception('无法加载 ACME 目录: ' . $this->directoryUrl);
		}
		$this->directory = json_decode($resp['body'], true);
		if (!isset($this->directory['newOrder'])) {
			throw new Exception('ACME 目录格式不正确');
		}
		$this->log('已加载 ACME 目录');
		return $this->directory;
	}

	public function setAccountKeyPem($pem)
	{
		$key = openssl_pkey_get_private($pem);
		if ($key === false) {
			throw new Exception('账户私钥不可用');
		}
		$this->accountKey   = $key;
		$this->jwkCache     = null;
		$this->thumbprint   = '';
		$this->computeThumbprint();
	}

	public function createAccountKey()
	{
		$key = openssl_pkey_new(array(
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name'       => 'prime256v1',
		));
		if ($key === false) {
			throw new Exception('无法生成账户密钥');
		}
		$pem = '';
		openssl_pkey_export($key, $pem);
		$this->setAccountKeyPem($pem);
		return $pem;
	}

	public function setKid($kid)
	{
		$this->kid = $kid;
	}

	public function registerAccount($email = '')
	{
		$payload = array('termsOfServiceAgreed' => true);
		if ($email !== '' && $email !== null) {
			$payload['contact'] = array('mailto:' . $email);
		}
		$resp = $this->signedRequest($this->directory['newAccount'], $payload, true);
		if (!isset($resp['headers']['location'])) {
			throw new Exception('账户注册未返回 Location');
		}
		$this->kid = $resp['headers']['location'];
		$this->log('账户注册成功');
		return $this->kid;
	}

	/* ---------------- 订单流程 ---------------- */

	public function newOrder($domain)
	{
		$payload = array(
			'identifiers' => array(
				array('type' => 'dns', 'value' => $domain),
			),
		);
		$resp = $this->signedRequest($this->directory['newOrder'], $payload);
		if (!isset($resp['headers']['location'])) {
			throw new Exception('创建订单未返回 Location');
		}
		return array('url' => $resp['headers']['location'], 'order' => json_decode($resp['body'], true));
	}

	public function getAuthorization($url)
	{
		$resp = $this->signedRequest($url, null);
		return json_decode($resp['body'], true);
	}

	public function triggerChallenge($url)
	{
		$empty = new stdClass();
		$resp  = $this->signedRequest($url, $empty);
		return json_decode($resp['body'], true);
	}

	public function pollAuthorization($url, $timeout = 120)
	{
		$deadline = time() + $timeout;
		while (time() < $deadline) {
			$authz  = $this->getAuthorization($url);
			$status = isset($authz['status']) ? $authz['status'] : '';
			if ($status === 'valid') {
				$this->log('域名验证通过');
				return true;
			}
			if ($status === 'invalid') {
				$reason = '';
				if (isset($authz['challenges']) && is_array($authz['challenges'])) {
					foreach ($authz['challenges'] as $ch) {
						if (isset($ch['status']) && $ch['status'] === 'invalid' && isset($ch['error']['detail'])) {
							$reason = $ch['error']['detail'];
						}
					}
				}
				throw new Exception('域名验证失败: ' . ($reason !== '' ? $reason : '未知原因'));
			}
			sleep(2);
		}
		throw new Exception('域名验证超时');
	}

	public function finalizeOrder($finalizeUrl, $csrDer)
	{
		$resp = $this->signedRequest($finalizeUrl, array('csr' => self::b64url($csrDer)));
		return json_decode($resp['body'], true);
	}

	public function pollOrder($orderUrl, $timeout = 120)
	{
		$deadline = time() + $timeout;
		while (time() < $deadline) {
			$resp  = $this->signedRequest($orderUrl, null);
			$order = json_decode($resp['body'], true);
			$status = isset($order['status']) ? $order['status'] : '';
			if ($status === 'valid') {
				return $order;
			}
			if ($status === 'invalid') {
				$detail = isset($order['error']['detail']) ? $order['error']['detail'] : '未知原因';
				throw new Exception('订单处理失败: ' . $detail);
			}
			sleep(2);
		}
		throw new Exception('订单处理超时');
	}

	public function downloadCertificate($url)
	{
		$resp = $this->signedRequest($url, null);
		$pem  = $resp['body'];
		if (strpos($pem, '-----BEGIN CERTIFICATE-----') === false) {
			throw new Exception('证书下载失败');
		}
		$this->log('证书下载完成');
		return $pem;
	}

	/* ---------------- 证书私钥 / CSR ---------------- */

	public static function createCertKey()
	{
		$key = openssl_pkey_new(array(
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name'       => 'prime256v1',
		));
		if ($key === false) {
			throw new Exception('无法生成证书私钥');
		}
		$pem = '';
		openssl_pkey_export($key, $pem);
		return array('key' => $key, 'pem' => $pem);
	}

	public static function createCsrDer($domain, $privateKey)
	{
		$cnfFile = tempnam(sys_get_temp_dir(), 'acmecnf');
		if ($cnfFile === false) {
			throw new Exception('无法创建临时配置文件');
		}
		$cnf = "[req]\n"
			. "distinguished_name = dn\n"
			. "req_extensions = v3_req\n"
			. "prompt = no\n"
			. "[dn]\n"
			. "CN = " . $domain . "\n"
			. "[v3_req]\n"
			. "basicConstraints = CA:FALSE\n"
			. "keyUsage = digitalSignature, keyEncipherment\n"
			. "extendedKeyUsage = serverAuth\n"
			. "subjectAltName = DNS:" . $domain . "\n";
		if (file_put_contents($cnfFile, $cnf) === false) {
			@unlink($cnfFile);
			throw new Exception('无法写入临时配置文件');
		}

		$csr = openssl_csr_new(
			array('commonName' => $domain),
			$privateKey,
			array(
				'config'         => $cnfFile,
				'digest_alg'     => 'sha256',
				'req_extensions' => 'v3_req',
			)
		);
		@unlink($cnfFile);
		if ($csr === false) {
			throw new Exception('生成 CSR 失败');
		}
		$pem = '';
		openssl_csr_export($csr, $pem);
		return self::pemToDer($pem);
	}
}