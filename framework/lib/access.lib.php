<?php
class Access
{
	const REAL_IP_CHAIN = '!real_ip';

	/** The HTTP method names recognized by kangle's KHttpKeyValue table. */
	public static function requestMethods()
	{
		return array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');
	}
	private $vh;
	private $access;
	private $whm;

	public function __construct($vh = null, $access = 'request')
	{
		$this->vh = $vh;
		$this->access = $access;
		$this->whm = apicall('nodes', 'makeWhm', array('localhost'));
	}

	public function listTable()
	{
		$result = $this->call('list_table', null);
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		$tables = $result->getAll('table');
		if (!is_array($tables)) {
			return array();
		}
		$ret = array();
		foreach ($tables as $table) {
			// kangle 3.6 returns structured table data for the new console,
			// while the long-standing WHM protocol returned a plain name.
			$ret[] = isset($table->name) ? (string) $table->name : (string) $table;
		}
		return $ret;
	}

	public function addTable($table)
	{
		$result = $this->call('add_table', array('table_name' => $table));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function emptyTable($table)
	{
		$result = $this->call('empty_table', array('table_name' => $table));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function delTable($table)
	{
		$result = $this->call('del_table', array('table_name' => $table));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function findChain($table, $name)
	{
		$result = $this->call('list_chain', array('table_name' => $table, 'name' => $name, 'detail' => 1));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return $this->normalizeLegacyChainValues($result->get('table_info')->children());
	}

	/**
	 * $detail为1时得到的是详细的信息，否则为概要
	 * Enter description here ...
	 * @param  $table
	 * @param  $detail
	 */
	public function listChain($table, $detail = 1)
	{
		$result = $this->call('list_chain', array('table_name' => $table, 'detail' => $detail));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return $this->normalizeLegacyChainValues($result->get('table_info')->children());
	}

	/**
	 * kangle 3.6 serializes model parameters as attributes. Older WHM output
	 * also exposed each model's primary value as its element text, which the
	 * existing EasyPanel controllers use for list-page display. Restore that
	 * representation while retaining every attribute for newer callers.
	 */
	private function normalizeLegacyChainValues($table)
	{
		if (!$table) {
			return $table;
		}

		$primary_attributes = array(
			'acl_file_ext' => 'v',
			'acl_header' => 'val',
			'acl_host' => 'v',
			'acl_meth' => 'meth',
			'acl_path' => 'path',
			'acl_referer' => 'referer',
			'acl_reg_path' => 'path',
			'acl_self_ports' => 'v',
			'acl_src' => 'ip',
			'acl_srcs' => 'v',
			'acl_url' => 'url',
			'acl_wide_host' => 'v',
			'mark_content' => 'content',
			'mark_gspeed_limit' => 'limit',
			'mark_host' => 'host',
			'mark_host_rewrite' => 'host',
			'mark_ip_speed_limit' => 'speed_limit',
			'mark_param' => 'value',
			'mark_post_file' => 'filename',
			'mark_redirect' => 'dst',
			'mark_rewrite' => 'dst',
			'mark_speed_limit' => 'limit',
			'mark_url_rewrite' => 'dst',
		);

		foreach ($table->children() as $chain) {
			foreach ($chain->children() as $model) {
				if ((string) $model !== '') {
					continue;
				}
				$name = $model->getName();
				if (!isset($primary_attributes[$name])) {
					continue;
				}
				$value = (string) $model[$primary_attributes[$name]];
				if ($value !== '') {
					$model[0] = $value;
				}
			}
		}
		return $table;
	}

	public function editChain($table, $arr, $models = null)
	{
		return $this->replaceChain('edit_chain', $table, $arr, $models);
	}

	public function addChain($table, $arr, $models = null)
	{
		return $this->replaceChain('add_chain', $table, $arr, $models);
	}

	private function replaceChain($callName, $table, $arr, $models = null)
	{
		if ($table === 'BEGIN' && $this->access === 'request') {
			$chains = $this->listChain('BEGIN');
			if ($chains === false) { return false; }
			$insert = $callName === 'add_chain';
			if (!$insert && !isset($arr['id']) && !empty($arr['name'])) {
				$insert = true;
				foreach ($chains->children() as $chain) {
					if ((string) $chain['name'] === $arr['name']) { $insert = false; break; }
				}
			}
			if ($insert) {
				$new = new SimpleXMLElement('<chain/>');
				$new->addAttribute('name', isset($arr['name']) ? $arr['name'] : '');
				$new->addAttribute('action', isset($arr['action']) ? $arr['action'] : 'continue');
				foreach ($models ? $models : array() as $name => $parameters) {
					$new->addChild(explode('#', $name)[0]);
				}
				$priority = $this->beginPriority($new);
				$index = 0;
				foreach ($chains->children() as $chain) {
					if ($this->beginPriority($chain) <= $priority) { $index++; }
				}
				// Already-sorted tables need only the insertion itself. Append
				// within a priority group instead of disturbing its other rules.
				$arr['id'] = $index;
				$callName = 'add_chain';
			}
		}
		if (!$this->writeChain($callName, $table, $arr, $models)) {
			return false;
		}
		return $table !== 'BEGIN' || $this->access !== 'request' || $this->sortBeginChains();
	}

	/**
	 * All request BEGIN inserts and edits share this stable priority order.
	 * Rewrite existing positions through WHM instead of deleting/recreating
	 * other features' rules. Response tables retain their existing order.
	 */
	public function sortBeginChains()
	{
		if ($this->access !== 'request') { return true; }
		$result = $this->call('list_chain', array('table_name' => 'BEGIN', 'detail' => 1));
		if (!$result || $result->getCode() != 200 || !$result->get('table_info')) { return false; }
		$table = $result->get('table_info')->children();
		$original = array();
		foreach ($table->children() as $chain) {
			$attributes = array();
			foreach ($chain->attributes() as $key => $value) { $attributes[$key] = (string) $value; }
			$models = array();
			$editable = true;
			foreach ($chain->children() as $model) {
				// The legacy WHM editor accepts flat module attributes. Never
				// discard an extension's nested configuration while reordering.
				if (count($model->children()) || !preg_match('/^(acl|mark)_/', $model->getName())) { $editable = false; }
				$parameters = array();
				foreach ($model->attributes() as $key => $value) { $parameters[$key] = (string) $value; }
				$models[$model->getName() . '#' . count($models)] = $parameters;
			}
			$original[] = array('attributes' => $attributes, 'models' => $models,
				'position' => count($original), 'priority' => $this->beginPriority($chain), 'editable' => $editable);
		}
		$sorted = $original;
		usort($sorted, function ($left, $right) {
			if ($left['priority'] !== $right['priority']) {
				return $left['priority'] < $right['priority'] ? -1 : 1;
			}
			if ($left['position'] === $right['position']) { return 0; }
			return $left['position'] < $right['position'] ? -1 : 1;
		});
		foreach ($sorted as $index => $chain) {
			if ($chain['position'] !== $index && !$chain['editable']) { return false; }
		}
		$changed = array();
		foreach ($sorted as $index => $chain) {
			if ($chain['position'] === $index) { continue; }
			$changed[] = $index;
			$attributes = $chain['attributes'];
			$attributes['id'] = $index;
			if (!$this->writeChain('edit_chain', 'BEGIN', $attributes, $chain['models'])) {
				// Restore overwritten positions if any individual WHM edit fails.
				foreach ($changed as $restoreIndex) {
					$restore = $original[$restoreIndex];
					$restore['attributes']['id'] = $restoreIndex;
					$this->writeChain('edit_chain', 'BEGIN', $restore['attributes'], $restore['models']);
				}
				return false;
			}
		}
		return true;
	}

	private function beginPriority($chain)
	{
		$name = (string) $chain['name'];
		$action = (string) $chain['action'];
		$target = strpos($action, 'table:') === 0 ? substr($action, 6) : '';
		if ($name === self::REAL_IP_CHAIN || isset($chain->mark_replace_ip)) { return 0; }
		if ($name === '!ssl_rewrite' || strpos($name, '!ssl_rewrite_') === 0) { return 10; }
		if ($name === '!ssl_hsts' || strpos($name, '!ssl_hsts_') === 0) { return 20; }
		if ($name === '!rewrite' || $target === '!rewrite') { return 25; }
		$whitelists = array('!allow_vhost_ip', '!allow_url', '!allow_geo');
		if (in_array($name, $whitelists, true) || in_array($target, $whitelists, true)) { return 28; }
		$blacklists = array('!deny_vhost_ip', '!deny_url', '!deny_ip_table', '!deny_geo');
		if (in_array($name, $blacklists, true) || in_array($target, $blacklists, true)) { return 30; }
		if ($action === 'deny' && (isset($chain->acl_src) || isset($chain->acl_srcs) || isset($chain->acl_url))) { return 30; }
		$security = array('!deny_referer', '!anti_sql', '!anti_up_inject', '!anticc', '!safeline_waf', '!deny_filter');
		if (in_array($name, $security, true) || in_array($target, $security, true)) { return 50; }
		// Speed limits and unrecognized custom rules remain
		// in the basic group; same-group rules keep their relative order.
		return 40;
	}

	private function writeChain($callName, $table, $arr, $models = null)
	{
		$arr['table_name'] = $table;
		$whmCall = new WhmCall('core.whm', $callName);
		$whmCall->multi_param = true;
		$whmCall->addParam('access', $this->access);

		if ($this->vh) {
			$whmCall->addParam('vh', $this->vh);
		}

		foreach ($arr as $k => $v) {
			$whmCall->addParam($k, $v);
		}

		if ($models) {
			foreach ($models as $name => $val) {
				if (strpos($name, '#') !== false) {
					$name = explode('#', $name)[0];
				}
				if (isset($val[0])) {
					foreach ($val as $v) {
						$this->setModel($whmCall, $name, $v);
					}
				}
				else {
					$this->setModel($whmCall, $name, $val);
				}
			}
		}

		$result = $this->whm->call($whmCall);
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	private function setModel(&$whmCall, $name, $val)
	{
		$whmCall->addParam('begin_sub_form', $name);

		foreach ($val as $k => $v) {
			$whmCall->addParam($k, $v);
		}

		$whmCall->addParam('end_sub_form', 1);
	}

	public function delChainByName($table, $name)
	{
		$result = $this->call('del_chain', array('table_name' => $table, 'name' => $name));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	public function delChain($table, $id)
	{
		$result = $this->call('del_chain', array('table_name' => $table, 'id' => $id));
		if (!$result || $result->getCode() != 200) {
			return false;
		}

		return true;
	}

	private function call($callname, $arr)
	{
		$whmCall = new WhmCall('core.whm', $callname);
		$whmCall->multi_param = true;
		$whmCall->addParam('access', $this->access);

		if ($this->vh) {
			$whmCall->addParam('vh', $this->vh);
		}

		if ($arr) {
			foreach ($arr as $k => $v) {
				$whmCall->addParam($k, $v);
			}
		}

		return $this->whm->call($whmCall);
	}
}
