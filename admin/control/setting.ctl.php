<?php
needRole('admin');
class SettingControl extends Control
{
	public function setFrom()
	{
		return $this->fetch('setting/setFrom.html');
	}

	public function index()
	{
		@load_conf('pub:settingrule');
		@load_conf('pub:setting');
		$sub = $_REQUEST['sub'];
		$info = $GLOBALS['settingrule'][$sub];
		$this->assign('env', $info);
		$this->assign('val', $GLOBALS['setting_cfg']);
		$this->assign('sub', $sub);
		return $this->fetch('setting/show.html');
	}

	public function add()
	{
		daocall('setting', 'add', array($_REQUEST['name'], $_REQUEST['value']));
	}

	public function set()
	{
		@load_conf('pub:settingrule');
		$names = $_REQUEST['name'];
		$sub = $_REQUEST['sub'];

		foreach ($names as $name) {
			if ($GLOBALS['settingrule'][$sub][$name]['password']) {
				if ($_REQUEST[$name] == '') {
					continue;
				}
			}

			$ret = apicall('tplenv', 'checkEnv', array($name, $_REQUEST[$name], $GLOBALS['settingrule'][$sub]));

			if ($ret != ENV_CHECK_SUCCESS) {
				$this->_tpl->assign('msg', '设置:' . $GLOBALS['lang']['zh_CN'][$name] . ' 失败');
				$list = daocall('setting', 'getAll');
				apicall('utils', 'writeConfig', array($list, 'name', 'setting'));
				return $this->index();
			}

			daocall('setting', 'add', array($name, $_REQUEST[$name]));
		}

		$list = daocall('setting', 'getAll');
		apicall('utils', 'writeConfig', array($list, 'name', 'setting'));
		return $this->index();
	}
}

?>