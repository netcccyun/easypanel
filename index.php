<?php
if (file_exists('config.php')) {
	header('Location: /vhost/?c=session&a=loginForm');
	exit();
	return 1;
}

header('Location: /admin/?c=session&a=loginForm');

?>