<?php
needRole('vhost');
class WebalizerControl extends control
{
	public function showLog()
	{
		$vhost = getRole('vhost');
		$user = isset($_SESSION['user'][$vhost]) ? $_SESSION['user'][$vhost] : array();
		$file = isset($_REQUEST['file']) ? (string) $_REQUEST['file'] : 'index.html';
		if ($file === '' || $file === 'log.php') {
			$file = 'index.html';
		}

		if (empty($user['doc_root']) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $file)
			|| !preg_match('/\.(?:html|png)$/iD', $file)) {
			header('HTTP/1.1 404 Not Found');
			exit('report not found');
		}

		$link_dir = rtrim($user['doc_root'], '/\\') . '/webalizer';
		$report_dir = is_link($link_dir) ? false : realpath($link_dir);
		$report_file = $report_dir === false ? false : realpath($report_dir . DIRECTORY_SEPARATOR . $file);
		if ($report_file === false || dirname($report_file) !== $report_dir || !is_file($report_file)) {
			header('HTTP/1.1 404 Not Found');
			header('Content-Type: text/html; charset=utf-8');
			exit('<!doctype html><meta charset="utf-8"><title>日志分析</title><p>暂无日志分析数据，请等待日志翻转后再查看。</p>');
		}

		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, no-cache, must-revalidate');
		header('Content-Length: ' . filesize($report_file));
		if (strtolower(pathinfo($report_file, PATHINFO_EXTENSION)) === 'png') {
			header('Content-Type: image/png');
		} else {
			header('Content-Type: text/html; charset=utf-8');
		}
		readfile($report_file);
		exit();
	}
}
