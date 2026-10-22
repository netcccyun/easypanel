<?php
/**
 * 备份远程存储适配器：FTP / SFTP / WebDAV / S3(兼容 R2、MinIO)
 *
 * 所有方法只接收“相对根目录”的路径（如 20261023f、20261023f/site.web.7z.001），
 * 根目录由各类型配置决定，在 fullPath() 内拼接。
 */
abstract class RemoteStorage
{
	protected $setting = array();
	protected $root = '';
	protected $error = '';
	protected $timeout = 3600;
	private static $create_error = '';

	public function __construct($setting)
	{
		$this->setting = is_array($setting) ? $setting : array();
	}

	/**
	 * 读取指定键的传输超时（秒），非法值回退 3600
	 */
	protected function resolveTimeout($key)
	{
		$timeout = isset($this->setting[$key]) ? intval($this->setting[$key]) : 0;
		return ($timeout >= 60 && $timeout <= 86400) ? $timeout : 3600;
	}

	/**
	 * 按 backup_remote_type 创建适配器，配置不完整时返回 false
	 * @param array $setting
	 * @return RemoteStorage|false
	 */
	public static function create($setting)
	{
		self::$create_error = '';
		$type = isset($setting['backup_remote_type']) ? strtolower(trim($setting['backup_remote_type'])) : 'ftp';

		if (!in_array($type, array('ftp', 'sftp', 'webdav', 's3'))) {
			$type = 'ftp';
		}

		if ($type == 'sftp') {
			$obj = new SftpStorage($setting);
		}
		else if ($type == 'webdav') {
			$obj = new WebdavStorage($setting);
		}
		else if ($type == 's3') {
			$obj = new S3Storage($setting);
		}
		else {
			$obj = new FtpStorage($setting);
		}

		$err = $obj->validate();

		if ($err !== '') {
			self::$create_error = $err;
			return false;
		}

		return $obj;
	}

	/**
	 * create() 失败时的中文原因
	 * @return string
	 */
	public static function getLastError()
	{
		return self::$create_error;
	}

	public function getError()
	{
		return $this->error;
	}

	public function test()
	{
		return $this->connect();
	}

	abstract public function mkDir($path);
	abstract public function putFile($local, $remote);
	abstract public function getFile($remote, $local);
	abstract public function listDirs($path);
	abstract public function deleteDir($path);

	/**
	 * 配置校验，返回空串表示通过
	 * @return string
	 */
	public function validate()
	{
		return '';
	}

	protected function connect()
	{
		return true;
	}

	protected function fullPath($path)
	{
		$root = trim(str_replace('\\', '/', $this->root), '/');
		$path = trim(str_replace('\\', '/', $path), '/');

		if ($root === '') {
			return $path;
		}

		if ($path === '') {
			return $root;
		}

		return $root . '/' . $path;
	}

	protected function seg($value)
	{
		return trim($value, '/');
	}
}

/**
 * FTP
 */
class FtpStorage extends RemoteStorage
{
	private $host;
	private $port;
	private $user;
	private $pass;
	private $conn = null;

	public function __construct($setting)
	{
		parent::__construct($setting);
		$this->root = isset($setting['ftp_dir']) ? $setting['ftp_dir'] : '';
		$this->host = isset($setting['ftp_host']) ? trim($setting['ftp_host']) : '';
		$this->port = isset($setting['ftp_port']) ? intval($setting['ftp_port']) : 21;
		$this->user = isset($setting['ftp_user']) ? trim($setting['ftp_user']) : '';
		$this->pass = isset($setting['ftp_passwd']) ? $setting['ftp_passwd'] : '';

		if ($this->port <= 0 || $this->port > 65535) {
			$this->port = 21;
		}
	}

	public function validate()
	{
		if ($this->host === '' || $this->user === '') {
			return 'FTP 主机地址或账号未填写';
		}

		return '';
	}

	protected function connect()
	{
		if ($this->conn !== null) {
			return $this->conn !== false;
		}

		$con = @ftp_connect($this->host, $this->port, 15);

		if (!$con) {
			$this->error = 'FTP 连接失败，请检查主机地址和端口';
			$this->conn = false;
			return false;
		}

		if (!@ftp_login($con, $this->user, $this->pass)) {
			$this->error = 'FTP 登录失败，请检查账号和密码';
			$this->conn = false;
			return false;
		}

		@ftp_pasv($con, true);
		$this->conn = $con;
		return true;
	}

	private function absPath($path)
	{
		$dir = $this->fullPath($path);
		return $dir === '' ? '/' : '/' . $dir;
	}

	public function mkDir($path)
	{
		$dir = $this->absPath($path);

		if (!$this->connect()) {
			return false;
		}

		$cur = '';

		foreach (explode('/', $dir) as $name) {
			if ($name === '') {
				continue;
			}

			$cur .= '/' . $name;
			@ftp_mkdir($this->conn, $cur);
		}

		return true;
	}

