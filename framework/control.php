<?php
class Control
{
	static public $__instance;
	static public $__out;
	static public $_tpl;

	/**
	 * 控制器构造函数
	 *
	 * 当子类实现自己的控制器构造函数时，必须在构造函数体内第一行调用： parent::__construct();
	 */
	public function __construct()
	{
		global $__core_env;
		$rand = rand(1000, 9999);
		$__core_env['__LIUSHUIHAO__'] = time() . $UID . $rand;
		$this->__out = &$__core_env['out'];
		$this->_tpl = tpl::singleton();
	}

	/**
	 * 控制器构造函数
	 *
	 * 当子类实现自己的控制器析构函数时，必须在析构函数体内最后一行调用： parent::__destruct();
	 */
	public function __destruct()
	{
		global $__core_env;
	}

	public function assign($tpl_var, $value = null, $nocache = false)
	{
		return $this->_tpl->assign($tpl_var, $value, $nocache);
	}

	public function display($template)
	{
		echo $this->fetch($template);
	}

	public function fetch($template)
	{
		$locale = 'zh_CN';
		$lang = get_lang();

		if (is_array($GLOBALS['lang'][$locale])) {
			$lang = array_merge($lang, $GLOBALS['lang'][$locale]);
		}

		change_to_super();
		$this->_tpl->assign('lang', $lang);

		try {
			return $this->_tpl->fetch($template);
		}
		catch (Exception $e) {
			$this->_tpl->template_dir = APPLICATON_ROOT . '/view/default';
			return $this->_tpl->fetch($template);
		}
	}

	/**
	 * 错误异常抛出
	 * @param string msg
	 */
	public function __control_exit($msg)
	{
		trigger_error($msg, E_USER_ERROR);
	}

	public function index()
	{
	}

	protected function out_error($errno = 500, $ret = false)
	{
		if ($ret['title'] == '') {
			$ret['title'] = '';
		}

		if ($ret['content'] == '') {
			$ret['content'] = '没有信息';
		}

		if ($ret['url'] == '') {
			$ret['url'] = '?c=user&a=info';
		}

		$this->_tpl->assign('title', $ret['title']);
		$this->_tpl->assign('content', $ret['content']);
		$this->_tpl->assign('url', $ret['url']);
		$this->_tpl->display('error.html');
	}

	protected function out_result($ret = array())
	{
		if ($ret['title'] == '') {
			$ret['title'] = '';
		}

		if ($ret['content'] == '') {
			$ret['content'] = '没有信息';
		}

		if ($ret['url'] == '') {
			$ret['url'] = '?c=user&a=info';
		}

		$this->_tpl->assign('title', $ret['title']);
		$this->_tpl->assign('content', $ret['content']);
		$this->_tpl->assign('url', $ret['url']);
		$this->_tpl->display('error.html');
	}
}


?>