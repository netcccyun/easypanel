<?php
needRole('vhost');
class WebappControl extends Control
{
	public function index()
	{
		$list = daocall('vhostwebapp', 'getAll', array(getRole('vhost')));
		$sum = count($list);
		$this->_tpl->assign('sum', $sum);
		$this->_tpl->assign('list', $list);
		return $this->_tpl->fetch('webapp/show.html');
	}

	public function browse()
	{
		$result = apicall('webapp', 'getDomainInfo', array(getRole('vhost')));

		if (!$result) {
			exit('不能连接节点，请联系管理员');
		}

		$url = strcasecmp($_SERVER['HTTPS'], 'ON') == 0 ? 'https://' : 'http://';
		$url .= $_SERVER['HTTP_HOST'];
		$url .= $_SERVER['PHP_SELF'];
		$url .= '?c=webapp';
		$webapp_url = daocall('setting', 'get', array('webapp_url'));

		if (!$webapp_url) {
			$webapp_url = 'webapp.kanglesoft.com';
		}

		$gourl = 'http://' . $webapp_url . '/admin/?c=webapp&a=pageApp';
		$gourl .= '&url=' . urlencode($url);
		$file_exts = $result->getAll('file_ext');

		foreach ($file_exts as $file_ext) {
			$gourl .= '&file_ext[]=' . $file_ext;
		}

		header('Location: ' . $gourl);
		exit();
	}

	public function install()
	{
		$step = intval($_REQUEST['step']);
		$this->_tpl->assign('id', filterParam($_REQUEST['id']));

		if ($step == 0) {
			$this->_tpl->assign('appid', $_REQUEST['appid']);
			$this->_tpl->assign('appname', $_REQUEST['appname']);
			$this->_tpl->assign('appver', $_REQUEST['appver']);
			$this->_tpl->assign('dir', $_REQUEST['dir']);
			$result = apicall('webapp', 'getDomainInfo', array(getRole('vhost')));

			if (!$result) {
				exit('不能连接节点，请联系管理员');
			}

			$this->_tpl->assign('domain', $result->getAll('domain'));
			return $this->_tpl->fetch('webapp/step0.html');
		}

		$node_name = apicall('vhost', 'getNode', array(getRole('vhost')));
		$node = apicall('nodes', 'getInfo', array($node_name));

		if (!$node) {
			exit('得到节点信息错误，请联系管理员');
		}

		if ($step == 1) {
			$appid = $_REQUEST['appid'];
			$appname = $_REQUEST['appname'];
			$appver = $_REQUEST['appver'];
			$domain = $_REQUEST['domain'];
			$dir = $_REQUEST['dir'];
			$force = intval($_REQUEST['force']);

			if (strstr($dir, '..') != '') {
				exit('安装目录不合法');
			}

			$this->_tpl->assign('appid', $_REQUEST['appid']);
			$this->_tpl->assign('appname', $_REQUEST['appname']);
			$this->_tpl->assign('appver', $_REQUEST['appver']);
			$this->_tpl->assign('dir', $dir);
			$this->_tpl->assign('domain', $domain);

			if ($force == 0) {
				if ($node['host'] == 'localhost') {
					$node_ip = gethostbyname($_SERVER['SERVER_NAME']);
				}
				else {
					$node_ip = gethostbyname($node['host']);
				}

				$this->_tpl->assign('node_ip', $node_ip);

				if (gethostbyname($domain) != $node_ip) {
					return $this->_tpl->fetch('webapp/wrongdomain.html');
				}
			}

			$appinfo = apicall('webapp', 'getInfo', array($appid));

			if (!$appinfo) {
				exit('不能得到程序信息，请联系管理员.' . $appinfo->err_msg);
			}

			$whm = apicall('nodes', 'makeWhm', array($node_name));
			$whmcall = new WhmCall('webapp.whm', 'download');
			$whmcall->addParam('appid', $appinfo['appid']);
			$whmcall->addParam('url', $appinfo['url']);
			$whmcall->addParam('md5', $appinfo['md5']);
			$result = $whm->call($whmcall, 10);
			if (!$result || $result->getCode() != 200) {
				exit('不能下载程序，请联系管理员。');
			}

			return $this->_tpl->fetch('webapp/downinstall.html');
		}
	}

