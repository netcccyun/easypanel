<?php
needRole('vhost');
define(SSI_MAP, '1,ssi,*');
class SsiControl extends Control
{
	public function add()
	{
		$name = intval($_REQUEST['file_ext']) . ',' . filterParam($_REQUEST['value']);
		apicall('vhost', 'addInfo', array(getRole('vhost'), $name, 3, SSI_MAP, true, filterParam($_REQUEST['id'])));
		return $this->show();
	}

	public function del()
	{
		apicall('vhost', 'delInfo', array(getRole('vhost'), filterParam($_REQUEST['name']), 3));
		return $this->show();
	}

	public function show()
	{
		$maps = daocall('vhostinfo', 'getMap', array(getRole('vhost'), SSI_MAP));
		$path_map = array();
		$file_map = array();

		foreach ($maps as $map) {
			$name = substr($map['name'], 2);

			if (substr($map['name'], 0, 1) == '1') {
				$file_map[] = array('id' => $map['id'], 'name' => addslashes($name), 'rawname' => $name);
			}
			else {
				$path_map[] = array('id' => $map['id'], 'name' => addslashes($name), 'rawname' => $name);
			}
		}

		$this->_tpl->assign('file_map', $file_map);
		$this->_tpl->assign('path_map', $path_map);
		return $this->_tpl->fetch('ssi.html');
	}
}

?>