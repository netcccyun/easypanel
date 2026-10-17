<?php
needRole('vhost');
class RealipControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'), 'request');
	}

	private function headers()
	{
		return array('X-Forwarded-For', 'X-Real-Ip', 'CF-Connecting-IP', 'True-Client-IP', 'Cdn-Src-Ip', 'Ali-CDN-Real-IP');
	}

	public function show()
	{
		$permission = apicall('access', 'checkAccess', array());
		if ($permission !== true) {
			$this->_tpl->assign('msg', $permission);
			return $this->_tpl->fetch('msg.html');
		}
		$settings = $this->settings();
		if ($settings === false) {
			$this->_tpl->assign('msg', '读取真实IP设置失败，请重试');
			return $this->_tpl->fetch('msg.html');
		}
		$settings['custom'] = $settings['enabled'] && !in_array($settings['header'], $this->headers(), true);
		$this->_tpl->assign('realip', $settings);
		$this->_tpl->assign('realip_headers', $this->headers());
		return $this->_tpl->fetch('realip.html');
	}

	public function save()
	{
		$permission = apicall('access', 'checkAccess', array());
		if ($permission !== true) {
			exit($permission);
		}
		$status = ep_request('status');
		$header = ep_request('header');
		if ($status !== '0' && $status !== '1') {
			exit('参数错误');
		}
		if ($status === '1') {
			if (!is_string($header)) { exit('请输入有效的Header名称'); }
			$header = trim($header);
			if (!preg_match('/\A[!#$%&\'*+.^_`|~0-9A-Za-z-]+\z/D', $header)) {
				exit('请输入有效的Header名称，不要包含冒号、空格或换行');
			}
		}
		if (!$this->saveSettings($status === '1', $header)) {
			exit('保存设置失败，请重试');
		}
		notice_cdn_changed();
		exit('成功');
	}

	private function settings()
	{
		$tables = $this->access->listTable();
		if ($tables === false) { return false; }
		$settings = array('enabled' => false, 'header' => 'X-Forwarded-For',
			'has_begin' => in_array('BEGIN', $tables, true));
		if (!$settings['has_begin']) { return $settings; }
		$chains = $this->access->listChain('BEGIN');
		if ($chains === false) { return false; }
		foreach ($chains->children() as $chain) {
			if ((string) $chain['name'] === Access::REAL_IP_CHAIN) {
				$settings['enabled'] = true;
				foreach ($chain->children() as $name => $model) {
					if ($name === 'mark_replace_ip') {
						$settings['header'] = (string) $model['header'];
					}
				}
				break;
			}
		}
		return $settings;
	}

	private function saveSettings($enabled, $header)
	{
		$settings = $this->settings();
		if ($settings === false) { return false; }
		if (!$enabled) {
			return !$settings['enabled'] || $this->access->delChainByName('BEGIN', Access::REAL_IP_CHAIN);
		}
		if (!$settings['has_begin'] && !$this->access->addTable('BEGIN')) { return false; }
		$attributes = array('name' => Access::REAL_IP_CHAIN, 'action' => 'continue');
		// replace_ip otherwise copies the entire header, including the proxy
		// list in X-Forwarded-For. Capture one IPv4/IPv6 address instead.
		$models = array('mark_replace_ip' => array('header' => $header,
			'val' => '^\s*((?:[0-9]{1,3}\.){3}[0-9]{1,3}|[0-9A-Fa-f]*:[0-9A-Fa-f:.]+)\s*(?:,|$)'));
		return $this->access->editChain('BEGIN', $attributes, $models);
	}
}
