<?php
// 由 Easypanel 生成，用于 phpMyAdmin signon 登录，请勿手工修改
if (isset($_GET['logout'])) {
	// 清掉 signon 凭据会话，否则 phpMyAdmin 下一个请求又会被自动登回去
	session_name('ep_pma_signon');
	session_start();
	$_SESSION = array();

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}

	session_destroy();
	setcookie('ep_pma_pending', '', time() - 36000, '/');
	header('Location: ep_signon.php?loggedout=1&bye=1');
	exit();
}

if (isset($_GET['loggedout'])) {
	header('Cache-Control: no-store');
	header('Content-Type: text/html; charset=utf-8');
	// 上一次提交后没进到 phpMyAdmin 就回到这里，说明账号或密码错误
	$failed = !empty($_COOKIE['ep_pma_pending']);

	if ($failed) {
		setcookie('ep_pma_pending', '', time() - 36000, '/');
	}

	$notice = isset($_GET['bye']) ? '已退出数据库管理。' : '';
	$notice .= $failed ? '<span style="color:#c33">账号或密码错误，请重试。</span>' : '';
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>数据库管理登录</title>'
		. '<style>body{font:14px/1.7 system-ui,"Microsoft YaHei",sans-serif;margin:56px auto;max-width:360px;color:#333}'
		. 'h3{font-weight:600;margin:0 0 4px}p.note{color:#666;margin:0 0 18px}'
		. 'input{width:100%;box-sizing:border-box;padding:8px 10px;margin-bottom:10px;border:1px solid #ccc;border-radius:4px}'
		. 'button{width:100%;padding:9px;border:0;border-radius:4px;background:#0a7;color:#fff;cursor:pointer}'
		. '</style></head><body>'
		. '<h3>数据库管理登录</h3><p class="note">' . $notice . '请输入 MySQL 账号密码</p>'
		. '<form method="post" action="ep_signon.php">'
		. '<input name="user" placeholder="账号" autocomplete="username" required>'
		. '<input name="password" type="password" placeholder="密码" autocomplete="current-password">'
		. '<button type="submit">登录</button>'
		. '</form></body></html>';
	exit();
}

$user = isset($_POST['user']) ? $_POST['user'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (!is_string($user) || $user === '' || !is_string($password)) {
	header('Location: ep_signon.php?loggedout=1');
	exit();
}

header('Cache-Control: no-store');
session_name('ep_pma_signon');
session_start();
$_SESSION['PMA_single_signon_user'] = $user;
$_SESSION['PMA_single_signon_password'] = $password;
session_write_close();
setcookie('ep_pma_pending', '1', time() + 60, '/');

header('Location: index.php');
