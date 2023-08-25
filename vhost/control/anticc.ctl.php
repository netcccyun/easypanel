<?php
needRole('vhost');
define('BEGIN', 'BEGIN');
define('TABLENAME', '!anticc');
define('ACTION', 'table:!anticc');
class AnticcControl extends Control
{
	private $access;

	public function __construct()
	{
		parent::__construct();
		load_lib('pub:access');
		$this->access = new Access(getRole('vhost'));
	}

	public function anticcFrom()
	{
		$user = daocall('vhost', 'getVhost', array(getRole('vhost')));

		$this->anticcAddTable();

		if ($this->access->findChain(BEGIN, TABLENAME)) {
			$this->_tpl->assign('at', 1);
		}

		$result = $this->access->listChain(TABLENAME);

		$white_urls = [];
		if ($result) {
			foreach ($result->children() as $chain) {
				foreach ($chain->children() as $name=>$ch) {
					if($name == 'mark_anti_cc'){
						$msg = file_get_contents($user['doc_root'] . '/access.xml');
						preg_match("/<html id=\'anticc_(.*?)\'>/", $msg, $match);
						$mode = $match[1];
						$cc = array('request' => (string) $ch['request'], 'second' => (string) $ch['second'], 'wl' => (string) $ch['wl'], 'flush' => (string) $ch['flush'], 'fix_url' => (string) $ch['fix_url'], 'skip_cache' => (string) $ch['skip_cache'], 'mode' => $mode);

						$this->_tpl->assign('cc', $cc);
					}elseif($name == 'acl_srcs'){
						$this->_tpl->assign('whiteip', str_replace('|',"\r\n",$ch));
					}elseif($name == 'acl_url'){
						$white_urls[] = $ch;
					}
				}
			}
		}

		$mode_list = $this->anticc_mode();
		$modes = [];
		foreach($mode_list as $key => $item){
			if($item['show']){
				$modes[$key] = $item['name'];
			}
		}
		$this->_tpl->assign('modes', $modes);
		$this->_tpl->assign('whiteurl', implode("\r\n", $white_urls));

		return $this->_tpl->fetch('anticc/anticcfrom.html');
	}

