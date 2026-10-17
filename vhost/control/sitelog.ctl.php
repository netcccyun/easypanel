<?php
needRole('vhost');

class SitelogControl extends Control
{
	private function site()
	{
		$user = daocall('vhost', 'getVhost', array(getRole('vhost')));
		return $user && !empty($user['log_file']) && $user['log_file'] !== '/nolog' ? $user : false;
	}

	public function index()
	{
		if (!$this->site()) {
			$this->_tpl->assign('msg', '站点未开启日志独立功能');
			return $this->_tpl->fetch('msg.html');
		}
		return $this->_tpl->fetch('sitelog/index.html');
	}

	public function read()
	{
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: private, no-store, no-cache, must-revalidate');
		header('X-Content-Type-Options: nosniff');
		$user = $this->site();
		if (!$user) {
			http_response_code(403);
			exit('站点未开启日志独立功能');
		}
		$root = realpath($user['doc_root']);
		if ($root === false) {
			http_response_code(404);
			exit('网站目录不存在');
		}
		$file = realpath(rtrim($root, '/\\') . '/logs/access.log');
		if ($file === false) { exit('暂无访问日志'); }
		// Resolve symlinks before reading, and never accept a path from the request.
		$prefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
		if (strncmp($file, $prefix, strlen($prefix)) !== 0 || !is_file($file)) {
			http_response_code(403);
			exit('无法读取网站日志');
		}
		$content = $this->tailFile($file, 500);
		if ($content === false) {
			http_response_code(500);
			exit('日志文件暂时无法读取，请刷新重试');
		}
		exit($content);
	}

	private function tailFile($file, $limit)
	{
		$fp = @fopen($file, 'rb');
		if (!$fp) { return false; }
		try {
			if (fseek($fp, 0, SEEK_END) !== 0) { return false; }
			$position = ftell($fp);
			if ($position === false) { return false; }
			$buffer = '';
			$newlines = 0;
			// Read backwards in blocks; large logs do not need a full-file scan.
			while ($position > 0 && $newlines <= $limit) {
				$size = min(8192, $position);
				$position -= $size;
				if (fseek($fp, $position, SEEK_SET) !== 0) { return false; }
				$chunk = fread($fp, $size);
				if ($chunk === false) { return false; }
				$buffer = $chunk . $buffer;
				$newlines += substr_count($chunk, "\n");
			}
			$lines = explode("\n", $buffer);
			if (end($lines) === '') { array_pop($lines); }
			return implode("\n", array_slice($lines, -$limit));
		} finally {
			fclose($fp);
		}
	}
}
