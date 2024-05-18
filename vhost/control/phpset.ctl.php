<?php
needRole('vhost');
class PhpsetControl extends Control
{
	private $ini_dir = '/vhs/kangle/phpini';
	private $php_name;

	private $php_extension_dir = [
		'5.2' => '/lib/php/extensions/no-debug-non-zts-20060613',
		'5.3' => '/lib/php/extensions/no-debug-non-zts-20090626',
		'5.4' => '/lib/php/extensions/no-debug-non-zts-20100525',
		'5.5' => '/lib/php/extensions/no-debug-non-zts-20121212',
		'5.6' => '/lib/php/extensions/no-debug-non-zts-20131226',
		'7.0' => '/lib/php/extensions/no-debug-non-zts-20151012',
		'7.1' => '/lib/php/extensions/no-debug-non-zts-20160303',
		'7.2' => '/lib/php/extensions/no-debug-non-zts-20170718',
		'7.3' => '/lib/php/extensions/no-debug-non-zts-20180731',
		'7.4' => '/lib/php/extensions/no-debug-non-zts-20190902',
		'8.0' => '/lib/php/extensions/no-debug-non-zts-20200930',
		'8.1' => '/lib/php/extensions/no-debug-non-zts-20210902',
		'8.2' => '/lib/php/extensions/no-debug-non-zts-20220829',
		'8.3' => '/lib/php/extensions/no-debug-non-zts-20230831',
	];

	private $php_extensions = [
		'opcache' => [
			'name' => 'Opcache',
			'description' => '用于缓存并加速PHP脚本，开发模式请勿开启',
			'file' => 'opcache.so',
			'data' => "zend_extension={file}\nopcache.enable=1\nopcache.memory_consumption=128\nopcache.interned_strings_buffer=8\nopcache.max_accelerated_files=4000\nopcache.revalidate_freq=60\nopcache.fast_shutdown=1",
			'sort' => 2
		],
		'ioncube' => [
			'name' => 'ionCube Loader',
			'description' => '用于解密ionCube Encoder加密的PHP脚本',
			'file' => 'ioncube_loader',
			'data' => "zend_extension={file}",
			'sort' => 1
		],
		'sourceguardian' => [
			'name' => 'SourceGuardian',
			'description' => '用于解密SourceGuardian加密的PHP脚本',
			'file' => 'ixed',
			'data' => "extension={file}",
			'sort' => 6
		],
		'swooleloader2' => [
			'name' => 'Swoole Loader 2',
			'description' => '用于解密Swoole Compiler v2加密的PHP脚本',
			'file' => 'swoole_loader22',
			'data' => "extension={file}",
			'sort' => 4
		],
		'swooleloader3' => [
			'name' => 'Swoole Loader 3',
			'description' => '用于解密Swoole Compiler v3加密的PHP脚本',
			'file' => 'swoole_loader31',
			'data' => "extension={file}",
			'sort' => 5
		],
		'zend' => [
			'name' => 'ZendGuardLoader',
			'description' => '用于解密ZendGuard加密的PHP脚本',
			'file' => 'ZendGuardLoader',
			'data' => "zend_extension={file}",
			'sort' => 3
		],
	];

