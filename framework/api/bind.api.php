<?php
class BindAPI extends API
{
	private $domain_config_dir = "/vhs/bind/etc/";
	private $zonedir = "/vhs/bind/etc/zone/";
	private $os = true;
	private $include_file = "record.conf";
	private $rndc_config = "rndc.conf";
	private $named_config = "named.conf";
	private $bind_dir = "/vhs/bind/";
	private $views;
	private $key_tmp_dir = "/tmp/";
	private $key_host = "cnc-key";
	public function __construct()
	{
	}
	/**
	 * example www.kanglesoft.com
	 * @param $domain
	 */
	public function domainDig($domain, $host = "localhost", $view = "any")
	{
		if ($host == "localhost") {
			$host = "127.0.0.1";
		}
		$v = daocall("views", "getView", array($view));
		if (!$v) {
			$this->setErrorMsg("cann't find view: " . $view);
			return false;
		}
		$key = $view . "_key:" . $v["key"];
		$cmd = $this->bind_dir . "bin/dig @" . $host . " -y " . $key . " " . $domain;
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->setErrorMsg("dig cmd run error");
			return false;
		}
		return implode("\n", $out);
	}
	public function checkBind()
	{
		if (file_exists($this->bind_dir . "sbin/named")) {
			$cmd = $this->bind_dir . "sbin/rndc status";
			exec($cmd, $out, $status);
			if ($status != 0 && $status != 0 - 1) {
				$this->setErrorMsg("rndc status cmd run failed status:" . $status);
				return false;
			}
			if (count($out) < 0) {
				$this->setErrorMsg("rndc status return empty ");
				return false;
			}
			foreach ($out as $o) {
				if (strripos(trim($o), "running")) {
					return true;
				}
			}
		}
		return false;
	}
	/**
	 * @deprecated
	 * Enter description here ...
	 * @param unknown_type $filename
	 */
	public function checkConf($filename)
	{
		return true;
	}
	public function checkZone($domain, $filename)
	{
		$cmd = $this->bind_dir . "sbin/named-checkzone " . $domain . " " . $filename;
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->setErrorMsg("checkzone cmd result status=" . $status);
			return false;
		}
		if (count($out) <= 0) {
			$this->setErrorMsg("checkzone cmd out min 0 cmd=" . $cmd);
			return false;
		}
		foreach ($out as $o) {
			if (strtolower(trim($o)) == "ok") {
				return true;
			}
		}
		$this->setErrorMsg(implode(" ", $out));
		return false;
	}
	private function setErrorMsg($msg)
	{
		trigger_error($msg);
		$GLOBALS["bind_error_msg"] = $msg;
	}
	public function mk_dir()
	{
		if (!file_exists($this->zonedir)) {
			mkdir($this->zonedir, 448, true);
		}
		$this->mkdirAllHash();
		return true;
	}
	public function generateConf()
	{
		if (!file_exists($this->domain_config_dir . $this->named_config)) {
			$key = $this->getBindSkey();
			if ($key === false) {
				$this->setErrorMsg("bindkey is empty line " . 134);
				return false;
			}
			if (!$this->generateRndcConf($key) || !$this->generateNamedConf($key)) {
				return false;
			}
		}
		return true;
	}
	public function namedRestart()
	{
		exec("killall named", $out, $status);
		if ($status != 0 && $status != 0 - 1) {
		}
		exec("/vhs/bind/sbin/named", $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->setErrorMsg(implode(" ", $out));
			return false;
		}
		daocall("setting", "add", array("dns_init", 1));
		return true;
	}
	/**
	 * bind初始化
	 * Enter description here ...
	 */
	public function bindInit($sync = true, $master = true)
	{
		$views = daocall("views", "viewsList", array());
		if (count($views) <= 0) {
			$this->setErrorMsg("DNS线路不能为空，请先同步DNS线路 line " . 166);
			return false;
		}
		if ($master) {
			if (!$this->rundViewKey()) {
				return false;
			}
		}
		$this->mk_dir();
		if (!$this->generateConf()) {
			return false;
		}
		if ($master) {
			if (!$this->writeAllDomainConf()) {
				return false;
			}
		}
		if ($sync) {
			if (!apicall("dnssync", "syncAllInit", array())) {
				return false;
			}
		}
		if (!$this->writeDomainConfig()) {
			$this->setErrorMsg("write domain conf is failed");
			return false;
		}
		if (!$this->namedRestart()) {
			return false;
		}
		return true;
	}
	/**
	 * 重写所有域名的配置文件
	 */
	public function writeAllDomainConf()
	{
		$domains = daocall("domains", "domainList", array());
		if (count($domains) <= 0) {
			return true;
		}
		foreach ($domains as $domain) {
			if (!$this->writeZoneFile($domain["name"], false)) {
				$this->setErrorMsg("writezonefile failed " . $domain["name"] . " line:" . 212);
				return false;
			}
		}
		return $this->rndcReload();
	}
	/**
	 * 重写一个域名的配置文件
	 * @param unknown_type $domain array
	 */
	public function writeZoneFile($domain, $reload = true)
	{
		$views = daocall("views", "viewsList", array());
		$this->views = $views;
		if (count($views) <= 0) {
			$this->setErrorMsg("views is empty line " . 227);
			return false;
		}
		$zones = $this->getDomainZones($domain);
		foreach ($views as $v) {
			$zone = $zones[$v["name"]];
			if (!$zone) {
				$zone = $zones["any"];
			}
			if (!$this->domainWriteConf($domain, $v["name"], $zone)) {
				$this->setErrorMsg("write domain conf failed line:" . 237);
				return false;
			}
		}
		if ($reload) {
			return $this->rndcReload();
		}
		return true;
	}
	/**
	 * 重写域名的解析文件
	 * @param  $domain
	 * @param  $view
	 * @param  $zone
	 */
	private function domainWriteConf($domain, $view, $zone)
	{
		$hash_dir = $this->getHashDir($domain);
		$domain_dir = $this->zonedir . $hash_dir;
		if (!file_exists($domain_dir)) {
			@mkdir($domain_dir, 448, true);
		}
		$ns = "root.kanglesoft.com.";
		$email = "www.kanglesoft.com.";
		$str = "\$TTL    3600" . "\n";
		$str .= "\$ORIGIN " . $domain . ".\n";
		$str .= "@\tSOA      " . $ns . " " . $email . " (\n";
		$str .= "\t\t" . (time() - 1000000000) . "\n";
		$str .= "\t\t86400\n";
		$str .= "\t\t15M;\n";
		$str .= "\t\t1W;\n";
		$str .= "\t\t10 )\n";
		$arr["name"] = $domain;
		$fileds = array("server");
		$info = daocall("domains", "getDomain", array($arr, $fileds));
		if ($info["server"]) {
			$server = daocall("servers", "serverGet", array($info));
			if ($server["ns"]) {
				$str .= "\t\tNS\t\t" . $server["ns"] . "\n";
			}
			$slaves = daocall("slaves", "slavesGet", array($info));
			if (0 < count($slaves)) {
				foreach ($slaves as $slave) {
					$str .= "\t\tNS\t\t" . $slave["ns"] . "\n";
				}
			}
		}
		if (0 < count($zone)) {
			foreach ($zone as $zon) {
				foreach ($zon as $z) {
					$str .= $z["name"] . "\t" . $z["ttl"] . "\t" . $z["type"];
					if ($z["type"] == "MX") {
						$str .= "\t" . $z["prio"];
					}
					$str .= "\t" . $z["value"] . "\n";
				}
			}
		}
		$view_file = $domain_dir . "/" . $view;
		if (file_exists($view_file)) {
			$view_tmp_file = $view_file . ".tmp";
			$fp = fopen($view_tmp_file, "wt");
			if (!$fp) {
				$this->setErrorMsg("打开文件失败:" . $view_tmp_file . " line " . 301);
				return false;
			}
			fwrite($fp, $str);
			fclose($fp);
			$check = $this->checkZone($domain, $view_tmp_file);
			if ($check === false) {
				$this->setErrorMsg("check zone file failed line:" . 308);
				return false;
			}
			if ($check === true || !$check[0]) {
				unlink($view_file);
				if (!rename($view_tmp_file, $view_file)) {
					$this->setErrorMsg("无法重命名文件" . $view_tmp_file . " line " . 314);
					return false;
				}
				if (!file_exists($view_file)) {
					$this->setErrorMsg("file not found " . $view_file . " line:" . 318);
					return false;
				}
				return true;
			}
			$this->setErrorMsg(implode(" ", $check));
			return false;
		}
		$fp = fopen($domain_dir . "/" . $view, "wt");
		if (!$fp) {
			$this->setErrorMsg("打开文件失败: " . $domain_dir . "/" . $view . " line " . 328);
			return false;
		}
		fwrite($fp, $str);
		fclose($fp);
		return true;
	}
	/**
	 * 重写所有域名的解析包含文件record.conf
	 */
	public function writeDomainConfig($master = null, $domains = null)
	{
		if ($domains == null) {
			$domains = daocall("domains", "domainList", array());
			if (count($domains) <= 0) {
				return true;
			}
		}
		$views = daocall("views", "viewsList", array());
		if ($master == null) {
			$slaves = daocall("slaves", "slavesGet", array());
		}
		$string = "";
		foreach ($views as $view) {
			$string .= "key\t\"" . $view["name"] . "_key\" {\n";
			$string .= "\t\talgorithm hmac-md5;\n";
			$string .= "\t\tsecret \"" . $view["key"] . "\";\n";
			$string .= "};\n";
		}
		foreach ($views as $view) {
			$match_key = "";
			foreach ($views as $view2) {
				if ($view["name"] == $view2["name"]) {
					$match_key .= "key " . $view2["name"] . "_key; ";
				} else {
					$match_key .= "!key " . $view2["name"] . "_key; ";
				}
			}
			$string .= "view \"" . $view["name"] . "\" {\n";
			$string .= "\tmatch-clients { " . $match_key . $view["name"] . ";};\n";
			if ($master == null) {
				$string .= "\tserver 127.0.0.1 {\n";
				$string .= "\t\tkeys { " . $view["name"] . "_key; };\n";
				$string .= "\t};\n";
				if (0 < count($slaves)) {
					foreach ($slaves as $slave) {
						$string .= "\tserver " . $slave["slave"] . " {\n";
						$string .= "\t\tkeys { " . $view["name"] . "_key; };\n";
						$string .= "\t};\n";
					}
				}
				$string .= "\tallow-transfer { key \"" . $view["name"] . "_key\";};\n";
			} else {
				$string .= "\tserver " . $master . " {\n";
				$string .= "\t\tkeys { " . $view["name"] . "_key; };\n";
				$string .= "\t};\n";
			}
			foreach ($domains as $do) {
				if (!is_array($do)) {
					$do = (array) $do;
				}
				$string .= "\tzone \"" . $do["name"] . "\" IN {\n";
				if ($master == null) {
					$string .= "\t\ttype master;\n";
					$string .= "\t\tfile \"" . $this->getDoaminFile($do["name"]) . "/" . $view["name"] . "\";\n";
				} else {
					$string .= "\t\ttype slave;\n";
					$string .= "\t\tfile \"" . $this->getSlaveFileName($do["name"], $view["name"]) . "\";\n";
					$string .= "\t\tmasters  {\n";
					$string .= "\t\t\t" . $master . ";\n";
					$string .= "\t\t};\n";
				}
				$string .= "\t};\n";
			}
			$string .= "};\n";
		}
		$record_file = $this->domain_config_dir . $this->include_file;
		$fp = fopen($record_file, "wt");
		if (!$fp) {
			$this->setErrorMsg("打开文件失败" . $record_file . " line " . 429);
			return false;
		}
		fwrite($fp, $string);
		fclose($fp);
		$this->rndcReload();
		if ($master == null) {
			apicall("dnssync", "syncAllNOdeDns", array());
		}
		return true;
	}
	public function getBindDir()
	{
		return $this->bind_dir;
	}
	private function getSlaveFileName($domain, $view)
	{
		return $this->getHashDir($domain) . "." . $view;
	}
	public function rundViewKey()
	{
		$views = daocall("views", "viewsList", array());
		if (count($views) <= 0) {
			return false;
		}
		foreach ($views as $view) {
			if (0 < strlen($view["key"])) {
				continue;
			}
			$arr["key"] = $this->getDnsKey();
			if (!$arr["key"]) {
				$this->setErrorMsg("Dnskey is empty line " . 462);
				return false;
			}
			daocall("views", "viewUpdate", array($view["name"], $arr));
		}
		return true;
	}
	public function mkdirAllHash()
	{
		$i = 0;
		while ($i <= 255) {
			$str = sprintf("%02x", $i);
			if (file_exists($this->zonedir . $str)) {
				continue;
			}
			@mkdir($this->zonedir . $str, 448);
			++$i;
		}
	}
	private function getDomainZones($domain)
	{
		$zones = array("any" => array());
		$records = daocall("records", "recordListByDomain", array($domain, false));
		foreach ($records as $re) {
			$zones[$re["view"]][$re["name"]][] = $re;
		}
		$records = daocall("records", "recordListByDomain", array($domain, true));
		foreach ($records as $re) {
			foreach ($zones as $view => &$v) {
				if ($view == "any" || !$v[$re["name"]]) {
					$v[$re["name"]][] = $re;
				}
			}
		}
		return $zones;
	}
	/**
	 * 取得域名哈稀目录
	 * Enter description here ...
	 * @param unknown_type $domain
	 */
	private function getHashDir($domain)
	{
		$domain_md5 = strtolower(md5($domain));
		$hash_dir = substr($domain_md5, 0, 2);
		return $hash_dir . "/" . strtolower($domain);
	}
	public function domainAdd($domain)
	{
		$domain_dir = $this->domain_config_dir . $this->getHashDir($domain);
		if (!file_exists($domain_dir)) {
			mkdir($domain_dir, 448, true);
		}
		$this->writeZoneFile($domain, false);
		return $this->writeDomainConfig();
	}
	/**
	 * 删除哈唏目录下的域名解析文件，通知重写named.conf
	 * @param unknown_type $domain
	 */
	public function domainDel($domain)
	{
		$domain_dir = $this->domain_config_dir . $this->getHashDir($domain);
		@unlink($domain_dir . "/" . $domain);
		return $this->writeDomainConfig();
	}
	public function rndcReload($zone = null, $view = null)
	{
		exec("/vhs/bind/sbin/rndc reload", $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->setErrorMsg("重新加载失败 状态 " . $status . " line " . 535);
			return false;
		}
		return true;
	}
	/**
	 * 取得域名哈唏的文件名，
	 * @param unknown_type $domain
	 */
	private function getDoaminFile($domain)
	{
		return $this->getHashDir($domain);
	}
	private function displayMsg($msg)
	{
		if (is_array($msg)) {
			print_r($msg);
			return NULL;
		}
		echo "msg=" . $msg . "\r\n";
	}
	private function getBindSkey()
	{
		$cmd = "/vhs/bind/sbin/rndc-confgen -r /dev/urandom|grep 'secret'";
		$out = $this->runCmd($cmd);
		if ($out === false) {
			$this->setErrorMsg("rndc-confgen cmd is exec failed line " . 562);
			return false;
		}
		if (count($out) < 0) {
			$this->setErrorMsg("getBindSkey cmd out is empty line " . 566);
			return false;
		}
		foreach ($out as $o) {
			if (stripos($o, "secret")) {
				return $o;
			}
		}
		return false;
	}
	private function getDnsKey()
	{
		$cmd = "/vhs/bind/sbin/dnssec-keygen -r /dev/urandom -K /tmp -a hmac-md5 -b 256 -n HOST " . $this->key_host;
		$out = $this->runCmd($cmd);
		if ($out === false && count($out) < 0) {
			$this->setErrorMsg("rndc-confgen cmd is exec failed line " . 583);
			return false;
		}
		foreach ($out as $o) {
			if (substr($o, 0, 1) == "K") {
				$file_text = file_get_contents($this->key_tmp_dir . $o . ".key");
				if ($file_text === false) {
					$this->setErrorMsg("file_get_contents file is failed line " . 590);
					return false;
				}
				$file_arr = explode(" ", $file_text);
				unlink($this->key_tmp_dir . $o . ".key");
				unlink($this->key_tmp_dir . $o . ".private");
				return trim($file_arr[6]);
			}
		}
		return false;
	}
	private function runCmd($cmd)
	{
		exec($cmd, $out, $status);
		if ($status != 0 && $status != 0 - 1) {
			$this->setErrorMsg("runcmd failed status=" . $status . " line " . 605);
			return false;
		}
		return $out;
	}
	private function generateRndcConf($key)
	{
		if (!file_exists($this->domain_config_dir)) {
			mkdir($this->domain_config_dir, 448, true);
		}
		$str = "key \"rndc-key\" {\r\n\t\t\t\talgorithm hmac-md5;\r\n        \t\t" . $key . "\r\n\t\t\t\t};\r\n        \t\toptions {\r\n        \t\t\tdefault-key \"rndc-key\";\r\n        \t\t\tdefault-server 127.0.0.1;\r\n        \t\t\tdefault-port 953;\r\n\t\t\t\t};";
		$fp = fopen($this->domain_config_dir . "/" . $this->rndc_config, "wt");
		if (!$fp) {
			$this->setErrorMsg("打开文件失败:" . $this->domain_config_dir . "/" . $this->rndc_config . " line " . 626);
			return false;
		}
		fwrite($fp, $str);
		fclose($fp);
		return true;
	}
	/**
	 * 用于
	 * Enter description here ...
	 * @param unknown_type $key
	 */
	private function generateNamedConf($key)
	{
		if (!file_exists($this->domain_config_dir)) {
			mkdir($this->domain_config_dir, 448, true);
		}
		$string = "options {\n";
		$string .= "\t\tdirectory \"" . $this->zonedir . "\";\n";
		$string .= " \t    notify  yes;\n";
		$string .= "\t\trecursion no;\n";
		$string .= "\t\tallow-transfer  { 127.0.0.1; };\n";
		$string .= "};\n";
		$string .= "key \"rndc-key\" {\n";
		$string .= "\t\talgorithm hmac-md5;\n";
		$string .= "\t\t" . $key . "\n";
		$string .= "};\n";
		$string .= "controls {\n";
		$string .= "\t\tinet 127.0.0.1 port 953\n";
		$string .= "   \t\tallow { 127.0.0.1; } keys { \"rndc-key\"; };\n";
		$string .= "};\n";
		$string .= "include \"/vhs/bind/etc/ip.conf\";\n";
		$string .= "include \"" . $this->domain_config_dir . $this->include_file . "\";\n";
		$name_file = $this->domain_config_dir . "/" . $this->named_config;
		$fp = fopen($name_file, "wt");
		if (!$fp) {
			$this->setErrorMsg("不能打开文件" . $name_file . " line " . 669);
			return false;
		}
		fwrite($fp, $string);
		fclose($fp);
		return true;
	}
}