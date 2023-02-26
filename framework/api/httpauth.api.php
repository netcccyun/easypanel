<?php
class HttpauthAPI extends API
{
	public function add($vhost, $user, $passwd)
	{
		if (daocall('httpauth', 'add', array($vhost, $user, $passwd))) {
			return $this->sync($vhost);
		}

		return false;
	}

	public function del($vhost, $user)
	{
		if (daocall('httpauth', 'del', array($vhost, $user))) {
			return $this->sync($vhost);
		}

		return false;
	}

	public function changePassword($vhost, $user, $passwd)
	{
		if (daocall('httpauth', 'changePassword', array($vhost, $user, $passwd))) {
			return $this->sync($vhost);
		}

		return false;
	}

	public function delAll($vhost)
	{
		daocall('httpauth', 'delAll', array($vhost));
		@unlink($this->getFileName($vhost));
		return true;
	}

	public function sync($vhost)
	{
		$users = daocall('httpauth', 'getAll', array($vhost));

		$str = '';

		foreach ($users as $user) {
			$str .= $user['user'] . ':' . $user['passwd'] . "\r\n";
		}

		$filename = $this->getFileName($vhost);
		$fp = @fopen($filename, 'wb');

		if ($fp === false) {
			mkdir($GLOBALS['safe_dir'] . 'httpauth');
			$fp = @fopen($filename, 'wb');
		}

		if ($fp === false) {
			return false;
		}

		fwrite($fp, $str);
		fclose($fp);
		return true;
	}

	public function getFileName($vhost)
	{
		return $GLOBALS['safe_dir'] . 'httpauth/' . $vhost . '.txt';
	}
}

?>