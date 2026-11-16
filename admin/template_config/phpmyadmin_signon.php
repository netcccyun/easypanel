<?php
// 由 Easypanel 生成，用于 phpMyAdmin signon 自动登录，请勿手工修改
$user = isset($_POST['user']) ? $_POST['user'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (!is_string($user) || $user === '' || !is_string($password)) {
    http_response_code(400);
    exit('缺少登录信息，请从面板重新进入');
}

header('Cache-Control: no-store');
session_name('ep_pma_signon');
session_start();
$_SESSION['PMA_single_signon_user'] = $user;
$_SESSION['PMA_single_signon_password'] = $password;
session_write_close();

header('Location: index.php');
