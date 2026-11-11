<?php
needRole('vhost');
class DomainControl extends Control
{
	const MIANBEIAN_INFO_TYPE = 6;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:ssl');
		load_lib('pub:access');
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function show()
	{
		$vhost = getRole('vhost');
		// Read vhost from DB so SSL certificate state changes show immediately;
		// the template's $user is assigned from $_SESSION in index.php before dispatch.
		$user = daocall('vhost', 'getVhost', array($vhost));
		$this->assign('user', $user);
		$cname_host = daocall('setting', 'get', array('cname_host'));

		if ($cname_host) {
			$domain_note = 'CNAME解析到：' . str_replace('*',$user['name'],$cname_host);
		}
		else {
			$domain_note = 'A记录到IP：' . gethostbyname($_SERVER['SERVER_NAME']);
		}

		$this->_tpl->assign('domain_note', $domain_note);
		$list = daocall('vhostinfo', 'getDomain', array($vhost));
		$bindings = array();
		foreach (ep_iter($list) as $domain) {
			$binding = EpSsl::splitBinding($domain['value']);
			$domain['value'] = $binding['dir'];
			$domain['ssl'] = $binding['ssl'] ? 1 : 0;
			$domain['proto'] = null;
			$domain['mianbeian'] = 0;
			$bindings[] = $domain;
		}
		$list = $bindings;

		if ($user['cdn']) {
			$domains = array();
			$id = 0;
			$mianbeian = array();
			$mianbeianRows = daocall('vhostinfo', 'getInfo', array($vhost, self::MIANBEIAN_INFO_TYPE));

			if (is_array($mianbeianRows)) {
				foreach ($mianbeianRows as $row) {
					$mianbeian[$row['name']] = 1;
				}
			}

			foreach (ep_iter($list) as $domain) {
				$isSsl = $domain['ssl'];
				$proto = null;
				if(substr($domain['value'], 0, 9) == 'server://'){
					$start = strpos($domain['value'],'nodes=')+6;
					$end = strpos($domain['value'],':',$start);
					$end2 = strpos($domain['value'],':',$end+1);
					$dir = substr($domain['value'], $start, $end - $start);
					$port = substr($domain['value'], $end+1, $end2 - $end - 1);
					if($port != '443s' && $port != '80'){
						$dir .= ':' . $port;
					}
					if(strpos($domain['value'], 'proto=tcp') !== false){
						$proto = 'tcp';
					}elseif(strpos($domain['value'], 'proto=https') !== false){
						$proto = 'https';
					}elseif(strpos($domain['value'], '|') !== false && strpos($domain['value'], ':443s:') !== false){
						$proto = 'follow';
					}
				}else{
					$dir = substr($domain['value'], 7, strlen($domain['value']));
					$dir = trim($dir, '/');
				}
				$domain['value'] = $dir;
				$domain['id'] = $id;
				$domain['ssl'] = $isSsl;
				$domain['proto'] = $proto;
				$domain['mianbeian'] = isset($mianbeian[$domain['name']]) ? 1 : 0;
				$domains[] = $domain;
				++$id;
			}

			$list = $domains;
			$this->_tpl->assign('subdir_flag', 1);
		}
		else {
			$id = 0;
			$li = array();

			foreach (ep_iter($list) as $domain) {
				$domain['id'] = $id;
				$li[] = $domain;
				++$id;
			}

			$list = $li;
			$this->_tpl->assign('subdir_flag', $user['subdir_flag']);
			$this->_tpl->assign('default_subdir', $user['subdir']);
		}

		if (strpos($user['port'], 's')!==false) {
			$this->_tpl->assign('ssl', 1);
		}

		$list = ep_iter($list);
		$sum = count($list);
		$this->_tpl->assign('sum', $sum);
		$this->_tpl->assign('list', $list);
		return $this->fetch('domain/show.html');
	}

	public function addForm()
	{
		$vhost = getRole('vhost');
		$this->_tpl->assign('action', 'add');
		$user = $_SESSION['user'][$vhost];
		$this->_tpl->assign('subdir_flag', $user['subdir_flag']);
		$this->_tpl->assign('default_subdir', $user['subdir']);
		return $this->_tpl->fetch('domain/add.html');
	}

