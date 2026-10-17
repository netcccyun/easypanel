<?php
class MigrateControl extends control
{
	public function list_vhost()
	{
		$vhs = daocall('vhost', 'listVhost', array());

		if (!is_array($vhs) || count($vhs) <= 0) {
			exit();
		}

		$vhs_name = '';

		foreach ($vhs as $vh) {
			$vhs_name .= $vh['name'] . ';';
		}

		exit($vhs_name);
	}

	public function migrate_domain()
	{
		$vh = ep_safe_name(trim(ep_request('vh')));
		if ($vh === '') {
			exit();
		}

		$domain = daocall('vhostinfo', 'getDomain', array($vh));

		if (!is_array($domain) || count($domain) <= 0) {
			exit();
		}

		$domain_str = '';
		load_lib('pub:ssl');

		foreach ($domain as $d) {
			// This legacy directory-only format uses ';' between records.
			// Full SSL bindings are carried by the structured WHM migration API.
			$binding = EpSsl::splitBinding($d['value']);
			$domain_str .= $d['name'] . '=>' . $binding['dir'] . ';';
		}

		exit($domain_str);
	}

	public function migrate_hello_vh_web()
	{
		$vh = $_REQUEST['vh'];
		$nolog = intval($_REQUEST['nolog']);
		$save_dir = $GLOBALS['safe_dir'] . '../nodewww/webftp/';

		if ($session = apicall('migrate', 'zipVhWeb', array($vh, $save_dir, $nolog))) {
			exit($session);
		}

		exit();
	}

	public function migrate_hello_vh_sql()
	{
		$vh = $_REQUEST['vh'];
		$save_dir = $GLOBALS['safe_dir'] . '../nodewww/webftp/';

		if ($session = apicall('migrate', 'zipVhSql', array($vh, $save_dir))) {
			exit($session);
		}

		exit();
	}
}
