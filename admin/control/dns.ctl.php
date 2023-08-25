<?php
needRole('admin');
class DnsControl extends Control
{
	public function domainAdd()
	{
		$records = daocall('records', 'recordList', array());

		foreach ($records as $re) {
			apicall('bind', 'domainAdd', array($re));
		}
	}
}

?>