	public function putFile($local, $remote)
	{
		if (!$this->connect()) {
			return false;
		}

		$remote = $this->absPath($remote);
		$dir = dirname($remote);
		$name = basename($remote);

		if (!@ftp_chdir($this->conn, $dir)) {
			$this->error = 'FTP 无法进入目录：' . $dir;
			return false;
		}

		if (!@ftp_put($this->conn, $name, $local, FTP_BINARY)) {
			$this->error = 'FTP 上传失败：' . basename($local);
			return false;
		}

		return true;
	}

	public function getFile($remote, $local)
	{
		if (!$this->connect()) {
			return false;
		}

		$remote = $this->absPath($remote);
		$dir = dirname($remote);
		$name = basename($remote);

		if (!@ftp_chdir($this->conn, $dir)) {
			$this->error = 'FTP 无法进入目录：' . $dir;
			return false;
		}

		if (!@ftp_get($this->conn, $local, $name, FTP_BINARY)) {
			$this->error = 'FTP 下载失败：' . $remote;
			return false;
		}

		return true;
	}

	public function listDirs($path)
	{
		if (!$this->connect()) {
			return false;
		}

		$dir = $this->absPath($path);

		if (!@ftp_chdir($this->conn, $dir)) {
			$this->error = 'FTP 无法进入目录：' . $dir;
			return false;
		}

		$list = @ftp_nlist($this->conn, '.');

		if ($list === false) {
			$this->error = 'FTP 列出目录失败：' . $dir;
			return false;
		}

		$result = array();

		foreach ($list as $file) {
			$name = basename(str_replace('\\', '/', $file));

			if ($name === '' || $name === '.' || $name === '..') {
				continue;
			}

			$result[] = $name;
		}

		return $result;
	}

	public function deleteDir($path)
	{
		if (!$this->connect()) {
			return false;
		}

		$dir = $this->absPath($path);

		if ($dir === '/' || !@ftp_chdir($this->conn, $dir)) {
			return false;
		}

		$list = @ftp_nlist($this->conn, '.');

		if (is_array($list)) {
			foreach ($list as $file) {
				$name = basename(str_replace('\\', '/', $file));

				if ($name === '' || $name === '.' || $name === '..') {
					continue;
				}

				if (!@ftp_delete($this->conn, $name)) {
					@ftp_rmdir($this->conn, $name);
				}
			}
		}

		@ftp_chdir($this->conn, dirname($dir));
		return @ftp_rmdir($this->conn, basename($dir));
	}
}

/**
 * SFTP（依赖 ssh2 扩展）
 */
class SftpStorage extends RemoteStorage
{
	private $host;
	private $port;
	private $user;
	private $pass;
	private $key;
	private $key_pub;
	private $key_pass;
	private $conn = null;
	private $sftp = null;
	private $failed = false;

	public function __construct($setting)
	{
		parent::__construct($setting);
		$this->root = isset($setting['sftp_dir']) ? $setting['sftp_dir'] : '';
		$this->host = isset($setting['sftp_host']) ? trim($setting['sftp_host']) : '';
		$this->port = isset($setting['sftp_port']) ? intval($setting['sftp_port']) : 22;
		$this->user = isset($setting['sftp_user']) ? trim($setting['sftp_user']) : '';
		$this->pass = isset($setting['sftp_passwd']) ? $setting['sftp_passwd'] : '';
		$this->key = isset($setting['sftp_key']) ? trim($setting['sftp_key']) : '';
		$this->key_pub = isset($setting['sftp_key_pub']) ? trim($setting['sftp_key_pub']) : '';
		$this->key_pass = isset($setting['sftp_key_pass']) ? $setting['sftp_key_pass'] : '';

		if ($this->port <= 0 || $this->port > 65535) {
			$this->port = 22;
		}
	}

	/**
	 * 自动获取当前 PHP 实际加载的配置文件，便于用户定位要改哪个 ini
	 */
	private function ssh2IniHint()
	{
		$ini = php_ini_loaded_file();

		if (!$ini || $ini === '') {
			$ini = 'php.ini';
		}

		$ext_file = strncasecmp(PHP_OS, 'WIN', 3) == 0 ? 'php_ssh2.dll' : 'ssh2.so';
		return '未启用 ssh2 扩展：请先安装 ssh2；若已安装，请在 ' . $ini . ' 中添加 extension=' . $ext_file . ' 后重启 kangle';
	}

	public function validate()
	{
		if (!function_exists('ssh2_connect')) {
			return $this->ssh2IniHint();
		}

		if ($this->host === '' || $this->user === '') {
			return 'SFTP 主机地址或账号未填写';
		}

		if ($this->key === '' && $this->pass === '') {
			return 'SFTP 需要填写密码或私钥路径';
		}

		return '';
	}

	protected function connect()
	{
		if ($this->conn !== null) {
			return $this->conn !== false;
		}

		if ($this->failed) {
			return false;
		}

		if (!function_exists('ssh2_connect')) {
			$this->error = $this->ssh2IniHint();
			$this->failed = true;
			return false;
		}

		$conn = @ssh2_connect($this->host, $this->port);

		if (!$conn) {
			$this->error = 'SFTP 连接失败，请检查主机地址、端口和网络';
			$this->failed = true;
			return false;
		}

		if (!$this->auth($conn)) {
			$this->failed = true;
			return false;
		}

		$sftp = @ssh2_sftp($conn);

		if (!$sftp) {
			$this->error = 'SFTP 初始化失败，服务器可能未开启 SFTP 子系统';
			$this->failed = true;
			return false;
		}

		$this->conn = $conn;
		$this->sftp = $sftp;
		return true;
	}