	private $php_configs = [
		'display_errors' => [
			'description' => '是否输出详细错误信息',
			'type' => 'switch',
			'value' => 'Off'
		],
		'short_open_tag' => [
			'description' => '是否开启短标签',
			'type' => 'switch',
			'value' => 'On'
		],
		'max_execution_time' => [
			'description' => '脚本超时时间',
			'type' => 'number',
			'minlimit' => 10,
			'maxlimit' => 300,
			'value' => '30'
		],
		'default_socket_timeout' => [
			'description' => 'socket超时时间',
			'type' => 'number',
			'minlimit' => 10,
			'maxlimit' => 300,
			'value' => '60'
		],
		'memory_limit' => [
			'description' => 'PHP运行内存限制',
			'type' => 'number-M',
			'minlimit' => 16,
			'maxlimit' => 512,
			'value' => '128M'
		],
		'post_max_size' => [
			'description' => 'POST数据最大限制',
			'type' => 'number-M',
			'minlimit' => 10,
			'maxlimit' => 100,
			'value' => '100M'
		],
		'file_uploads' => [
			'description' => '是否允许上传文件',
			'type' => 'switch',
			'value' => 'On'
		],
		'upload_max_filesize' => [
			'description' => '上传文件最大限制',
			'type' => 'number-M',
			'minlimit' => 10,
			'maxlimit' => 100,
			'value' => '100M'
		],
		'max_file_uploads' => [
			'description' => '上传文件个数限制',
			'type' => 'number',
			'minlimit' => 1,
			'maxlimit' => 50,
			'value' => '20'
		],
	];

	public function __construct()
	{
		parent::__construct();
		if(!is_dir($this->ini_dir)) mkdir($this->ini_dir);

		$vhostinfo = apicall('vhostinfo', 'get2', array(getRole('vhost'), 'moduleversion', 101));
		$this->php_name = $vhostinfo['value'];
	}

	private function getCurrentExtensions($ini_data)
	{
		$phpver = substr($this->php_name,-2,1).'.'.substr($this->php_name,-1,1);
		$extension_dir = '/vhs/kangle/ext/'.$this->php_name.$this->php_extension_dir[$phpver];
		if(!is_dir($extension_dir)) return [];

		$file_list = [];
		$files = scandir($extension_dir);
		foreach($files as $file){
            if($file == '.' || $file == '..') continue;
			$file_list[] = $file;
		}

		$list = [];
		foreach($this->php_extensions as $name=>$row){
			$file_name = null;
			foreach($file_list as $file){
				if(strpos($file, $row['file'])!==false){
					$file_name=$file;
					break;
				}
			}
			if($file_name){
				$row['file'] = $file_name;
				$row['data'] = str_replace('{file}', $file_name, $row['data']);
				$row['value'] = strpos($ini_data, $row['file'])!==false ? 1 : 0;
				$list[$name] = $row;
			}
		}
		return $list;
	}

	private function getCurrentConfigs($ini_data)
	{
		$templete_file = '/vhs/kangle/ext/'.$this->php_name.'/php-templete.ini';
		$templete_data = file_get_contents($templete_file);

		$list = [];
		foreach($this->php_configs as $name=>$row){
			if(strpos($templete_data, $name)!==false && preg_match("/$name = (.*)/", $templete_data, $param_val)){
				$row['dvalue'] = $param_val[1];
			}
			if(strpos($ini_data, $name)!==false && preg_match("/$name = (.*)/", $ini_data, $param_val)){
				$row['value'] = $param_val[1];
			}else{
				$row['value'] = $row['dvalue'];
			}
			if($row['type'] == 'switch'){
				$row['value'] = $row['value']=='On'?1:0;
				$row['dvalue'] = $row['dvalue']=='On'?1:0;
			}elseif($row['type'] == 'number'){
				$row['value'] = intval($row['value']);
				$row['dvalue'] = intval($row['dvalue']);
			}elseif($row['type'] == 'number-M'){
				$row['value'] = intval(substr($row['value'],0,-1));
				$row['dvalue'] = intval(substr($row['dvalue'],0,-1));
			}
			$list[$name] = $row;
		}
		return $list;
	}

	private function parse_php_ini()
	{
		$vhost = getRole('vhost');
		$ini_data = '';
		$ini_file = $this->ini_dir.'/php-'.$vhost.'.ini';
		if(file_exists($ini_file)){
			$ini_data = file_get_contents($ini_file);
		}
		$extlist = $this->getCurrentExtensions($ini_data);
		$configlist = $this->getCurrentConfigs($ini_data);
		return [$extlist, $configlist];
	}

