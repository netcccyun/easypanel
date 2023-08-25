<?php
function Usage()
{
	exit('Usage: ' . $_SERVER['argv'][0] . " <sync>\n");
}

@set_time_limit(0);
date_default_timezone_set('Asia/Shanghai');
$dir = dirname(__FILE__);
define('SYS_ROOT', $dir);
define('APPLICATON_ROOT', '');
include SYS_ROOT . '/runtime.php';
if ($_SERVER['argv'] == null || $_REQUEST != null) {
	exit('crontab cann\'t run in web model.please run in cli.');
}

if ($_SERVER['argc'] < 2) {
	usage();
}

$argv = $_SERVER['argv'];
$program = array_shift($argv);
$action = array_shift($argv);
@apicall('shell', $action, array($argv));

?>