	/**
	 * ponytail: 仅支持密码与 PEM/OpenSSH 私钥，未实现 ssh-agent 与 KeyboardInteractive 认证；
	 * 若目标服务器要求交互式认证，可在本方法内追加 ssh2_auth_agent() 分支。
	 */
	private function auth($conn)
	{
		if ($this->key === '') {
			if (@ssh2_auth_password($conn, $this->user, $this->pass)) {
				return true;
			}

			$this->error = 'SFTP 认证失败，请检查账号和密码';
			return false;
		}

		if (!is_file($this->key)) {
			$this->error = 'SFTP 私钥文件不存在：' . $this->key;
			return false;
		}

		$pub = $this->key_pub;
		$tmp = null;

		if ($pub === '') {
			$tmp = $this->makePublicKey($this->key, $this->key_pass);

			if ($tmp === false) {
				return false;
			}

			$pub = $tmp;
		}

		$ok = @ssh2_auth_pubkey_file($conn, $this->user, $pub, $this->key, $this->key_pass);

		if ($tmp !== null) {
			@unlink($tmp);
		}

		if (!$ok) {
			$this->error = 'SFTP 密钥认证失败，请检查私钥、公钥文件及口令';
			return false;
		}

		return true;
	}

	/**
	 * 由私钥推导 PEM 公钥临时文件
	 * @return string|false
	 */
	private function makePublicKey($private_file, $passphrase)
	{
		$content = @file_get_contents($private_file);

		if ($content === false) {
			$this->error = 'SFTP 私钥文件无法读取：' . $private_file;
			return false;
		}

		$key = @openssl_pkey_get_private($content, $passphrase);

		if (!$key) {
			$key = @openssl_pkey_get_private('file://' . $private_file, $passphrase);
		}

		if (!$key) {
			$this->error = 'SFTP 私钥无法解析，请同时填写公钥文件路径';
			return false;
		}

		$detail = @openssl_pkey_get_details($key);

		if (!$detail || !isset($detail['key'])) {
			$this->error = 'SFTP 私钥无法解析，请同时填写公钥文件路径';
			return false;
		}

		$tmp = @tempnam(sys_get_temp_dir(), 'epspub');

		if ($tmp === false || @file_put_contents($tmp, $detail['key']) === false) {
			$this->error = 'SFTP 临时公钥文件写入失败';
			return false;
		}

		return $tmp;
	}

	public function mkDir($path)
	{
		if (!$this->connect()) {
			return false;
		}

		$dir = $this->fullPath($path);

		if ($dir === '') {
			return true;
		}

		if (@ssh2_sftp_stat($this->sftp, $dir)) {
			return true;
		}

		if (!@ssh2_sftp_mkdir($this->sftp, $dir, 0777, true)) {
			$this->error = 'SFTP 创建目录失败：' . $dir;
			return false;
		}

		return true;
	}

	public function putFile($local, $remote)
	{
		if (!$this->connect()) {
			return false;
		}

		$remote = $this->fullPath($remote);

		if (!@ssh2_scp_send($this->conn, $local, $remote, 0644)) {
			$this->error = 'SFTP 上传失败：' . basename($local);
			return false;
		}

		return true;
	}

	public function getFile($remote, $local)
	{
		if (!$this->connect()) {
			return false;
		}

		$remote = $this->fullPath($remote);

		if (!@ssh2_scp_recv($this->conn, $remote, $local)) {
			$this->error = 'SFTP 下载失败：' . $remote;
			return false;
		}

		return true;
	}

	public function listDirs($path)
	{
		if (!$this->connect()) {
			return false;
		}

		$dir = $this->fullPath($path);
		// ponytail: 部分 ssh2 版本下 ssh2_sftp_opendir()+readdir() 读不到 EOF 会一直阻塞，
		// 改用官方推荐的 ssh2.sftp:// 流包装器 + opendir()，由标准目录流保证正常结束。
		// 流包装器要求绝对路径，先 realpath 把相对目录（如 cs）解析到家目录下。
		$real = @ssh2_sftp_realpath($this->sftp, $dir === '' ? '.' : $dir);

		if ($real === false || $real === '') {
			$this->error = 'SFTP 无法解析目录：' . ($dir === '' ? '.' : $dir);
			return false;
		}

		$url = 'ssh2.sftp://' . intval($this->sftp) . '/' . ltrim($real, '/');
		$handle = @opendir($url);

		if (!$handle) {
			$this->error = 'SFTP 无法打开目录：' . ($dir === '' ? '.' : $dir);
			return false;
		}

		$result = array();

		while (true) {
			$name = @readdir($handle);

			if ($name === false || $name === null || $name === '') {
				break;
			}

			if ($name === '.' || $name === '..') {
				continue;
			}

			// ponytail: 目录可能很大，逐条 ssh2_sftp_stat 会产生大量远程请求导致长时间无响应；
			// 备份目录名固定为 Ymd[f][HH]，直接按名字匹配，不再逐条 stat。
			// 若将来需要列出非备份目录，应另加专用方法。
			if (preg_match('/^\d{8}f?\d{0,2}$/', $name)) {
				$result[] = $name;
			}
		}

		@closedir($handle);
		return $result;
	}

