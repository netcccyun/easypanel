<?php
class CronDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array('id' => 'id', 'vhost' => 'vhost', 'min' => 'min', 'hour' => 'hour', 'day' => 'day', 'month' => 'month', 'mday' => 'mday', 'cmd_type' => 'cmd_type', 'cmd' => 'cmd', 'stdin_file' => 'stdin_file', 'stdout_file' => 'stdout_file', 'stderr_as_out' => 'stderr_as_out');
		$this->MAP_TYPE = array('id' => FIELD_TYPE_AUTO, 'cmd_type' => FIELD_TYPE_INT, 'stderr_as_out' => FIELD_TYPE_INT);
		$this->_TABLE = 'cron';
	}

	public function get($vhost)
	{
		$where = $this->getFieldValue2('vhost', $vhost);
		return $this->select(null, $where, 'rows');
	}

	public function getCount($vhost)
	{
		$sql = 'select count(*) as count FROM ' . $this->_TABLE . ' WHERE ' . $this->getFieldValue2('vhost', $vhost);
		$result = $this->executex($sql, 'row');
		return $result['count'];
	}

	public function add($vhost, $params, $max_cron)
	{
		if (0 < $max_cron) {
			$count = $this->getCount($vhost);

			if ($max_cron <= $count) {
				trigger_error('max cron limit');
				return false;
			}
		}

		$params['vhost'] = $vhost;
		return $this->insert($params);
	}

	public function del($vhost, $id)
	{
		return $this->delData($this->getFieldValue2('id', $id) . ' AND ' . $this->getFieldValue2('vhost', $vhost));
	}

	public function delAll($vhost)
	{
		return $this->delData($this->getFieldValue2('vhost', $vhost));
	}
}

?>