	public function phpset()
	{
		$vhost = getRole('vhost');
		$versions = modcall('php', 'php_get_version');
		$version = 'PHP-'.substr($this->php_name,-2,1).'.'.substr($this->php_name,-1,1);

		list($extlist, $configlist) = $this->parse_php_ini();

		$this->_tpl->assign('versions', $versions);
		$this->_tpl->assign('version', $version);
		$this->_tpl->assign('extlist', $extlist);
		$this->_tpl->assign('configlist', $configlist);
		return $this->_tpl->fetch('phpset.html');
	}

	public function change()
	{
		$vhost = getRole('vhost');
		$versions = modcall('php', 'php_get_version');
		$v = trim($_REQUEST['v']);
		if(empty($v) || !array_key_exists($v, $versions))exit('参数错误');

		@unlink($this->ini_dir.'/php-'.$vhost.'.ini');

		$arr['value'] = '1,cmd:' . $v . ',*';

		if (!apicall('vhost', 'updateInfo', array($vhost, '1,php', $arr, 3))) {
			exit('修改失败');
		}

		if (!apicall('vhostinfo', 'set2', array($vhost, 'moduleversion', 101, $v))) {
			exit('修改失败');
		}
		exit('修改成功');
	}

	public function edit()
	{
		$ext = $_POST['ext'];
		$config = $_POST['config'];
		if($ext && !is_array($ext) || !is_array($config)) exit('参数不能为空');
		$vhost = getRole('vhost');
		$ini_file = $this->ini_dir.'/php-'.$vhost.'.ini';

		list($extlist, $configlist) = $this->parse_php_ini();

		uksort($ext, function($a, $b) use($extlist){
			if($extlist[$a]['sort'] > $extlist[$b]['sort']) return 1;
			elseif($extlist[$a]['sort'] < $extlist[$b]['sort']) return -1;
			else return 0;
		});

		$save = false;
		$ini_data = '';
		if($ext){
			if($ext['swooleloader2']==1 && $ext['swooleloader3']==1) exit('Swoole Loader组件只能开启一个版本');
			if(($ext['swooleloader2']==1 || $ext['swooleloader3']==1) && $ext['ioncube']==1) exit('Swoole Loader与ionCube组件冲突');
			foreach($ext as $name=>$value){
				if(!array_key_exists($name, $extlist)) continue;
				$current = $extlist[$name];
				$value = intval($value);
				if($value == 1){
					$ini_data .= $current['data']."\n";
					$save = true;
				}
			}
		}
		$ini_data .= "[PHP]\n";
		foreach($config as $name=>$value){
			if(!array_key_exists($name, $configlist)) continue;
			$current = $configlist[$name];
			$value = intval($value);
			if($value != $current['dvalue']){
				if(!getRole('admin')){
					if($current['minlimit'] && $value<$current['minlimit']) exit($name.' 不能小于 '.$current['minlimit']);
					if($current['maxlimit'] && $value>$current['maxlimit']) exit($name.' 不能大于 '.$current['maxlimit']);
				}
				if($current['type'] == 'switch'){
					$ini_data .= $name.' = '.($value==1?'On':'Off')."\n";
				}elseif($current['type'] == 'number'){
					$ini_data .= $name.' = '.$value."\n";
				}elseif($current['type'] == 'number-M'){
					$ini_data .= $name.' = '.$value.'M'."\n";
				}
				$save = true;
			}
		}

		if($save){
			file_put_contents($ini_file, $ini_data);
		}else{
			@unlink($ini_file);
		}

		$result = apicall('vhost', 'rebootProcess', array($vhost));
		if ($result) {
			exit('修改成功');
		}
		exit('修改失败');
	}

	public function reset()
	{
		$vhost = getRole('vhost');
		$ini_file = $this->ini_dir.'/php-'.$vhost.'.ini';
		@unlink($ini_file);

		$result = apicall('vhost', 'rebootProcess', array($vhost));
		if ($result) {
			exit('重置成功');
		}
		exit('重置失败');
	}

}

?>