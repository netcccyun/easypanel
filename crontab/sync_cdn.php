<?php
if ($_SERVER['argv'] == null || $_REQUEST != null) {
	exit('crontab cann\'t run in web model.please run in cli.');
}

date_default_timezone_set('Asia/Shanghai');
define('APPLICATON_ROOT', dirname(__FILE__));
define('SYS_ROOT', dirname(dirname(__FILE__)) . '/framework');
define('DEFAULT_CONTROL', 'index');
include SYS_ROOT . '/runtime.php';

if ($_SERVER['argc'] < 2) {
	exit('Usage: sync_cdn.php host [filename]');
}

$filename = 'cdn.xml';

if (2 < $_SERVER['argc']) {
	$filename = $_SERVER['argv'][2];
}

$i = 1;

while ($i < $_SERVER['argc']) {
	apicall('cdn', 'sync', array($_SERVER['argv'][$i]));
	++$i;
}

apicall('vhost', 'noticeChange', array('localhost', false));

?>