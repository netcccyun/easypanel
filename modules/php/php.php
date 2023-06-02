<?php
function php_precreate($params)
{
	$default_version = daocall('setting', 'get', array('default_version'));
	if(!$default_version) $default_version = 'php56';

	if ($params['resync']==1) {

		$vhostinfo = apicall('vhostinfo', 'get2', array($params['name'], 'moduleversion', 101));
		$value = $vhostinfo['value'];
		if(!$value) $value = $default_version;
		apicall('vhost', 'addInfo', array($params['name'], '1,php', 3, '1,cmd:'.$value.',*', false));
		apicall('vhost', 'addInfo', array($params['name'], 'moduleversion', 101, $value, false));

	}else{

		if (!is_win()) {
			@unlink('/vhs/kangle/phpini/php-'.$params['name'].'.ini');
		}

		apicall('vhost', 'addInfo', array($params['name'], '1,php', 3, '1,cmd:'.$default_version.',*', false));
		apicall('vhost', 'addInfo', array($params['name'], 'moduleversion', 101, $default_version, false));

		if ($params['default_index']) {
			$default_indexs = explode(',', $params['default_index']);
			$indexs = array();
			$i = 100;

			foreach ($default_indexs as $index) {
				$indexs[] = array($index, 2, $i++, false);
			}
		}
		else {
			$indexs = array(
				array('index.htm', 2, '100', false),
				array('index.html', 2, '101', false),
				array('index.php', 2, '102', false)
				);
		}

		apicall('vhost', 'addInfos', array($params['name'], $indexs));
	}
}

function php_postcreate($params)
{
}

function php_get_version()
{
	$extdir = $GLOBALS['safe_dir'] . '../ext/';
	$opdir = opendir($extdir);

	if (!$opdir) {
		return false;
	}

	while (($file = readdir($opdir)) !== false) {
		if ($file == '.' || $file == '..') {
			continue;
		}

		if (!is_dir($extdir . $file) || !strstr($file, 'php')) {
			continue;
		}

		if (substr($file, 0, 4) == 'tpl_') {
			$file = substr($file, 4);
			if (is_win() && $file == 'php5217') {
				$versions['php52'] = 'PHP-5.2';
			}
			else {
				$versions[$file] = 'PHP-'.substr($file,-2,1).'.'.substr($file,-1,1);
			}
		}
		else {
			$versions[$file] = 'PHP-'.substr($file,-2,1).'.'.substr($file,-1,1);
		}
	}

	ksort($versions);

	return $versions;
}

function php_destroy($params)
{
}

function php_link($params)
{
	$versions = php_get_version();

	if (1 < count($versions)) {
		$vhostinfo = apicall('vhostinfo', 'get2', array(getRole('vhost'), 'moduleversion', 101));
		$value = $vhostinfo['value'];
		$str = '<form action=\'?c=index&a=module&op=php_version\' method=\'POST\'>切换php版本:<select name=v>';

		foreach ($versions as $k => $v) {
			$str .= '<option value=\'' . $k . '\' ';

			if ($value == $k) {
				$str .= 'selected';
			}

			$str .= '>' . $v . '</option>';
		}

		$str .= '</select><input value=\'确定\' type=\'submit\'></form>';
	}

	return $str;
}

function php_update($params)
{
}

function php_cron($params)
{
}

function php_call($params)
{
	if ($_REQUEST['op'] == 'php_version') {
		$v = trim($_REQUEST['v']);
		if(empty($v))return false;
		$vhost = getRole('vhost');
		$ver = php_get_version();
		if(!array_key_exists($v, $ver)) return;

		if (!is_win()) {
			@unlink('/vhs/kangle/phpini/php-'.$vhost.'.ini');
		}

		$arr['value'] = '1,cmd:' . $v . ',*';

		if (!apicall('vhost', 'updateInfo', array($vhost, '1,php', $arr, 3))) {
		}

		if (!apicall('vhostinfo', 'set2', array($vhost, 'moduleversion', 101, $v))) {
		}
	}
}

function php_get_cli_version()
{
	if(file_exists('/usr/bin/php')){
		$oripath = shell_exec('ls -al /usr/bin/php | awk \'{print $NF}\'');
		if($oripath){
			if(preg_match('!/ext/php(\d+)/bin/php!', $oripath, $match)){
				return 'php'.$match[1];
			}
		}
	}
	return null;
}

function php_set_cli_version($version)
{
	if(empty($version)){
		shell_exec('rm -f /usr/bin/php');
	}else{
		shell_exec('ln -sf /vhs/kangle/ext/'.$version.'/bin/php /usr/bin/php');
	}
}