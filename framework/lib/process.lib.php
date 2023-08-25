<?php

class Process
{
	/**
	 * 同步运行命令,此函数设计非常安全，抛弃php的exec的安全隐患，也可以指定运行身份。
	 * windows需要借助runas.exe(vhsnode中的项目)，辅助实现。
	 * @param $cmds 命令组，每个命令一个array,包含各个参数，个命令之间，用管道串接
	 * @param $vh 运行的虚拟主机身份，可以为array,也可以为string指令名字
	 * @param $pipes 输入输出管道
	 * @param $stdin_file 标准输入文件名
	 * @param $stdout_file 标准输出文件名,以+开头的，为追加方式打开
	 * @param $stderr_file 错误输出文件名,以+开头的，为追加方式打开
	 * @return 返回proc_open的句柄，注意要用proc_close关闭。
	 */
	public function run(array $cmds, $vh, &$pipes, $stdin_file = null, $stdout_file = null, $stderr_file = null)
	{
		if ($vh && !is_array($vh)) {
			$vh = daocall("vhost", "getVhost", array($vh, array("uid", "gid")));
			if (!$vh) {
				trigger_error("cann't getVhost for " . $vh);
				return false;
			}
		}
		change_to_super();
		if (is_win()) {
			return $this->winrun($cmds, $vh, $pipes, $stdin_file, $stdout_file, $stderr_file);
		}
		return $this->unixrun($cmds, $vh, $pipes, $stdin_file, $stdout_file, $stderr_file);
	}

	/**
	 * 同步处理run的结果
	 * @param $rs     run的返回值
	 * @param $pipes  run的pipes
	 * @param $stdin  标准输入内容
	 * @param $stdout 输出内容
	 * @return 返回code
	 */
	public function result($rs, &$pipes, $stdin = null, $stdout = "-", $stderr = "-", &$err)
	{
		if (is_resource($rs)) {
			if ($stdin && is_resource($pipes[0])) {
				if (0 < strlen($stdin)) {
					fwrite($pipes[0], $stdin);
				}
				fclose($pipes[0]);
			}
			if ($stdout && is_resource($pipes[1])) {
				if ($stdout_file == "-") {
					while (true) {
						$msg = fread($pipes[1]);
						if ($msg === FALSE || strlen($msg) == 0) {
							break;
						}
						echo $msg;
					}
				} else {
					$stdout = stream_get_contents($pipes[1]);
				}
				fclose($pipes[1]);
			}
			if ($stderr && is_resource($pipes[2])) {
				if ($stderr == "-") {
					while (true) {
						$msg = fread($pipes[2]);
						if ($msg === FALSE || strlen($msg) == 0) {
							break;
						}
						echo $msg;
					}
				} else {
					$err = $stderr = stream_get_contents($pipes[2]);
				}
				fclose($pipes[2]);
			}
			$status = proc_get_status($rs);
			$code = $status["exitcode"];
			proc_close($rs);
		} else {
			$code = 1;
			if ($stderr) {
				$stderr = "rs is not resource";
			}
		}
		return $code;
	}

	public function runtest()
	{
		$test_cmd = "D:\\project\\vhsnode\\Debug\\testcmd.exe";
		if (!is_win()) {
			$test_cmd = "/home/keengo/test/php/testcmd";
		}
		$test_args = "`~!@#\$%^&*()-_=+\\|[{]};:'\",<.>/?";
		$command = array($test_cmd);
		$i = 0;
		while ($i < strlen($test_args)) {
			$command[] = substr($test_args, $i, 1);
			++$i;
		}
		$rs = $this->run(array(array($test_cmd)), null, $pipes, "c:\\a.txt");
		print_r($pipes);
		echo "time=" . time() . "\n";
		$this->handle($rs, $pipes, "test", $stdout, $stderr);
		echo "time=" . time() . " stdout=" . $stdout . " stderr=" . $stderr . "\n";
	}