	public function deleteDir($path)
	{
		if (!$this->connect()) {
			return false;
		}

		$dir = $this->fullPath($path);

		if ($dir === '') {
			return false;
		}

		$this->removeDir($dir);
		return true;
	}

	private function removeDir($dir)
	{
		$real = @ssh2_sftp_realpath($this->sftp, $dir);

		if ($real !== false && $real !== '') {
			$dir = $real;
		}

		$url = 'ssh2.sftp://' . intval($this->sftp) . '/' . ltrim($dir, '/');
		$handle = @opendir($url);

		if ($handle) {
			while (true) {
				$name = @readdir($handle);

				if ($name === false || $name === null || $name === '') {
					break;
				}

				if ($name === '.' || $name === '..') {
					continue;
				}

				$item = $dir . '/' . $name;
				$stat = @ssh2_sftp_stat($this->sftp, $item);

				if ($stat && ($stat['mode'] & 0040000)) {
					$this->removeDir($item);
				}
				else {
					@ssh2_sftp_unlink($this->sftp, $item);
				}
			}

			@closedir($handle);
		}

		@ssh2_sftp_rmdir($this->sftp, $dir);
	}
}

/**
 * WebDAV（curl）
 */
class WebdavStorage extends RemoteStorage
{
	private $url;
	private $user;
	private $pass;
	private $base;

	public function __construct($setting)
	{
		parent::__construct($setting);
		$this->timeout = $this->resolveTimeout('webdav_timeout');
		$this->root = isset($setting['webdav_dir']) ? $setting['webdav_dir'] : '';
		$this->url = isset($setting['webdav_url']) ? trim($setting['webdav_url']) : '';
		$this->user = isset($setting['webdav_user']) ? trim($setting['webdav_user']) : '';
		$this->pass = isset($setting['webdav_passwd']) ? $setting['webdav_passwd'] : '';
		$this->base = rtrim($this->url, '/');
	}

	public function validate()
	{
		if (!function_exists('curl_init')) {
			return '未启用 curl 扩展，无法使用 WebDAV';
		}

		if ($this->base === '') {
			return 'WebDAV 地址未填写';
		}

		if (!preg_match('!^https?://!i', $this->base) || !parse_url($this->base, PHP_URL_HOST)) {
			return 'WebDAV 地址格式不正确，需以 http:// 或 https:// 开头';
		}

		return '';
	}

	protected function connect()
	{
		return true;
	}

	private function urlFor($path)
	{
		$path = $this->fullPath($path);

		if ($path === '') {
			return $this->base;
		}

		$parts = array();

		foreach (explode('/', $path) as $seg) {
			if ($seg !== '') {
				$parts[] = rawurlencode($seg);
			}
		}

		return $this->base . '/' . implode('/', $parts);
	}

	/**
	 * @return array code/body/error
	 */
	private function request($method, $url, $timeout, $upload = null, $download = null, $body = null, $depth = null)
	{
		$ch = curl_init();
		$headers = array();

		if ($depth !== null) {
			$headers[] = 'Depth: ' . $depth;
		}

		if ($body !== null) {
			$headers[] = 'Content-Type: application/xml; charset=utf-8';
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

		if ($this->user !== '') {
			curl_setopt($ch, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
		}

		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}

		$fp = null;

		if ($upload !== null) {
			$fp = @fopen($upload, 'rb');

			if (!$fp) {
				$this->error = '本地文件无法读取：' . $upload;
				curl_close_safe($ch);
				return array('code' => 0, 'body' => '', 'error' => $this->error);
			}

			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_INFILE, $fp);
			curl_setopt($ch, CURLOPT_INFILESIZE, filesize($upload));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		}
		else if ($download !== null) {
			$fp = @fopen($download, 'wb');

			if (!$fp) {
				$this->error = '本地文件无法写入：' . $download;
				curl_close_safe($ch);
				return array('code' => 0, 'body' => '', 'error' => $this->error);
			}

			curl_setopt($ch, CURLOPT_FILE, $fp);
		}
		else {
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		}

		$result = curl_exec($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$errno = curl_errno($ch);
		$ret_body = is_string($result) ? $result : '';
		curl_close_safe($ch);

		if ($fp) {
			fclose($fp);
		}

		if ($errno) {
			return array('code' => $code, 'body' => '', 'error' => 'WebDAV 连接失败，请检查地址和网络');
		}

		return array('code' => $code, 'body' => $ret_body, 'error' => '');
	}

	private function fail($code)
	{
		if ($code == 401) {
			$this->error = 'WebDAV 账号或密码错误（HTTP 401）';
		}
		else if ($code == 403) {
			$this->error = 'WebDAV 拒绝访问（HTTP 403）';
		}
		else if ($code == 404) {
			$this->error = 'WebDAV 地址不存在（HTTP 404）';
		}
		else {
			$this->error = 'WebDAV 返回 HTTP ' . $code;
		}

		return false;
	}

	public function test()
	{
		$body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>';
		$ret = $this->request('PROPFIND', $this->base, 10, null, null, $body, 0);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if ($ret['code'] == 207 || $ret['code'] == 200) {
			return true;
		}

		return $this->fail($ret['code']);
	}

	public function mkDir($path)
	{
		$dir = $this->fullPath($path);

		if ($dir === '') {
			return true;
		}

		$cur = $this->base;

		foreach (explode('/', $dir) as $seg) {
			if ($seg === '') {
				continue;
			}

			$cur .= '/' . rawurlencode($seg);
			$ret = $this->request('MKCOL', $cur, 15);

			if ($ret['error'] !== '') {
				$this->error = $ret['error'];
				return false;
			}

			if (!in_array($ret['code'], array(200, 201, 204, 301, 405))) {
				return $this->fail($ret['code']);
			}
		}

		return true;
	}

	public function putFile($local, $remote)
	{
		$ret = $this->request('PUT', $this->urlFor($remote), $this->timeout, $local);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if (in_array($ret['code'], array(200, 201, 204))) {
			return true;
		}

		return $this->fail($ret['code']);
	}

	public function getFile($remote, $local)
	{
		$ret = $this->request('GET', $this->urlFor($remote), $this->timeout, null, $local);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			@unlink($local);
			return false;
		}

		if ($ret['code'] == 200) {
			return true;
		}

		@unlink($local);
		return $this->fail($ret['code']);
	}