	private function get_main_domain($domain){
		if(!strpos($domain,'.')) return null;
		if(filter_var($domain, FILTER_VALIDATE_IP))return $domain;
		$top_domain_list = @file_get_contents(dirname(__FILE__) . '/domain_root.txt');
		if ($top_domain_list === false) {
			$top_domain_list = '';
		}
		$top_domain_list = explode("\n", $top_domain_list);
		$data = explode('.', $domain);
		$co_ta = count($data);

		if ($co_ta < 2) {
			return null;
		}

		$domain_name = $data[$co_ta-2].'.'.$data[$co_ta-1];
		if(in_array('.'.$domain_name, $top_domain_list)){
			if ($co_ta < 3 || $data[$co_ta-3] === '' || $data[$co_ta-3] === null) return null;
			$domain_name = $data[$co_ta-3].'.'.$domain_name;
		}
		return $domain_name;
	}

	private function parseCdnOrigin($subdir, $proto)
	{
		$subdir = trim($subdir);
		if ($proto === 'follow' || $proto === 'tcp') {
			return false;
		}
		$ssl = ($proto === 'https');
		$host = '';
		$port = 0;

		if (strncasecmp($subdir, 'server://', 9) === 0) {
			if ($proto === 'follow' || strpos($subdir, 'proto=tcp') !== false || strpos($subdir, '|/nodes=') !== false) {
				return false;
			}
			if (strpos($subdir, 'proto=https') !== false) {
				$ssl = true;
			}
			if (preg_match('/nodes=([^:]+):([0-9]+)(s?)/i', $subdir, $m)) {
				$host = $m[1];
				$port = intval($m[2]);
				if ($m[3] === 's') {
					$ssl = true;
				}
			}
		}
		elseif (preg_match('#^(https?)://#i', $subdir, $m)) {
			$u = parse_url($subdir);
			if ($u && !empty($u['host'])) {
				$host = $u['host'];
				$ssl = (strtolower($m[1]) === 'https');
				$port = isset($u['port']) ? intval($u['port']) : ($ssl ? 443 : 80);
			}
		}
		else {
			if (strpos($subdir, ':') !== false) {
				list($host, $port) = explode(':', $subdir, 2);
				$port = intval($port);
			}
			else {
				$host = $subdir;
				$port = $ssl ? 443 : 80;
			}
		}

		if ($host === '' || $port < 1 || $port > 65535) {
			return false;
		}
		return array('host' => $host, 'port' => $port, 'ssl' => $ssl ? 1 : 0);
	}

	private function saveMianbeianOrigin($vhost, $domain, $origin)
	{
		daocall('vhostinfo', 'delInfo', array($vhost, $domain, self::MIANBEIAN_INFO_TYPE, null));
		if (!$origin) {
			return true;
		}
		return daocall('vhostinfo', 'addInfo', array($vhost, $domain, self::MIANBEIAN_INFO_TYPE, json_encode($origin), false));
	}

	private function syncMianbeianRule($vhost, $domain, $origin)
	{
		$access = new Access($vhost);
		$name = '!mianbeian_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $domain);
		$access->delChainByName('BEGIN', $name);

		if (!$origin) {
			return true;
		}

		$port = $origin['port'] . (!empty($origin['ssl']) ? 's' : '');
		$arr = array('action' => 'continue', 'name' => $name);
		$acl = (substr($domain, 0, 2) === '*.' || $domain === '*') ? 'acl_wide_host' : 'acl_host';
		// 上游由绑定的 server:// 节点提供（含 60 秒连接复用），这里只改回源 Host。
		$models = array(
			$acl => array('v' => $domain),
			'mark_host' => array('host' => $origin['host'], 'port' => $port, 'rewrite' => 1),
		);
		return $access->addChain('BEGIN', $arr, $models);
	}