	private function anticc_mode(){
		$mode_list = [
			'redirect' => [
				'name' => '普通跳转模式',
				'html' => "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: keep-alive\r\nCache-Control: no-cache,no-store\r\n\r\n<html id='anticc_redirect'><body><script language='javascript'>{{revert:cbk_var}};cbk_defender_{{session_key}}=cbk_var;cbk_var='';window.location=cbk_defender_{{session_key}};</script></body></html>",
				'show' => true
			],
			'timeout' => [
				'name' => '延时跳转模式',
				'html' => "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: keep-alive\r\nCache-Control: no-cache,no-store\r\n\r\n<!DOCTYPE html><html id='anticc_timeout'><head><title></title><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no\"><meta name=\"renderer\" content=\"webkit\"><meta http-equiv=\"x-ua-compatible\" content=\"IE=edge,chrome=1\"><link rel=\"stylesheet\" href=\"//cdn.66zan.cn/waf_style.css\"></head><body><div class=\"box\"><div class=\"logo\"><img src=\"//cdn.66zan.cn/waf_logo.gif\" width=\"100\"></div><div class=\"tip\"><small><div class=\"ipinfo\"><b id=\"cip\"></b><span id=\"cname\"></span></div><div>当前网站访问人数较多</div><div>系统正在自动为您分配最快的服务器</div></small></div><div class=\"progress\"><div id=\"progress-bar\" class=\"progress-bar progress-bar-success\" role=\"progressbar\" aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width:0%\"></div></div></div><script>{{revert:caihong_defender_tmp}};caihong_defender_{{session_key}}=caihong_defender_tmp;caihong_defender_tmp='';function progress(p){document.getElementById(\"progress-bar\").style.width=p+\"%\"}setTimeout(function(){progress(\"5\");setTimeout(function(){progress(\"60\");setTimeout(function(){progress(\"95\");window.location.href=caihong_defender_{{session_key}};},500);},500);},300);</script></body></html>",
				'show' => true
			],
			'click' => [
				'name' => '点击验证模式',
				'html' => "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: keep-alive\r\nCache-Control: no-cache,no-store\r\n\r\n<!DOCTYPE html><html id='anticc_click'><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><title>请进行安全验证</title><meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge,chrome=1\"><meta content=\"width=device-width,initial-scale=1\" name=\"viewport\"><link rel=\"stylesheet\" href=\"//cdn.66zan.cn/waf_click2.css\" charset=\"utf-8\"></head><body><div class=\"container\"><div class=\"header\"><p>很抱歉，当前访问人数过多，请完成<strong>“安全验证”</strong>后继续访问</p></div></div><script src=\"//cdn.staticfile.org/jquery/1.12.4/jquery.min.js\"></script><script src=\"//cdn.staticfile.org/layer/2.3/layer.js\"></script><script>$(document).ready(function(){layer.open({type:1,title:'请点击下方按钮',shadeClose:false,closeBtn:0,scrollbar:false,shade:0.5,move:false,area:['260px'],content:'<div class=\"caption-wrap\"><div id=\"one_points\" class=\"caption\"></div></div>'});$(\"#one_points\").click(function(){ {{revert:cbk_var}}cbk_defender_{{session_key}}=cbk_var;cbk_var='';layer.msg('验证成功，正在跳转中...',{icon:16,shade:0.01,time:15000});window.location.href=cbk_defender_{{session_key}} })})</script></body></html>",
				'show' => true
			],
			'slideverify' => [
				'name' => '滑动验证码模式',
				'html' => "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: keep-alive\r\nCache-Control: no-cache,no-store\r\n\r\n<!DOCTYPE html><html id='anticc_slideverify'><head><title></title><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no\"><meta name=\"apple-mobile-web-app-capable\" content=\"yes\"><meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black\"><meta name=\"renderer\" content=\"webkit\"><meta http-equiv=\"x-ua-compatible\" content=\"IE=edge,chrome=1\"><meta name=\"format-detection\" content=\"telephone=no\"><title>请进行安全验证</title><link type=\"text/css\" href=\"//cdn.66zan.cn/slideVerify.css\" rel=\"stylesheet\"></head><body><div class=\"main\"><div class=\"top-tip\">♥&nbsp;&nbsp;网站当前访问量较大，请拖动滑块后继续访问</div><div class=\"slide-box\"><div class=\"verify-wrap\" id=\"verify-wrap\"></div></div></div><script type=\"text/javascript\" src=\"//cdn.staticfile.org/jquery/1.12.4/jquery.min.js\"></script><script type=\"text/javascript\" src=\"//cdn.66zan.cn/slideVerify.js\"></script><script>{{revert:caihong_defender_tmp}};caihong_defender__{{session_key}}=caihong_defender_tmp;caihong_defender_tmp='';\$(function(){var SlideVerifyPlug = window.slideVerifyPlug;var slideVerify = new SlideVerifyPlug('#verify-wrap',{getSucessState:function(res){if(res==true)location.href=caihong_defender__{{session_key}};}});})</script></body></html>",
				'show' => true
			],
			'captcha' => [
				'name' => '滑动验证码2模式',
				'html' => "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: keep-alive\r\nCache-Control: no-cache,no-store\r\n\r\n<!DOCTYPE html><html id='anticc_captcha'><head><title>CDN安全防护系统</title><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1,user-scalable=no\"><link rel=\"stylesheet\" href=\"//cdn.66zan.cn/captchan.css\" charset=\"utf-8\"></head><body><div class=\"container\"><div class=\"header\"><p>很抱歉，当前访问人数过多，请完成<strong>“安全验证”</strong>后继续访问</p></div></div><script src=\"//static.geetest.com/v4/gt4.js\"></script><script>{{revert:cbk_var}}cbk_defender_{{session_key}}=cbk_var;cbk_var='';initGeetest4({captchaId:'24f56dc13c40dc4a02fd0318567caef5',product:'bind',protocol:'https://',mask:{outside:false},hideSuccess:true},function(captcha){captcha.onReady(function(){captcha.showCaptcha()}).onSuccess(function(){location.href=cbk_defender_{{session_key}}}).onError(function(){alert('验证码加载失败，请刷新页面重试')})});</script></body></html>",
				'show' => true
			],
			'vcode' => [
				'name' => '图形验证码模式',
				'html' => "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: keep-alive\r\nCache-Control: no-cache,no-store\r\n\r\n<!DOCTYPE html><html id='anticc_vcode'><head><title>访问验证</title><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><style>body,html{width:100%;height:100%}body{display:flex;justify-content:center;align-items:center;margin:0;padding:0;text-align:center;font-family:\"微软雅黑\",Arial,Helvetica,sans-serif;font-size:14px;background-color:#f9f9f9;color:#666}div,dl,dt,h1,h2,input,li,p,ul{margin:0;padding:0}h1{font-size:25px;text-align:left;line-height:40px;margin-bottom:10px;color:#666}.main{width:456px}.warncontenter{height:280px;background-color:#fff;margin:30px 0;padding-top:30px}.captcha-text{width:112px;height:34px;margin:23px 0 0 36px;font-size:19px;color:#111;line-height:26px;font-weight:600}.capture-img{float:left;margin-left:20px;width:200px}.capture-img span{margin-left:10px;line-height:40px}.capture-img span img{width:160px}.warnbtn{margin:40px auto}.code-btn{width:174px;height:30px;line-height:30px;background:#008cff;color:#fff;border-radius:59.5px;font-weight:700;text-align:center;border:0;cursor:pointer;margin-right:40px;margin-top:20px}.code-input{width:180px;height:32px;margin-right:36px;border:1px solid #ccc;border-left:none;border-right:none;border-top:none;padding:0 0 0 10px;outline:0}.visit-ip{text-align:left;margin-left:45px}</style></head><body><div class=\"main\"><div class=\"warncontenter\"><div class=\"captcha-text\">验证码访问</div><div class=\"visit-ip\">当前网站访问量过大，请输入验证码继续浏览</div><div class=\"form\" style=\"margin-top:40px\"><form action=\"/KANGLE_CCIMG.php\" method=\"GET\"><input name='k' value='{{session_key}}' type='hidden'><div class=\"captcha\"><div class=\"capture-img\"><span><img src=\"/KANGLE_CCIMG.php?k={{session_key}}\" alt=\"验证码\"></span></div><div class=\"capture-input\"><input type=\"text\" name=\"v\" class=\"code-input\" placeholder=\"输入验证码\" autocomplete=\"off\"><input type=\"submit\" value=\"确定\" class=\"code-btn\"></div></div></form></div></div></div></body></html>",
				'show' => true
			],
		];
		if(!file_exists('/var/run/cdnbest.pid')){
			unset($mode_list['vcode']);
		}
		return $mode_list;
	}

