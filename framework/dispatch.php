<?php
function __dispatch_init()
{
	global $__core_env;
	if (isset($_REQUEST['c']) && $_REQUEST['c'] != '') {
		$__core_env['control'] = $_REQUEST['c'];

		if (!preg_match('/^[a-z0-9_-]+$/i', $__core_env['control'])) {
			__dispatch_exit('无效的控制器: ' . $__core_env['control']);
		}

		$__core_env['control'] = str_replace(array('-'), array('/'), $__core_env['control']);
	}
	else if (defined('DEFAULT_CONTROL')) {
		$__core_env['control'] = DEFAULT_CONTROL;
	}
	else {
		__dispatch_exit('未指定默认控制器');
	}

	if (isset($_REQUEST['a']) && $_REQUEST['a'] != '') {
		$__core_env['action'] = $_REQUEST['a'];

		if (!preg_match('/^[a-z0-9_]+$/i', $__core_env['action'])) {
			__dispatch_exit('无效的事件 ' . $__core_env['action']);
		}

		if (substr($__core_env['action'], 0, 1) == '_') {
			__dispatch_exit('无效的事件 ' . $__core_env['action']);
			return NULL;
		}
	}
	else {
		$__core_env['action'] = 'index';
	}
}

function check_dispatch($control, $action)
{
}

function __dispatch_start()
{
	global $__core_env;
	$pos = strrpos($__core_env['control'], '/');

	if (false === $pos) {
		$control_name = $__core_env['control'];
	}
	else {
		$control_name = substr($__core_env['control'], $pos + 1, 100);
	}

	load_ctl($__core_env['control']);
	$control_name[0] = strtoupper($control_name[0]);
	$class = $control_name . 'Control';

	if (!class_exists($class)) {
		__dispatch_exit($class . '  控制器无效');
	}

	if (substr($__core_env['action'], 0, 1) == '_') {
		__dispatch_exit($class . ' 控制器的 ' . $__core_env['action'] . ' 该事件被禁止访问');
	}

	$inst = new $class();
	if ($inst && method_exists($inst, $__core_env['action'])) {
		$t_start = microtime_float();
		$result = $inst->$__core_env['action']();

		if (!defined('__OUTVIEW__')) {
			$t_end = microtime_float();
			$t_cost = round($t_end - $t_start, 5);
			$stat = array('l' => 'CONTORL', 'c' => $control_name, 'a' => $__core_env['action'], 't' => $t_cost);

			if (!empty($__core_env['out'])) {
				echo json_encode($__core_env['out']);
			}
		}
	}
	else {
		__dispatch_exit($class . ' 控制器的 ' . $__core_env['action'] . ' 事件不存在');
	}

	return $result;
}

function __dispatch_exit($msg)
{
	setLastError($msg);
	exit();
}

function dispatch($control, $action)
{
	global $__core_env;

	if ($__core_env[$control . ':' . $action] == 1) {
		__dispatch_exit('control=' . $control . ' action=' . $action . ' 被重复执行');
		return '';
	}

	$__core_env[$control . ':' . $action] = 1;
	$pos = strrpos($control, '/');

	if (false === $pos) {
		$control_name = $control;
	}
	else {
		$control_name = substr($control, $pos + 1, 100);
	}

	if (!preg_match('/^[a-z0-9_-]+$/i', $control)) {
		__dispatch_exit('无效的控制器: ' . $control);
	}

	load_ctl($control);
	$control_name[0] = strtoupper($control_name[0]);
	$class = $control_name . 'Control';

	if (!class_exists($class)) {
		__dispatch_exit($class . '  控制器无效');
	}

	if (substr($action, 0, 1) == '_') {
		__dispatch_exit($class . ' 控制器的 ' . $action . ' 该事件被禁止访问');
	}

	$inst = new $class();
	if ($inst && method_exists($inst, $action)) {
		$result = $inst->$action();
	}
	else {
		__dispatch_exit($class . ' 控制器的 ' . $action . ' 事件不存在');
	}

	return $result;
}


?>