	public function listDirs($path)
	{
		// 集合地址必须以 / 结尾，否则部分服务器会 301 跳转，跳转后请求体与响应都会异常
		$url = rtrim($this->urlFor($path), '/') . '/';
		$body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>';
		$ret = $this->request('PROPFIND', $url, 30, null, null, $body, 1);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if ($ret['code'] != 207) {
			return $this->fail($ret['code']);
		}

		return $this->parseList($ret['body'], $url);
	}

	/**
	 * 非标准 PROPFIND 响应的兜底解析：从 HTML/纯文本中提取一级子目录名
	 */
	private function parsePlainList($raw, $self)
	{
		$items = array();

		if (preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $raw, $m)) {
			$items = $m[1];
		}

		if (empty($items) && preg_match_all('#(/[A-Za-z0-9_%\.\-/]+/)#', $raw, $m)) {
			$items = $m[1];
		}

		$prefix = $self === '' ? '/' : $self . '/';
		$result = array();

		foreach ($items as $href) {
			$path = parse_url($href, PHP_URL_PATH);

			if (!is_string($path) || $path === '') {
				$path = $href;
			}

			$path = rtrim(urldecode($path), '/');

			if (substr($path, 0, 1) !== '/') {
				$path = $prefix . $path;
			}

			if ($path === $self || strpos($path, $prefix) !== 0) {
				continue;
			}

			$name = substr($path, strlen($prefix));

			if ($name === '' || strpos($name, '/') !== false) {
				continue;
			}

			$result[] = $name;
		}

