<?php
include_once SYS_ROOT . '/smarty/Smarty.class.php';

class TPL
{
	static private $instance;

	static public function template_config($dir)
	{
		$tpl = new Smarty();
		$tpl->compile_dir = SYS_ROOT . '/templates_c/';
		$tpl->caching = false;
		$tpl->left_delimiter = '{{';
		$tpl->right_delimiter = '}}';
		$tpl->template_dir = $dir;
		return $tpl;
	}

	static public function singleton()
	{
		if (!(self::$instance instanceof Smarty)) {
			self::$instance = new Smarty();
			self::$instance->use_sub_dirs = true;
			$view_dir = daocall('setting', 'get', array('view_dir'));

			if (!$view_dir) {
				$view_dir = 'default';
			}

			if (defined('VHOST_PATH')) {
				if (is_mobile_request()) {
					$view_dir = 'ws';

					if (!file_exists(APPLICATON_ROOT . '/view/' . $view_dir)) {
						$view_dir = 'default';
					}
				}
			}
			else {
				$view_dir = 'default';
			}

			self::$instance->template_dir = APPLICATON_ROOT . '/view/' . $view_dir;
			self::$instance->compile_dir = SYS_ROOT . '/templates_c/' . $view_dir;

			if (!defined(TPL_ROOT)) {
				define(TPL_ROOT, dirname($_SERVER['PHP_SELF']));
			}

			$static = TPL_ROOT;

			if (substr($static, 0 - 1) != '/') {
				$static .= '/';
			}

			$static .= 'view/' . $view_dir . '/';
			self::$instance->assign('STATIC', $static);
			self::$instance->assign('role', getRoles());
			self::$instance->left_delimiter = '{{';
			self::$instance->right_delimiter = '}}';
		}

		return self::$instance;
	}
}

?>