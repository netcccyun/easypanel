<?php
function sys_linux()
{
	if (false === ($str = @file('/proc/uptime'))) {
		return false;
	}

	$str = explode(' ', implode('', $str));
	$str = trim($str[0]);
	$min = $str / 60;
	$hours = $min / 60;
	$days = floor($hours / 24);
	$hours = floor($hours - $days * 24);
	$min = floor($min - $days * 60 * 24 - $hours * 60);

	if ($days !== 0) {
		$res['uptime'] = $days . '天';
	}

	if ($hours !== 0) {
		$res['uptime'] .= $hours . '小时';
	}

	$res['uptime'] .= $min . '分钟';

	if (false === ($str = @file('/proc/meminfo'))) {
		return false;
	}

	$str = implode('', $str);
	preg_match_all('/MemTotal\\s{0,}\\:+\\s{0,}([\\d\\.]+).+?MemFree\\s{0,}\\:+\\s{0,}([\\d\\.]+).+?Cached\\s{0,}\\:+\\s{0,}([\\d\\.]+).+?SwapTotal\\s{0,}\\:+\\s{0,}([\\d\\.]+).+?SwapFree\\s{0,}\\:+\\s{0,}([\\d\\.]+)/s', $str, $buf);
	preg_match_all('/Buffers\\s{0,}\\:+\\s{0,}([\\d\\.]+)/s', $str, $buffers);
	$res['memTotal'] = round($buf[1][0] / 1024, 2);
	$res['memFree'] = round($buf[2][0] / 1024, 2);
	$res['memBuffers'] = round($buffers[1][0] / 1024, 2);
	$res['memCached'] = round($buf[3][0] / 1024, 2);
	$res['memUsed'] = $res['memTotal'] - $res['memFree'];
	$res['memPercent'] = floatval($res['memTotal']) != 0 ? round($res['memUsed'] / $res['memTotal'] * 100, 2) : 0;
	$res['memRealUsed'] = $res['memTotal'] - $res['memFree'] - $res['memCached'] - $res['memBuffers'];
	$res['memRealFree'] = $res['memTotal'] - $res['memRealUsed'];
	$res['memRealPercent'] = floatval($res['memTotal']) != 0 ? round($res['memRealUsed'] / $res['memTotal'] * 100, 2) : 0;
	$res['memCachedPercent'] = floatval($res['memCached']) != 0 ? round($res['memCached'] / $res['memTotal'] * 100, 2) : 0;
	$res['swapTotal'] = round($buf[4][0] / 1024, 2);
	$res['swapFree'] = round($buf[5][0] / 1024, 2);
	$res['swapUsed'] = round($res['swapTotal'] - $res['swapFree'], 2);
	$res['swapPercent'] = floatval($res['swapTotal']) != 0 ? round($res['swapUsed'] / $res['swapTotal'] * 100, 2) : 0;

	if (false === ($str = @file('/proc/loadavg'))) {
		return false;
	}

	$str = explode(' ', implode('', $str));
	$str = array_chunk($str, 4);
	$res['loadAvg'] = implode(' ', $str[0]);
	return $res;
}

function sys_windows()
{
	if (5 <= PHP_VERSION) {
		$objLocator = new COM('WbemScripting.SWbemLocator');
		$wmi = $objLocator->ConnectServer();
		$prop = $wmi->get('Win32_PnPEntity');
	}
	else {
		return false;
	}

	$sysinfo = GetWMI($wmi, 'Win32_OperatingSystem', array('LastBootUpTime', 'TotalVisibleMemorySize', 'FreePhysicalMemory'));
	$res['uptime'] = $sysinfo[0]['LastBootUpTime'];
	$sys_ticks = 3600 * 8 + time() - strtotime(substr($res['uptime'], 0, 14));
	$min = $sys_ticks / 60;
	$hours = $min / 60;
	$days = floor($hours / 24);
	$hours = floor($hours - $days * 24);
	$min = floor($min - $days * 60 * 24 - $hours * 60);

	if ($days !== 0) {
		$res['uptime'] = $days . '天';
	}

	if ($hours !== 0) {
		$res['uptime'] .= $hours . '小时';
	}

	$res['uptime'] .= $min . '分钟';
	$res['memTotal'] = round($sysinfo[0]['TotalVisibleMemorySize'] / 1024, 2);
	$res['memFree'] = round($sysinfo[0]['FreePhysicalMemory'] / 1024, 2);
	$res['memUsed'] = $res['memTotal'] - $res['memFree'];
	$res['memPercent'] = round($res['memUsed'] / $res['memTotal'] * 100, 2);
	$loadinfo = GetWMI($wmi, 'Win32_Processor', array('LoadPercentage'));
	$res['loadAvg'] = $loadinfo[0]['LoadPercentage'] . '%';
	return $res;
}

function GetWMI($wmi, $strClass, $strValue = array())
{
	$arrData = array();
	$objWEBM = $wmi->Get($strClass);
	$arrProp = $objWEBM->Properties_;
	$arrWEBMCol = $objWEBM->Instances_();

	foreach ($arrWEBMCol as $objItem) {
		@reset($arrProp);
		$arrInstance = array();

		foreach ($arrProp as $propItem) {
			eval ('$value = $objItem->' . $propItem->Name . ';');

			if (empty($strValue)) {
				$arrInstance[$propItem->Name] = trim($value);
			}
			else {
				if (in_array($propItem->Name, $strValue)) {
					$arrInstance[$propItem->Name] = trim($value);
				}
			}
		}

		$arrData[] = $arrInstance;
	}

	return $arrData;
}

function sys_info()
{
	switch (PHP_OS) {
	case 'Linux':
		return sys_linux();
	case 'WINNT':
		return sys_windows();
	default:
		break;
	}

	return $sysInfo;
}


?>