		return array_values(array_unique($result));
	}

	private function parseList($body, $url)
	{
		$raw = $body;
		$body = ltrim($body);
		$self = rtrim(urldecode((string)parse_url($url, PHP_URL_PATH)), '/');

		// 去掉 UTF-8 BOM，部分服务器会带 BOM 导致 SimpleXML 解析失败
		if (substr($body, 0, 3) === "\xEF\xBB\xBF") {
			$body = substr($body, 3);
		}

		$xml = @simplexml_load_string($body);

		if (!$xml) {
			// ponytail: 部分 WebDAV 实现（如 kangle）PROPFIND 返回的不是 DAV XML，
			// 而是带路径的纯文本/HTML 列表；解析失败时按路径兜底提取一级子目录。
			$fallback = $this->parsePlainList($raw, $self);

			if (!empty($fallback)) {
				return $fallback;
			}

			$brief = trim(strip_tags($raw));
			$brief = preg_replace('/\s+/', ' ', $brief);

			if (function_exists('mb_substr')) {
				$brief = mb_substr($brief, 0, 120, 'UTF-8');
			}
			else {
				$brief = substr($brief, 0, 120);
			}

			$this->error = 'WebDAV 目录内容无法解析（长度 ' . strlen($raw) . ($brief === '' ? '' : '，内容：' . $brief) . '）';
			return false;
		}

		$result = array();

		foreach ($xml->children('DAV:') as $node) {
			$name = $node->getName();

			if ($name != 'response') {
				continue;
			}

			$href = '';
			$is_dir = false;

			foreach ($node->children('DAV:') as $child) {
				if ($child->getName() == 'href') {
					$href = (string)$child;
				}
			}

			if ($href === '') {
				continue;
			}

			$href_path = parse_url($href, PHP_URL_PATH);

			if ($href_path === null) {
				$href_path = $href;
			}

			$href_decoded = urldecode($href_path);

			if (substr($href, -1) == '/') {
				$is_dir = true;
			}
			else {
				foreach ($node->children('DAV:') as $child) {
					if ($child->getName() != 'propstat') {
						continue;
					}

					foreach ($child->children('DAV:') as $ps) {
						if ($ps->getName() != 'prop') {
							continue;
						}

						foreach ($ps->children('DAV:') as $prop) {
							if ($prop->getName() == 'resourcetype' && count($prop->children('DAV:')) > 0) {
								$is_dir = true;
							}
						}
					}
				}
			}

			$trimmed = rtrim($href_decoded, '/');

			if (!$is_dir || $trimmed === '' || $trimmed === $self) {
				continue;
			}

			$result[] = basename($trimmed);
		}

		return $result;
	}

	public function deleteDir($path)
	{
		$url = $this->urlFor($path);
		$body = '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>';
		$ret = $this->request('PROPFIND', $url, 30, null, null, $body, 1);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if ($ret['code'] == 207) {
			$xml = @simplexml_load_string($ret['body']);

			if (!$xml) {
				$this->error = 'WebDAV 目录内容无法解析';
				return false;
			}

			$self = rtrim((string)parse_url($url, PHP_URL_PATH), '/');
			$files = array();
			$dirs = array();

			foreach ($xml->children('DAV:') as $node) {
				if ($node->getName() != 'response') {
					continue;
				}

				foreach ($node->children('DAV:') as $child) {
					if ($child->getName() != 'href') {
						continue;
					}

					$href = (string)$child;

					if (parse_url($href, PHP_URL_PATH) === null) {
						$href = rtrim($url, '/') . '/' . $href;
					}

					if (rtrim((string)parse_url($href, PHP_URL_PATH), '/') === $self) {
						continue;
					}

					if (substr($href, -1) == '/') {
						$dirs[] = $href;
					}
					else {
						$files[] = $href;
					}
				}
			}

			foreach ($files as $href) {
				$this->request('DELETE', $href, 30);
			}

			foreach ($dirs as $href) {
				$this->request('DELETE', $href, 30);
			}
		}

		$ret = $this->request('DELETE', $url, 30);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if (in_array($ret['code'], array(200, 204)) || $ret['code'] == 404) {
			return true;
		}

		return $this->fail($ret['code']);
	}
}

/**
 * S3 兼容存储（Cloudflare R2 / AWS S3 / MinIO），路径风格 + SigV4
 */
class S3Storage extends RemoteStorage
{
	private $endpoint;
	private $region;
	private $bucket;
	private $access;
	private $secret;
	private $scheme;
	private $host;
	private $port;
	private $base_path = '';

	public function __construct($setting)
	{
		parent::__construct($setting);
		$this->timeout = $this->resolveTimeout('s3_timeout');
		$this->root = isset($setting['s3_dir']) ? $setting['s3_dir'] : '';
		$this->endpoint = isset($setting['s3_endpoint']) ? trim($setting['s3_endpoint']) : '';
		$this->region = isset($setting['s3_region']) ? trim($setting['s3_region']) : '';
		$this->bucket = isset($setting['s3_bucket']) ? trim($setting['s3_bucket']) : '';
		$this->access = isset($setting['s3_access_key']) ? trim($setting['s3_access_key']) : '';
		$this->secret = isset($setting['s3_secret_key']) ? trim($setting['s3_secret_key']) : '';

		if ($this->region === '') {
			$this->region = 'auto';
		}

		$this->scheme = (string)parse_url($this->endpoint, PHP_URL_SCHEME);
		$this->host = (string)parse_url($this->endpoint, PHP_URL_HOST);
		$this->port = intval(parse_url($this->endpoint, PHP_URL_PORT));
		$this->base_path = rtrim((string)parse_url($this->endpoint, PHP_URL_PATH), '/');
	}

	public function validate()
	{
		if (!function_exists('curl_init')) {
			return '未启用 curl 扩展，无法使用 S3 存储';
		}

		if ($this->endpoint === '' || $this->bucket === '' || $this->access === '' || $this->secret === '') {
			return 'S3 的 Endpoint、Bucket、AccessKey、SecretKey 必须填写';
		}

		if (!preg_match('!^https?://!i', $this->endpoint) || $this->host === '') {
			return 'S3 Endpoint 格式不正确，需以 http:// 或 https:// 开头';
		}

		return '';
	}

	protected function connect()
	{
		return true;
	}

	private function hostHeader()
	{
		$host = $this->host;

		if ($this->port > 0 && (($this->scheme == 'https' && $this->port != 443) || ($this->scheme == 'http' && $this->port != 80))) {
			$host .= ':' . $this->port;
		}

		return $host;
	}

	private function baseUrl()
	{
		$url = $this->scheme . '://' . $this->hostHeader();
		return $url . $this->base_path;
	}

	/**
	 * 由对象 Key 生成未编码的 URI
	 */
	private function keyUri($key)
	{
		$uri = $this->base_path . '/' . $this->bucket;

		if ($key !== '') {
			$uri .= '/' . ltrim($key, '/');
		}

		return $uri;
	}

