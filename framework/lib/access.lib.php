<?php
class Access
{
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

	public function addChain($table, $arr, $models = null, $id = null)
	{
		return $this->replaceChain('add_chain', $table, $arr, $models, $id);
	}

	private function replaceChain($callName, $table, $arr, $models = null, $id = null)
	{
		$arr['table_name'] = $table;
		$whmCall = new WhmCall('core.whm', $callName);
		$whmCall->multi_param = true;
		$whmCall->addParam('access', $this->access);

		if ($this->vh) {
			$whmCall->addParam('vh', $this->vh);
		}

		if ($id !== null) {
			// kangle 端 EvNew 按 id 插入：-1=追加尾部，N=插到位置 N。
			// 不传 id 时 kangle 默认 0，插入位置随链数变化（0/1条=尾部、≥2条=插顶），
			// 这正是 BEGIN 链顺序不可预测的根源，因此重排时必须显式传 id。
			$whmCall->addParam('id', intval($id));
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

	/**
	 * BEGIN 表固定优先级：靠前的链先匹配（安全 deny 类在上、挑战类其次、路由跳转垫底）。
	 * prefix: 规则匹配链名前缀（用于每域名多条例外链）。
	 */
	private function beginChainBucket($name)
	{
		static $priority = array(
			// CDN真实IP替换必须最先执行：否则后续所有按IP的功能（黑白名单/防CC）匹配到的都是CDN节点IP
			'prefix:!real_ip',
			// 强制HTTPS(301)/HSTS 属于路由层，先于安全防护层执行：白名单只豁免安全防护，不豁免基础跳转
			'prefix:!ssl_rewrite', 'prefix:!hsts',
			'!allow_vhost_ip', '!allow_url', '!deny_vhost_ip', '!cdn', '!shield_method', '!deny_url',
			'!deny_referer', '!anti_sql', '!anti_up_inject', '!anticc', '!safeline_waf',
			'!speed_limit',
		);

		foreach ($priority as $i => $rule) {
			if (strncmp($rule, 'prefix:', 7) === 0) {
				if (strpos($name, substr($rule, 7)) === 0) {
					return $i;
				}
			}
			else {
				if ($rule === $name) {
					return $i;
				}
			}
		}

		// 未知链排在所有已知链之后、域名跳转(!rewrite)之前，保持原相对顺序
		return $name === '!rewrite' ? count($priority) + 1 : count($priority);
	}

	/**
	 * 按固定优先级重建 BEGIN 表全部链，消除开启顺序对优先级的影响。
	 * kangle 的子表(table:)无匹配后会回溯父表继续，因此只要顺序固定，
	 * 各功能即可按预期优先级生效（详见 issue #7 分析）。
	 *
	 * ponytail: 每次调用约 2N+1 次 WHM 调用（N≈12，管理操作低频可接受）；
	 * 中途失败会留下"链完整但顺序未排"的中间态，下次任意开关自动修正；
	 * 未命名链无法按名删除，发现即整体放弃重排（升级路径：kangle del_chain 支持 id 后可放开）。
	 */
	public function sortBeginChains()
	{
		$chains = $this->listChain('BEGIN', 1);
		if (!$chains) {
			return false;
		}

		$items = array();
		foreach ($chains->children() as $chain) {
			$attrs = array();
			foreach ($chain->attributes() as $k => $v) {
				$attrs[(string) $k] = (string) $v;
			}

			if (!isset($attrs['name']) || $attrs['name'] === '') {
				return false;
			}

			$models = array();
			foreach ($chain->children() as $name => $model) {
				$ma = array();
				foreach ($model->attributes() as $k => $v) {
					$ma[(string) $k] = (string) $v;
				}
				$models[(string) $name][] = $ma;
			}

			$items[] = array('bucket' => $this->beginChainBucket($attrs['name']), 'attrs' => $attrs, 'models' => $models);
		}

		if (count($items) <= 1) {
			return true;
		}

		// 桶序号升序、桶内保持原相对顺序；当前顺序已正确则不动，避免无谓的 WHM 调用
		$keys = array();
		foreach ($items as $k => $item) {
			$keys[$k] = $item['bucket'];
		}
		array_multisort($keys, SORT_NUMERIC, SORT_ASC, $items);

		$origin_names = array();
		foreach ($chains->children() as $chain) {
			$origin_names[] = (string) $chain['name'];
		}
		$target_names = array();
		foreach ($items as $item) {
			$target_names[] = $item['attrs']['name'];
		}
		if ($origin_names === $target_names) {
			return true;
		}

		// 先全部删除（while 容忍同名重复链），再按目标顺序显式 id 重建
		foreach ($target_names as $name) {
			while ($this->findChain('BEGIN', $name)) {
				if (!$this->delChainByName('BEGIN', $name)) {
					break;
				}
			}
		}

		$result = true;
		foreach ($items as $i => $item) {
			if (!$this->addChain('BEGIN', $item['attrs'], $item['models'], $i)) {
				$result = false;
			}
		}
		return $result;
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
