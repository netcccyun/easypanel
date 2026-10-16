<?php
needRole('vhost');
class DomainControl extends Control
{
	public function __construct()
	{
		parent::__construct();
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function show()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$cname_host = daocall('setting', 'get', array('cname_host'));

		if ($cname_host) {
			$domain_note = 'CNAME解析到：' . str_replace('*',$user['name'],$cname_host);
		}
		else {
			$domain_note = 'A记录到IP：' . gethostbyname($_SERVER['SERVER_NAME']);
		}

		$this->_tpl->assign('domain_note', $domain_note);
		$list = daocall('vhostinfo', 'getDomain', array($vhost));

		if ($user['cdn']) {
			$domains = array();
			$id = 0;

			foreach (ep_iter($list) as $domain) {
				$isSsl = 0;
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
					if(strpos($domain['value'],';') !== false && strpos($domain['value'],'.crt') !== false && strpos($domain['value'],'.key') !== false){
						$isSsl = 1;
					}
				}else{
					$dir = substr($domain['value'], 7, strlen($domain['value']));
					$dir = trim($dir, '/');
				}
				$domain['value'] = $dir;
				$domain['id'] = $id;
				$domain['ssl'] = $isSsl;
				$domain['proto'] = $proto;
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
				$p = strpos($domain['value'], ';');
				if ($p !== false && strpos($domain['value'], '.crt') !== false && strpos($domain['value'], '.key') !== false) {
					$domain['ssl'] = 1;
					$domain['value'] = substr($domain['value'], 0, $p);
				} else {
					$domain['ssl'] = 0;
				}
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

		//每个域名的证书剩余有效天数（无证书为空）；普通虚拟主机目录可点击进入webftp
		foreach ($list as $i => $row) {
			$days = apicall('acme', 'getCertDays', array($user['doc_root'], $row['name']));
			$list[$i]['cert_days'] = ($days === null) ? '' : $days;
			if (!$user['cdn']) {
				$d = trim($row['value'], '/');
				$list[$i]['dir_url'] = ($d === '') ? '' : '?c=webftp&a=cd&file=/' . rawurlencode($d);
			} else {
				$list[$i]['dir_url'] = '';
			}
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

	public function info()
	{
		$vhost = getRole('vhost');
		$user = $_SESSION['user'][$vhost];
		$domain = strtolower(trim(ep_request('domain')));
		$ret = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
		if (is_array($ret) && !empty($ret[0])) {
			$result = array();
			$result['code'] = 0;
			$value = $ret[0]['value'];
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
			}else{
				$result['cdn'] = 0;
				$p = strpos($value, ';');
				if ($p !== false && strpos($value, '.crt') !== false && strpos($value, '.key') !== false) {
					$result['subdir'] = substr($value, 0, $p);
				} else {
					$result['subdir'] = $value;
				}
			}
			exit(json_encode($result));
		}else{
			exit(json_encode(array('code'=>-1, 'msg'=>'该域名不存在')));
		}
	}

	public function add()
	{
		$domain = strtolower(trim(ep_request('domain')));
		$subdir = trim(ep_request('subdir'));
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
			if($proto == 'tcp'){
				$subdir = 'server://nodes=' . $subdir . ':' . $port . ':0:1/proto=tcp/error_count=1/error_try_time=30';
			}elseif($proto == 'https'){
				$subdir = 'server://proto=https/nodes=' . $subdir . ':' . $port . 's:0:1';
			}elseif($proto == 'follow'){
				$subdir = 'server://nodes=' . $subdir . ':' . $port . ':0:1|/nodes=' . $subdir . ':443s:0:1';
			}else{
				$subdir = 'server://nodes=' . $subdir . ':' . $port . ':0:1';
			}
			if($isreplace){
				$ret = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
				if(is_array($ret) && !empty($ret[0])){
					if (strncasecmp($ret[0]['value'], 'server://', 9) == 0 && strpos($ret[0]['value'], ';' . $domain . '.crt|' . $domain . '.key')!==false){
						$subdir .= ';' . $domain . '.crt|' . $domain . '.key';
					}
				}
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
			if (strpos($subdir, ';') !== false || strpos($subdir, '|') !== false) {
				exit('目录名不能包含 ; 或 | 字符');
			}
			if (!daocall('vhostinfo', 'checkDomainSubdir', array($vhost, $subdir, $user['max_subdir']))) {
				exit('最多绑定子目录限制为:' . $user['max_subdir']);
			}
		}
		else {
			$subdir = $user['subdir'];
		}

		//普通虚拟主机修改域名目录时，保留已设置的单域名SSL证书
		if ($isreplace && !$user['cdn']) {
			$old = daocall('vhostinfo', 'getInfo', array($vhost, 0, $domain));
			if (is_array($old) && !empty($old[0])) {
				$p = strpos($old[0]['value'], ';');
				if ($p !== false && strpos($old[0]['value'], '.crt') !== false && strpos($old[0]['value'], '.key') !== false) {
					$subdir .= substr($old[0]['value'], $p);
				}
			}
		}

		if (!$isreplace) {
			$ret = apicall('vhost', 'addInfo', array($vhost, $domain, 0, $subdir));
		}
		else {
			$arr = array();
			$arr['value'] = $subdir;
			$ret = apicall('vhost', 'updateInfo', array($vhost, $domain, $arr));
		}

		if (!$ret) {
			exit('绑定域名失败');
		}

		if ($is_vhost_domain && !$isreplace) {
			apicall('record', 'addDnsdunRecord', array($vhost));
		}

		if (!$user['cdn']) {
			$dir = $subdir;
			$p = strpos($dir, ';');
			if ($p !== false) {
				$dir = substr($dir, 0, $p);
			}
			apicall('vhost', 'copyIndexForUser', array($vhost, $dir));
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
		$user = $_SESSION['user'][$vhost];
		if (is_array($user) && !empty($user['doc_root'])){
			// 清理 cert/ 子目录与旧版散放根目录两处的证书文件
			foreach (array('cert/' . $domain, $domain) as $base) {
				$file = $user['doc_root'] . '/' . $base . '.crt';
				$keyfile = $user['doc_root'] . '/' . $base . '.key';
				if(file_exists($file)){
					unlink($file);
				}
				if(file_exists($keyfile)){
					unlink($keyfile);
				}
			}
		}

		notice_cdn_changed();
		exit('成功');
	}

}