	public function canonicalUri($uri)
	{
		if ($uri === '') {
			return '/';
		}

		$parts = array();

		foreach (explode('/', $uri) as $seg) {
			$parts[] = rawurlencode($seg);
		}

		$result = implode('/', $parts);

		if (substr($result, 0, 1) != '/') {
			$result = '/' . $result;
		}

		return $result;
	}

	public function canonicalQuery($query)
	{
		if (empty($query)) {
			return '';
		}

		ksort($query, SORT_STRING);
		$parts = array();

		foreach ($query as $key => $value) {
			$parts[] = rawurlencode($key) . '=' . rawurlencode($value);
		}

		return implode('&', $parts);
	}

	/**
	 * 计算 SigV4 签名，date 形如 20150830T123600Z
	 * @return array
	 */
	public function sign($method, $uri, $query, $headers, $payloadHash, $date, $service = 's3')
	{
		$canonicalUri = $this->canonicalUri($uri);
		$canonicalQuery = $this->canonicalQuery($query);
		$normalized = array();

		foreach ($headers as $key => $value) {
			$normalized[strtolower($key)] = trim($value);
		}

		ksort($normalized, SORT_STRING);
		$canonicalHeaders = '';
		$signedHeaders = array();

		foreach ($normalized as $key => $value) {
			$canonicalHeaders .= $key . ':' . $value . "\n";
			$signedHeaders[] = $key;
		}

		$signed = implode(';', $signedHeaders);
		$canonicalRequest = $method . "\n" . $canonicalUri . "\n" . $canonicalQuery . "\n" . $canonicalHeaders . "\n" . $signed . "\n" . $payloadHash;
		$scope_date = substr($date, 0, 8);
		$scope = $scope_date . '/' . $this->region . '/' . $service . '/aws4_request';
		$stringToSign = "AWS4-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
		$k_date = hash_hmac('sha256', $scope_date, 'AWS4' . $this->secret, true);
		$k_region = hash_hmac('sha256', $this->region, $k_date, true);
		$k_service = hash_hmac('sha256', $service, $k_region, true);
		$k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
		$signature = hash_hmac('sha256', $stringToSign, $k_signing);

		return array(
			'signature' => $signature,
			'signed_headers' => $signed,
			'authorization' => 'AWS4-HMAC-SHA256 Credential=' . $this->access . '/' . $scope
				. ', SignedHeaders=' . $signed . ', Signature=' . $signature,
			'canonical_request' => $canonicalRequest,
			'string_to_sign' => $stringToSign
		);
	}

	/**
	 * @return array code/body/error
	 */
	private function request($method, $uri, $query, $payloadHash, $timeout, $upload = null, $download = null)
	{
		$date = gmdate('Ymd\THis\Z');
		$headers = array(
			'host' => $this->hostHeader(),
			'x-amz-content-sha256' => $payloadHash,
			'x-amz-date' => $date
		);
		$signed = $this->sign($method, $uri, $query, $headers, $payloadHash, $date);
		$url = $this->baseUrl() . $this->canonicalUri($uri);
		$query_string = $this->canonicalQuery($query);

		if ($query_string !== '') {
			$url .= '?' . $query_string;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Host: ' . $headers['host'],
			'x-amz-content-sha256: ' . $payloadHash,
			'x-amz-date: ' . $date,
			'Authorization: ' . $signed['authorization'],
			'Expect:'
		));

		$fp = null;

		if ($upload !== null) {
			$fp = @fopen($upload, 'rb');

			if (!$fp) {
				$this->error = '本地文件无法读取：' . $upload;
				curl_close_safe($ch);
				return array('code' => 0, 'body' => '', 'error' => $this->error);
			}

			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_setopt($ch, CURLOPT_INFILE, $fp);
			curl_setopt($ch, CURLOPT_INFILESIZE, filesize($upload));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		}
		else if ($download !== null) {
			$fp = @fopen($download, 'wb');

			if (!$fp) {
				$this->error = '本地文件无法写入：' . $download;
				curl_close_safe($ch);
				return array('code' => 0, 'body' => '', 'error' => $this->error);
			}

			curl_setopt($ch, CURLOPT_FILE, $fp);
		}
		else {
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		}

		$result = curl_exec($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$errno = curl_errno($ch);
		$body = is_string($result) ? $result : '';
		curl_close_safe($ch);

		if ($fp) {
			fclose($fp);
		}

		if ($errno) {
			return array('code' => $code, 'body' => '', 'error' => 'S3 连接失败，请检查 Endpoint 和网络');
		}

		return array('code' => $code, 'body' => $body, 'error' => '');
	}

	private function failure($code)
	{
		if ($code == 403) {
			$this->error = 'S3 拒绝访问（HTTP 403），请检查密钥和权限';
		}
		else if ($code == 404) {
			$this->error = 'S3 资源不存在（HTTP 404），请检查 Bucket';
		}
		else if ($code == 301) {
			$this->error = 'S3 区域(region)配置不正确（HTTP 301）';
		}
		else if ($code == 400) {
			$this->error = 'S3 请求被拒绝（HTTP 400），请检查 Endpoint 与区域';
		}
		else {
			$this->error = 'S3 返回 HTTP ' . $code;
		}

		return false;
	}

