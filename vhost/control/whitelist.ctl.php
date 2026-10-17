<?php
require_once dirname(__FILE__) . '/banip.ctl.php';

class WhitelistControl extends BanipControl
{
    protected $ipTable = '!allow_vhost_ip';
    protected $urlTable = '!allow_url';
    protected $geoTable = '!allow_geo';
    protected $ruleAction = 'allow';
    protected $listLabel = '白名单';
    protected $listController = 'whitelist';
    protected $templatePath = 'whitelist';

    public function __construct()
    {
        parent::__construct();
        $permission = apicall('access', 'checkAccess', array());
        if ($permission !== true) { exit($permission); }
    }
}
