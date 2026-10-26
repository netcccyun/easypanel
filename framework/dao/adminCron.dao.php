<?php
class AdminCronDAO extends DAO
{
	public function __construct()
	{
		parent::__construct();
		$this->MAP_ARR = array(
			'id' => 'id',
			'name' => 'name',
			'type' => 'type',
			'cmd' => 'cmd',
			'cron' => 'cron',
			'flock' => 'flock',
			'enabled' => 'enabled',
			'last_run' => 'last_run',
			'last_status' => 'last_status'
		);
		$this->MAP_TYPE = array(
			'id' => FIELD_TYPE_AUTO,
			'flock' => FIELD_TYPE_INT,
			'enabled' => FIELD_TYPE_INT,
			'last_run' => FIELD_TYPE_INT,
			'last_status' => FIELD_TYPE_INT
		);
		$this->_TABLE = 'admin_cron';
	}

	public function ensureTable()
	{
		$sql = 'CREATE TABLE IF NOT EXISTS admin_cron ('
			. 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
			. 'name TEXT,'
			. 'type TEXT,'
			. 'cmd TEXT,'
			. 'cron TEXT,'
			. 'flock INTEGER NOT NULL DEFAULT 0,'
			. 'enabled INTEGER NOT NULL DEFAULT 1,'
			. 'last_run INTEGER NOT NULL DEFAULT 0,'
			. 'last_status INTEGER NOT NULL DEFAULT 0)';
		if ($this->executex($sql) === false) {
			return false;
		}
		$cols = $this->executex('PRAGMA table_info(admin_cron)', 'rows');
		if (is_array($cols)) {
			foreach ($cols as $col) {
				if (isset($col['name']) && $col['name'] === 'flock') {
					return true;
				}
			}
		}
		return $this->executex('ALTER TABLE admin_cron ADD COLUMN flock INTEGER NOT NULL DEFAULT 0');
	}

	public function getList()
	{
		return $this->select(null, '', 'rows', 'id');
	}

	public function get($id)
	{
		return $this->select(null, $this->getFieldValue2('id', intval($id)), 'row');
	}

	public function add($data)
	{
		return $this->insert($data);
	}

	public function updateById($id, $data)
	{
		return $this->update($data, $this->getFieldValue2('id', intval($id)));
	}

	public function delById($id)
	{
		return $this->delData($this->getFieldValue2('id', intval($id)));
	}
}
