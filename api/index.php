<?php
function verificationSkey()
{
	if ($_REQUEST['r'] == '' || $_REQUEST['a'] == '' || $_REQUEST['s'] == '') {
		return false;
	}

	$skey = daocall('setting', 'get', array('skey'));

	if (!$skey) {
		return false;
	}

	$urls = $_REQUEST['a'] . $skey . $_REQUEST['r'];

	if (md5($urls) == $_REQUEST['s']) {
		return true;
	}

	return false;
}

function whm_dump($ret)
{
	if (is_array($ret)) {
		$str = '';
		foreach ($ret as $k => $v) {
			$str .= '<' . $k . '>' . whm_dump($v) . '</' . $k . ">\n";
		}

		return $str;
	}

	return $ret;
}

function whm_return($status, $ret = null)
{
	if ($_REQUEST['json'] == 1 || $_REQUEST['fmt'] == 'json') {
		$json['result'] = $status;

		if ($ret) {
			if (is_array($ret)) {
				foreach ($ret as $k => $v) {
					$json[$k] = $v;
				}
			}
			else {
				$json['msg'] = $ret;
			}
		}

		exit(json_encode($json));
	}

	header('Content-Type: text/xml; charset=utf-8');
	$str = '<?xml version="1.0" encoding="utf-8"?>';
	$whm_call = $_REQUEST['a'];
	$str .= '<' . $whm_call . ' whm_version="1.0">';
	$str .= '<result status=\'' . $status . '\'>';

	if (is_array($ret)) {
		foreach ($ret as $k => $v) {
			if (is_array($v)) {
				foreach ($v as $sv) {
					$str .= '<' . $k . '>' . whm_dump($sv) . '</' . $k . ">\n";
				}
			}
			else {
				$str .= '<' . $k . '>' . $v . '</' . $k . ">\n";
			}
		}
	}

	$str .= '</result>';
	$str .= '</' . $whm_call . '>';
	exit($str);
}

date_default_timezone_set('Asia/Shanghai');
define('APPLICATON_ROOT', dirname(__FILE__));
define('SYS_ROOT', dirname(dirname(__FILE__)) . '/framework');
define('DEFAULT_CONTROL', 'index');
include SYS_ROOT . '/runtime.php';

if (!verificationskey()) {
	whm_return(403, '权限错误,请检查通信安全码是否正确');
	exit();
}

$tpl = TPL::singleton();
$tpl->assign('title', getTitle());
startFramework();

?>