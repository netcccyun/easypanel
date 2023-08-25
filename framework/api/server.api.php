<?php
class ServerAPI extends API
{
	public function serverDel($server)
	{
		if (daocall('servers', 'serverDel', array($server))) {
			$arr['server'] = $server;
			$slave = daocall('slaves', 'slavesGet', array($arr));

			if (0 < count($slave)) {
				return daocall('slaves', 'slaveDel', array($server));
			}

			return apicall('bind', 'bindInit', array());
		}

		return false;
	}

	public function serverUpdate($oldserver, $arr)
	{
		if (daocall('servers', 'serverUpdate', array($oldserver, $arr))) {
			return apicall('bind', 'bindInit', array());
		}

		return false;
	}

	public function getEntKey()
	{
		return 'Bka2ZwYXNkamY7YXNkZjtsYXNkbDtmamFzZDtsZmFzZGZwJ2FzZGp';
	}
}

?>