	public function anticcAdd()
	{
		$check_result = apicall('access', 'checkAccess', array('ent'));

		if ($check_result !== true) {
			exit($check_result);
		}

		$mode = trim($_REQUEST['mode']);
		$mode_list = $this->anticc_mode();
		if(!array_key_exists($mode, $mode_list))exit('未知防护模式');
		$msg = $mode_list[$mode]['html'];

		$this->access->delChainByName(TABLENAME, TABLENAME);

		$request = intval($_REQUEST['request']);
		$second = intval($_REQUEST['second']);
		$whiteip = $_REQUEST['whiteip'];
		$whiteurl = $_REQUEST['whiteurl'];
		if(!empty($whiteip)){
			$whiteip = str_replace(array("\r\n", "\r", "\n"), "|", $whiteip);
			$arrs = explode("|",$whiteip);
			$ipdata = '';
			foreach($arrs as $ip){
				$ip = trim($ip);
				if(empty($ip) || !strpos($ip,'.'))continue;
				$ipdata .= $ip . '|';
			}
			$ipdata = trim($ipdata, '|');
		}

		$wl = 1;
		$fix_url = 1;
		$skip_cache = 1;
		$arr['action'] = 'continue';
		$arr['name'] = TABLENAME;
		if(!empty($ipdata)){
			$modeles['acl_srcs'] = array('revers' => 1, 'split' => '|', 'v' => $ipdata);
		}
		if(!empty($whiteurl)){
			$whiteurl = str_replace(array("\r\n", "\r", "\n"), "[br]", $whiteurl);
			$arrs = explode("[br]",$whiteurl);
			$i=0;
			foreach($arrs as $url){
				$url = trim($url);
				if(empty($url))continue;
				$modeles['acl_url#'.$i++] = array('revers' => 1, 'nc' => 1, 'url' => $url);
			}
		}
		$modeles['mark_anti_cc'] = array('request' => $request, 'second' => $second, 'wl' => $wl, 'fix_url' => $fix_url, 'skip_cache' => $skip_cache, 'msg' => $msg);
		$result = $this->access->addChain(TABLENAME, $arr, $modeles);

		if (!$result) {
			exit('保存设置失败');
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	/**
	 * 开关
	 * Enter description here ...
	 */
	public function anticcCheckOn()
	{
		$status = intval($_REQUEST['status']);

		switch ($status) {
		case '2':
			$this->access->delChainByName(BEGIN, TABLENAME);
			break;

		case '1':
			$arr = array('action' => ACTION, 'name' => TABLENAME);
			$this->access->addChain(BEGIN, $arr);
			break;

		default:
			break;
		}

		apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
		exit('成功');
	}

	public function anticcDel()
	{
		if ($this->access->delChainByName(TABLENAME, TABLENAME)) {
			apicall('vhost', 'updateVhostSyncseq', array(getRole('vhost')));
			exit('成功');
		}

		exit('删除失败');
	}

	private function anticcAddChain()
	{
		if ($this->access->findChain(BEGIN, TABLENAME)) {
			return true;
		}

		$arr = array('action' => ACTION, 'name' => TABLENAME);
		$this->access->addChain(BEGIN, $arr);
	}

	/**
	 * 创建表
	 * Enter description here ...
	 */
	private function anticcAddTable()
	{
		$tables = $this->access->listTable();
		$table_finded = false;

		foreach ($tables as $table) {
			if ($table == TABLENAME) {
				$table_finded = true;
				break;
			}
		}

		if (!$table_finded) {
			if (!$this->access->addTable(TABLENAME)) {
				return $this->show_msg('不能增加表');
			}
		}
	}

	private function show_msg($msg)
	{
		$this->_tpl->assign('msg', $msg);
		return $this->_tpl->fetch('msg.html');
	}
}

?>