	public function info()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$domain = strtolower(trim(ep_request('domain')));
		$ret = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if (is_array($ret) && !empty($ret[0])) {
			$result = array();
			$result['code'] = 0;
			$binding = EpSsl::splitBinding($ret[0]['value']);
			$value = $binding['dir'];
			if ($user['cdn']) {
				$result['cdn'] = 1;
				$proto = 'http';
				if(substr($value, 0, 9) == 'server://'){
					$start = strpos($value,'nodes=')+6;
					$end = strpos($value,':',$start);
					$end2 = strpos($value,':',$end+1);
					$dir = substr($value, $start, $end - $start);
					$port = substr($value, $end+1, $end2 - $end - 1);
					if($port != '443s' && $port != '80'){
						$port = rtrim($port, 's');
						$dir .= ':' . $port;
					}
					if(strpos($value, 'proto=tcp') !== false){
						if(!getRole('admin')){
							exit(json_encode(array('code'=>-1, 'msg'=>'tcp回源协议只能由管理员修改')));
						}
						$proto = 'tcp';
					}elseif(strpos($value, 'proto=https') !== false){
						$proto = 'https';
					}elseif(strpos($value, '|') !== false && strpos($value, ':443s:') !== false){
						$proto = 'follow';
					}
				}else{
					$dir = substr($value, 7, strlen($value));
					$dir = trim($dir, '/');
				}
				$result['proto'] = $proto;
				$result['subdir'] = $dir;
				$flag = daocall('vhostinfo', 'getInfo', array($vhost, self::MIANBEIAN_INFO_TYPE, $domain));
				$result['mianbeian'] = (is_array($flag) && !empty($flag[0])) ? 1 : 0;
			}else{
				$result['cdn'] = 0;
				$result['subdir'] = $value;
			}
			exit(json_encode($result));
		}else{
			exit(json_encode(array('code'=>-1, 'msg'=>'该域名不存在')));
		}
	}

	public function add()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$submitted = EpSsl::splitBinding(trim(ep_request('subdir')));
		$subdir = $submitted['dir'];
		$proto = trim(ep_request('proto'));
		$vhost = getRole('vhost');
		$replace = intval($_REQUEST['replace']);
		$isreplace = false;
		if(!$domain)exit('域名不能为空');
		$domain_bind = daocall('setting', 'get', array('domain_bind'));

		if ($domain != '*') {
			$ret = daocall('vhostinfo', 'findDomain', array($domain));

			if ($ret) {
				if (($ret['vhost'] != $vhost)) {
					$vhostinfo = daocall('vhost', 'getVhost', array($ret['vhost']));
					if(is_array($vhostinfo) && $vhostinfo['status']==1){
						//如果是已暂停的用户，可以强制取消绑定域名
						if (!apicall('vhost', 'delInfo', array($ret['vhost'], $domain, 0, null))) {
							exit('该域名已被他人绑定，请联系管理员(del)');
						}
						$isreplace = false;
					}else{
						exit('该域名已被他人绑定，请联系管理员');
					}
				}else{
					$isreplace = true;
				}
			}
			
			//当绑定xx.www.com的时候判断*.www.com是否被别的用户绑定
			if(strpos($domain, '.')!=strrpos($domain, '.') && substr($domain, 0 ,1)!='*' && !getRole('admin')){
				$domain2 = '*' . substr($domain, strpos($domain, '.'));

				$ret = daocall('vhostinfo', 'findDomain', array($domain2));

				if ($ret) {
					if (($ret['vhost'] != $vhost)) {
						$vhostinfo = daocall('vhost', 'getVhost', array($ret['vhost']));
						if(is_array($vhostinfo) && $vhostinfo['status']==1){
							if (!apicall('vhost', 'delInfo', array($ret['vhost'], $domain2, 0, null))) {
								exit('该域名已被他人绑定，请联系管理员(del)');
							}
							$isreplace = false;
						}else{
							exit('该域名已被他人绑定，请联系管理员');
						}
					}
				}
			}
		}

		$is_vhost_domain = false;

		if ($vhost_domain = daocall('setting', 'get', array('vhost_domain'))) {
			$find_vhost_domain = strstr($domain, $vhost_domain);

			if ($find_vhost_domain) {
				$is_vhost_domain = true;

				if ($domain != $vhost . '.' . $vhost_domain) {
					exit('该域名为赠送域名,不能绑定其他的二级域名');
				}
			}
		}

		if($domain_bind == 1){ //允许泛绑定域名
			$maindomain = $this->get_main_domain($domain);
			if ($domain == '*' || $domain == '*.' || $maindomain==null || strpos($maindomain,'*')!==false) {
				if (!getRole('admin')) {
					exit('域名不合法');
				}
			}elseif (!checkDomain($domain)) {
				exit('域名不合法');
			}
		}else{
			if ($domain == '*' || strpos($domain, '*') !== false) {
				if (!getRole('admin')) {
					exit('域名不合法,泛绑定只能由管理员添加');
				}
			}elseif (!checkDomain($domain)) {
				exit('域名不合法');
			}
		}

		@load_conf('pub:reserv_domain');

		if (is_array($GLOBALS['reserv_domain'])) {
			$i = 0;

			while ($i < count($GLOBALS['reserv_domain'])) {
				if (strcasecmp($domain, $GLOBALS['reserv_domain'][$i]) == 0) {
					exit('该域名为保留域名,不允许绑定,请联系管理员');
				}

				++$i;
			}
		}

		$user = $_SESSION['user'][$vhost];
		$mianbeian = 0;
		$origin = false;
		if ($user['cdn']) {
			$oldFlag = daocall('vhostinfo', 'getInfo', array($vhost, self::MIANBEIAN_INFO_TYPE, $domain));
			$oldHas = is_array($oldFlag) && !empty($oldFlag[0]);
			$mianbeian = isset($_REQUEST['mianbeian']) ? (intval($_REQUEST['mianbeian']) ? 1 : 0) : ($isreplace && $oldHas ? 1 : 0);
			$origin = $this->parseCdnOrigin($subdir, $proto);
			if ($mianbeian && (!$origin || $proto === 'follow' || $proto === 'tcp')) {
				exit('免备案模式仅支持 HTTP/HTTPS 回源');
			}
		}
		if ($user['cdn'] && strncasecmp($subdir, 'http://', 7) != 0 && strncasecmp($subdir, 'https://', 8) != 0 && strncasecmp($subdir, 'server://', 9) != 0) {
			if(strpos($subdir,':')!==false){
				$port = substr($subdir, strpos($subdir,':')+1);
				$subdir = substr($subdir, 0, strpos($subdir,':'));
			}else{
				$port = $proto == 'https' ? '443' : '80';
			}
			if(checkIp($subdir)==false && checkDomain($subdir)==false || strpos($subdir,'.')===false || strpos($subdir,'*')!==false){
				exit('源站IP填写错误');
			}
			if(!is_numeric($port) || $port <= 0 || $port > 65535){
				exit('源站端口填写错误');
			}
			// Node format is host:port:life_time:weight. HTTP(S) origins keep
			// idle connections for 60s (kangle's KGL_HTTP_UPSTREAM_LIFE_TIME);
			// life_time 0 opened a new origin connection for every request.
			// Raw TCP forwarding cannot reuse connections, so it stays 0.
			$keep = ':60:1';
			if($proto == 'tcp'){
				$subdir = 'server://nodes=' . $subdir . ':' . $port . ':0:1/proto=tcp/error_count=1/error_try_time=30';
			}elseif($proto == 'https'){
				$subdir = 'server://proto=https/nodes=' . $subdir . ':' . $port . 's' . $keep;
			}elseif($proto == 'follow'){
				$subdir = 'server://nodes=' . $subdir . ':' . $port . $keep . '|/nodes=' . $subdir . ':443s' . $keep;
			}else{
				$subdir = 'server://nodes=' . $subdir . ':' . $port . $keep;
			}
		}

		if ($user['domain'] == 0) {
			exit('该空间不允许绑定域名');
		}

		if (!$isreplace && 0 < $user['domain']) {
			$count = daocall('vhostinfo', 'getDomainCount', array($vhost));
			if ($count && $user['domain'] <= $count) {
				exit('该空间绑定域名数限制为:' . $user['domain'] . '个');
			}
		}

		if ($user['cdn']){
			if(($proto=='tcp' || strpos($subdir, 'proto=tcp')) && !getRole('admin')){
				exit('tcp回源协议只能由管理员添加');
			}
		}
		elseif ($user['subdir_flag'] == 1) {
			$subdir = ep_safe_subdir($subdir);
			if ($subdir === false) {
				exit('绑定目录不合法');
			}
			if (!daocall('vhostinfo', 'checkDomainSubdir', array($vhost, $subdir, $user['max_subdir']))) {
				exit('最多绑定子目录限制为:' . $user['max_subdir']);
			}
		}
		else {
			$subdir = $user['subdir'];
		}

		$bindingValue = $subdir;
		if ($isreplace && strpos($subdir, 'proto=tcp') === false) {
			// Directory/upstream editing must not discard an existing domain certificate.
			$oldDomains = daocall('vhostinfo', 'getDomain', array($vhost));
			foreach (ep_iter($oldDomains) as $oldDomain) {
				if ($oldDomain['name'] === $domain) {
					$oldBinding = EpSsl::splitBinding($oldDomain['value']);
					if ($oldBinding['ssl']) {
						$bindingValue .= ';' . $oldBinding['certificate'] . '|' . $oldBinding['key'];
					}
					break;
				}
			}
		}
		if (!$isreplace) {
			$ret = apicall('vhost', 'addInfo', array($vhost, $domain, 0, $bindingValue));
		}
		else {
			$arr = array();
			$arr['value'] = $bindingValue;
			$ret = apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}

		if (!$ret) {
			exit('绑定域名失败');
		}

		if ($is_vhost_domain && !$isreplace) {
			apicall('record', 'addDnsdunRecord', array($vhost));
		}

		if (!$user['cdn']) {
			apicall('vhost', 'copyIndexForUser', array($vhost, $subdir));
		}
		else {
			$this->saveMianbeianOrigin($vhost, $domain, $mianbeian ? $origin : null);

			if (!$this->syncMianbeianRule($vhost, $domain, $mianbeian ? $origin : null)) {
				exit('免备案规则同步失败，请重试');
			}
		}

		notice_cdn_changed();
		exit('成功');
	}

	public function del()
	{
		$domain = ep_safe_name(strtolower(trim(ep_request('domain'))));

		if ($domain === '') {
			exit('删除域名失败');
		}

		if ($vhost_domain = daocall('setting', 'get', array('vhost_domain'))) {
			$find_vhost_domain = strstr($domain, $vhost_domain);

			if ($find_vhost_domain) {
				$vhost = getRole('vhost');
				$vhostinfo = daocall('vhost', 'getVhost', array($vhost));

				if (is_array($vhostinfo) && 0 < $vhostinfo['recordid']) {
					@apicall('record', 'delDnsdunRecord', array($vhostinfo['recordid']));
				}
			}
		}

		if (!apicall('vhost', 'delInfo', array(getRole('vhost'), $domain, 0, null))) {
			exit('删除域名失败');
		}

		$vhost = getRole('vhost');
		daocall('vhostinfo', 'delInfo', array($vhost, $domain, self::MIANBEIAN_INFO_TYPE, null));
		$this->syncMianbeianRule($vhost, $domain, null);
		$user = $_SESSION['user'][$vhost];
		if (is_array($user) && !empty($user['doc_root'])){
			change_to_user($user['uid'], $user['gid']);
			$file = $user['doc_root'] . '/' . $domain . '.crt';
			$keyfile = $user['doc_root'] . '/' . $domain . '.key';
			if(file_exists($file)){
				unlink($file);
			}
			if(file_exists($keyfile)){
				unlink($keyfile);
			}
			change_to_super();
		}

		notice_cdn_changed();
		exit('成功');
	}

}