	public function test()
	{
		$query = array('list-type' => '2', 'max-keys' => '1');
		$prefix = $this->fullPath('');

		if ($prefix !== '') {
			$query['prefix'] = $prefix . '/';
		}

		$ret = $this->request('GET', $this->keyUri(''), $query, hash('sha256', ''), 10);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if ($ret['code'] == 200) {
			return true;
		}

		return $this->failure($ret['code']);
	}

	public function mkDir($path)
	{
		return true;
	}

	public function putFile($local, $remote)
	{
		$key = $this->fullPath($remote);

		if (!is_file($local)) {
			$this->error = '本地文件不存在：' . $local;
			return false;
		}

		$hash = @hash_file('sha256', $local);

		if ($hash === false) {
			$this->error = '本地文件校验值计算失败：' . basename($local);
			return false;
		}

		$ret = $this->request('PUT', $this->keyUri($key), array(), $hash, $this->timeout, $local);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if ($ret['code'] == 200) {
			return true;
		}

		return $this->failure($ret['code']);
	}

	public function getFile($remote, $local)
	{
		$key = $this->fullPath($remote);
		$ret = $this->request('GET', $this->keyUri($key), array(), hash('sha256', ''), $this->timeout, null, $local);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			@unlink($local);
			return false;
		}

		if ($ret['code'] == 200) {
			return true;
		}

		@unlink($local);
		return $this->failure($ret['code']);
	}

	public function listDirs($path)
	{
		$prefix = $this->fullPath($path);
		$prefix = $prefix === '' ? '' : $prefix . '/';
		$query = array('list-type' => '2', 'delimiter' => '/', 'max-keys' => '1000');

		if ($prefix !== '') {
			$query['prefix'] = $prefix;
		}

		$ret = $this->request('GET', $this->keyUri(''), $query, hash('sha256', ''), 60);

		if ($ret['error'] !== '') {
			$this->error = $ret['error'];
			return false;
		}

		if ($ret['code'] != 200) {
			return $this->failure($ret['code']);
		}

		$xml = @simplexml_load_string($ret['body']);

		if (!$xml) {
			$this->error = 'S3 返回内容无法解析';
			return false;
		}

		$xml->registerXPathNamespace('s', 'http://s3.amazonaws.com/doc/2006-03-01/');
		$items = $xml->xpath('//s:CommonPrefixes/s:Prefix');
		$result = array();

		if (!$items) {
			return $result;
		}

		foreach ($items as $item) {
			$item_prefix = (string)$item;

			if ($prefix !== '' && strpos($item_prefix, $prefix) === 0) {
				$item_prefix = substr($item_prefix, strlen($prefix));
			}

			$item_prefix = rtrim($item_prefix, '/');

			if ($item_prefix !== '') {
				$result[] = basename($item_prefix);
			}
		}

		return $result;
	}

	public function deleteDir($path)
	{
		$prefix = $this->fullPath($path);

		if ($prefix === '') {
			return false;
		}

		$prefix .= '/';
		$token = '';
		$pages = 0;

		// ponytail: 逐个对象删除，对象极多时较慢；后续可改用 POST ?delete 批量删除接口
		while ($pages < 100) {
			$query = array('list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000');

			if ($token !== '') {
				$query['continuation-token'] = $token;
			}

			$ret = $this->request('GET', $this->keyUri(''), $query, hash('sha256', ''), 60);

			if ($ret['error'] !== '') {
				$this->error = $ret['error'];
				return false;
			}

			if ($ret['code'] != 200) {
				return $this->failure($ret['code']);
			}

			$xml = @simplexml_load_string($ret['body']);

			if (!$xml) {
				$this->error = 'S3 返回内容无法解析';
				return false;
			}

			$xml->registerXPathNamespace('s', 'http://s3.amazonaws.com/doc/2006-03-01/');
			$keys = $xml->xpath('//s:Contents/s:Key');

			if ($keys) {
				foreach ($keys as $key) {
					$del = $this->request('DELETE', $this->keyUri((string)$key), array(), hash('sha256', ''), 60);

					if ($del['error'] !== '' || !in_array($del['code'], array(200, 204))) {
						$this->error = 'S3 删除对象失败：' . (string)$key;
						return false;
					}
				}
			}

			$truncated = $xml->xpath('//s:IsTruncated');
			$is_truncated = $truncated && strtolower((string)$truncated[0]) == 'true';

			if (!$is_truncated) {
				break;
			}

			$tokens = $xml->xpath('//s:NextContinuationToken');
			$token = $tokens ? (string)$tokens[0] : '';

			if ($token === '') {
				break;
			}

			++$pages;
		}

		return true;
	}
}

if (!function_exists('curl_close_safe')) {
	/**
	 * PHP 8 起 curl 句柄为对象且无需手动关闭，兼容旧版本
	 */
	function curl_close_safe($ch)
	{
		if (PHP_VERSION_ID < 80000) {
			curl_close($ch);
		}
	}
}
