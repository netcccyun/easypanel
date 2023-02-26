<?php
needRole('admin');
load_lib('pub:selector');
class SelectorTestEvent extends SelectorEvent
{
	public function read(&$selector)
	{
		$str = fread($fp, 1024);

		if (strlen($str) <= 0) {
			$selector->removeRead($this);
			return NULL;
		}

		echo 'str=' . $str;
	}
}
class TestControl extends Control
{
	public function sqlsrv()
	{
		echo 'eee';
		$db = apicall('nodes', 'makeDbProduct', array('localhost', 'sqlsrv'));
		print_r($db);
	}

	public function mod()
	{
		print_r(modlist());
	}

	public function run()
	{
	}

	public function sync()
	{
		notice_cdn_changed('test');
	}

	public function daemon()
	{
		$result = apicall('process', 'daemon', array('test', null, "test\r\n"));
		print_r($result);
	}

	public function cmd()
	{
		$descriptorspec = array(
			array('pipe', 'r'),
			array('pipe', 'w'),
			array('pipe', 'w')
			);
		$rs = proc_open('D:\\project\\vhsnode\\Debug\\testcmd.exe | test .txt', $descriptorspec, $pipes, null, null, array('bypass_shell' => true));

		if (is_resource($rs)) {
			fwrite($pipes[0], 'test');
			echo stream_get_contents($pipes[1]);
			echo stream_get_contents($pipes[2]);
			proc_close($rs);
		}
	}
}

?>