	private function winrun(array $cmds, $vh, &$pipes, $stdin_file = null, $stdout_file = null, $stderr_file = null)
	{
		$command = "";
		$env = $_SERVER;
		if ($vh) {
			$env["__KRUNAS_USER"] = $vh["uid"];
			$env["__KRUNAS_GROUP"] = $vh["gid"];
		}
		$descriptorspec = array();
		if ($stdin_file) {
			$env["__KRUNAS_STDIN"] = $stdin_file;
		} else {
			$descriptorspec[0] = array("pipe", "r");
		}
		if ($stdout_file) {
			$env["__KRUNAS_STDOUT"] = $stdout_file;
		} else {
			$descriptorspec[1] = array("pipe", "w");
		}
		if ($stderr_file) {
			$env["__KRUNAS_STDERR"] = $stderr_file;
		} else {
			$descriptorspec[2] = array("pipe", "w");
		}
		$env["__KRUNAS_CMD_COUNT"] = count($cmds);
		$index = 0;
		foreach ($cmds as $cmd) {
			$c = "";
			foreach ($cmd as $arg) {
				if ($c) {
					$c .= " ";
				}
				$c .= $this->escapeWindowsCommand($arg);
			}
			$env_name = "__KRUNAS_CMD_" . $index++;
			$env[$env_name] = $c;
		}
		$command = dirname($GLOBALS["safe_dir"]) . "/bin/runas.exe";
		return proc_open($command, $descriptorspec, $pipes, null, $env, array("bypass_shell" => true));
	}

	private function unixrun(array $cmds, $vh, &$pipes, $stdin_file = null, $stdout_file = null, $stderr_file = null)
	{
		if ($vh) {
			change_to_user($vh["uid"], $vh["gid"]);
		}
		$command = "";
		foreach ($cmds as $cmd) {
			$c = "";
			foreach ($cmd as $arg) {
				if ($c) {
					$c .= " " . escapeshellarg($arg);
				} else {
					$c = escapeshellcmd($arg);
				}
			}
			if ($command) {
				$command .= " | ";
			}
			$command .= $c;
		}
		if ($stdin_file) {
			$stdin_ds = array("file", $stdin_file, "r");
		} else {
			$stdin_ds = array("pipe", "r");
		}
		if ($stdout_file) {
			if ($stdout_file[0] == "+") {
				$stdout_file = substr($stdout_file, 1);
				$stdout_ds = array("file", $stdout_file, "a");
			} else {
				$stdout_ds = array("file", $stdout_file, "w");
			}
		} else {
			$stdout_ds = array("pipe", "w");
		}
		if ($stderr_file) {
			if ($stderr_file[0] == "+") {
				$stderr_file = substr($stderr_file, 1);
				$stderr_ds = array("file", $stderr_file, "a");
			} else {
				$stderr_ds = array("file", $stderr_file, "w");
			}
		} else {
			$stderr_ds = array("pipe", "w");
		}
		$descriptorspec = array($stdin_ds, $stdout_ds, $stderr_ds);
		$rs = proc_open($command, $descriptorspec, $pipes);
		if ($vh) {
			change_to_super();
		}
		return $rs;
	}
	
	private function escapeWindowsCommand($str)
	{
		$msg = "\"";
		$len = strlen($str);
		$slash_count = 0;
		$char = 0;
		$i = 0;
		while ($i < $len) {
			$char = $str[$i];
			if ($char == "\\") {
				++$slash_count;
			} else {
				if ($char == "\"") {
					$j = 0;
					while ($j < $slash_count) {
						$msg .= "\\";
						++$j;
					}
					$msg .= "\\";
				} else {
					if ($char == "<" || $char == ">") {
					}
				}
				$slash_count = 0;
			}
			$msg .= $char;
			++$i;
		}
		if ($char == "\\") {
			$j = 0;
			while ($j < $slash_count) {
				$msg .= "\\";
				++$j;
			}
		}
		$msg .= "\"";
		return $msg;
	}
}