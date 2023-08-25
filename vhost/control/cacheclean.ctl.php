<?php
needRole('vhost');
class CachecleanControl extends Control
{
	public function cachecleanFrom()
	{
		$list = daocall('vhostinfo', 'getDomain', array(getRole('vhost')));
		foreach ($list as $domain) {
			if(strpos($domain['name'],'.') && strpos($domain['name'],'*')===false)
				$li[] = $domain['name'];
		}
		$this->_tpl->assign('list', $li);
		return $this->_tpl->fetch('cacheclean/cachecleanfrom.html');
	}

	public function cacheclean()
	{
		$url = trim($_REQUEST['url']);
		$url = rtrim($url, ',');

		if ($url == '') {
			exit('url不能为空');
		}

		$result = apicall('vhost', 'cleanCache', array($url, getRole('vhost'), true));

		if (!$result) {
			exit('清除失败');
		}

		$count = $result->get('count');
		if (!$count || $count < 0) {
			$count = 0;
		}

		exit('成功:清除缓存数 ' . $count);
	}
}

?>