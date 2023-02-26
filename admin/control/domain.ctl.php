<?php
needRole('admin');
@load_conf('pub:reserv_domain');
class DomainControl extends Control
{
	public function domainFrom()
	{
		$this->_tpl->assign('domains', $GLOBALS['reserv_domain']);
		return $this->_tpl->fetch('domain/domainfrom.html');
	}

	public function domainAdd()
	{
		$domain_name = trim($_REQUEST['domain_name']);

		if (is_array($GLOBALS['reserv_domain'])) {
			$GLOBALS['reserv_domain'][] = $domain_name;
		}

		$fp = fopen(SYS_ROOT . '/configs/reserv_domain.cfg.php', 'wt');

		if (!$fp) {
			exit('不能打开文件' . SYS_ROOT . '/configs/reserv_domain.cfg.php');
		}

		apicall('utils', 'writeDomainConfig', array($fp, array_unique($GLOBALS['reserv_domain'])));
		header('Location: ?c=domain&a=domainFrom');
		exit();
	}

	public function domainDel()
	{
		$del_domain_name = trim($_REQUEST['domain_name']);
		$fp = fopen(SYS_ROOT . '/configs/reserv_domain.cfg.php', 'wt');

		if (!$fp) {
			exit('不能打开文件' . SYS_ROOT . '/configs/reserv_domain.cfg.php');
		}

		apicall('utils', 'writeDomainConfig', array($fp, array_unique($GLOBALS['reserv_domain']), $del_domain_name));
		header('Location: ?c=domain&a=domainFrom');
		exit();
	}
}

?>