	public function uninstall()
	{
		$id = $_REQUEST['id'];
		$app = daocall('vhostwebapp', 'getapp', array($id, getRole('vhost')));

		if (!$app) {
			exit('没有该程序');
		}

		$appinfo = apicall('webapp', 'getInfo', array($app['appid']));

		if (!$appinfo) {
			exit('不能得到程序信息，请联系管理员');
		}

		$node_name = apicall('vhost', 'getNode', array(getRole('vhost')));
		$whm = apicall('nodes', 'makeWhm', array($node_name));
		$whmcall = new WhmCall('webapp.whm', 'uninstall');
		$whmcall->addParam('appid', $app['appid']);
		$whmcall->addParam('appdir', $appinfo['appdir']);
		$whmcall->addParam('vh', getRole('vhost'));
		$whmcall->addParam('phy_dir', $app['phy_dir']);
		$result = $whm->call($whmcall);
		if (!$result || $result->getCode() != 200) {
			exit('删除程序错误，请联系管理员.');
		}

		$this->_tpl->assign('app', $app);
		$this->_tpl->assign('appinfo', $appinfo);
		return $this->_tpl->fetch('webapp/uninstall.html');
	}

	public function ajaxInstall()
	{
		header('Content-Type: text/xml; charset=utf-8');
		echo '<?xml version="1.0" encoding="utf-8"?>';
		$appid = $_REQUEST['appid'];
		$appname = $_REQUEST['appname'];
		$appver = $_REQUEST['appver'];
		$domain = $_REQUEST['domain'];
		$dir = $_REQUEST['dir'];
		$phy_dir = apicall('webapp', 'getPhyDir', array(getRole('vhost'), $domain, $dir));

		if (!$phy_dir) {
			exit('<result code=\'500\' msg=\'不能得到物理路径\'/>');
		}

		$appinfo = apicall('webapp', 'getInfo', array($appid));

		if (!$appinfo) {
			exit('<result code=\'500\' msg=\'不能得到程序信息，请联系管理员\'/>');
		}

		$node_name = apicall('vhost', 'getNode', array(getRole('vhost')));
		$whm = apicall('nodes', 'makeWhm', array($node_name));
		$whmcall = new WhmCall('webapp.whm', 'install');
		$whmcall->addParam('appid', $appid);
		$whmcall->addParam('appdir', $appinfo['appdir']);
		$whmcall->addParam('vh', getRole('vhost'));
		$whmcall->addParam('phy_dir', $phy_dir);
		$id = intval($_REQUEST['id']);

		if ($id == 0) {
			$id = daocall('vhostwebapp', 'add', array(getRole('vhost'), $appid, $appname, $appver, $domain, $dir, $phy_dir));
		}

		$result = $whm->call($whmcall);
		$install = $appinfo['install'];
		$str = '<result code=\'' . $result->getCode() . '\'';

		if ($install != '') {
			$url = 'http://' . $domain;

			if ($dir[0] != '/') {
				$url .= '/';
			}

			$url .= $dir;
			$url .= $install;
			$str .= ' install=\'' . $url . '\'';
		}

		$str .= ' id=\'' . $id . '\' ';
		$str .= ' phy_dir=\'' . $phy_dir . '\' ';
		$str .= '/>';
		exit($str);
	}

	public function ajaxCheckAppinstall()
	{
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		$str .= '<result code=\'';
		$node_name = apicall('vhost', 'getNode', array(getRole('vhost')));
		$whm = apicall('nodes', 'makeWhm', array($node_name));
		$op = $_REQUEST['op'];
		$whmcall = new WhmCall('webapp.whm', $op == 'install' ? 'query_install' : 'query_uninstall');
		$whmcall->addParam('vh', getRole('vhost'));
		$whmcall->addParam('phy_dir', $_REQUEST['phy_dir']);
		$result = $whm->call($whmcall, 10);

		if (!$result) {
			$str .= '500';
		}
		else {
			$str .= $result->getCode();
		}

		$str .= '\'/>';
		exit($str);
	}

	public function ajaxCheckDownload()
	{
		header('Content-Type: text/xml; charset=utf-8');
		$str = '<?xml version="1.0" encoding="utf-8"?>';
		$str .= '<result appid=\'' . $_REQUEST['appid'] . '\' code=\'';
		$node_name = apicall('vhost', 'getNode', array(getRole('vhost')));
		$whm = apicall('nodes', 'makeWhm', array($node_name));
		$whmcall = new WhmCall('webapp.whm', 'query_download');
		$whmcall->addParam('appid', $_REQUEST['appid']);
		$result = $whm->call($whmcall, 10);

		if (!$result) {
			$str .= '500';
		}
		else {
			$str .= $result->getCode();
		}

		$str .= '\' ';

		if ($result->getCode() == 201) {
			$str .= ' total=\'' . $result->get('total');
			$str .= '\' finished=\'' . $result->get('finished');
			$str .= '\'';
		}

		$str .= '/>';
		exit($str);
	}

	public function installComplete()
	{
		daocall('vhostwebapp', 'updateApp', array($_REQUEST['id'], getRole('vhost')));
		return $this->index();
	}

	public function uninstallComplete()
	{
		daocall('vhostwebapp', 'remove', array($_REQUEST['id'], getRole('vhost')));
		return $this->index();
	}
}

?>