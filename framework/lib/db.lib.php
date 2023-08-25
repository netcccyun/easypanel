<?php
function db_connectx($dsn)
{
	try {
		return new PDO($dsn);
	}
	catch (Exception $e) {
		return false;
	}
}

function db_connect($dsn = null)
{
	global $db_cfg;
	$dsn = $dsn ? $dsn : $db_cfg['default']['dsn'];
	$dlink = db_connectx($dsn);

	if (!$dlink) {
		exit('无法连接数据库,请联系管理员000!');
	}

	return $dlink;
}

function db_query(PDO $db, $sql, $ret_type = 'result')
{
	if ($ret_type == 'result') {
		$result = $db->exec($sql);
	}
	else {
		$result = $db->query($sql);
	}

	if (!$result && $db->errorCode() != '00000') {
		return false;
	}

	switch ($ret_type) {
	case 'result':
		return $result;
	case 'row':
		if ($result) {
			return $result->fetch(PDO::FETCH_ASSOC);
		}

		return false;
	case 'rows':
		if ($result) {
			return $result->fetchAll(PDO::FETCH_ASSOC);
		}

		return false;
	default:
		trigger_error('db_query' . ' unknowd query type');
	}

	return false;
}

function db_pages($sql, $page = 1, $page_size = 10, $total = null)
{
	$retval = array(
		'total' => 0,
		'pages' => 1,
		'page'  => $page,
		'rows'  => array()
		);
	$dbenv = &db_get_env();
	$db = &$dbenv['dbconf'][$dbenv['host']];

	if (preg_match('/^([\\s]*SELECT[\\s]+)([\\s\\S]+)([\\s]+FROM[\\s]+[\\s\\S]+[\\s]+WHERE[\\s]+[\\s\\S]+[\\s]*)$/i', $sql, $matches)) {
		$parsesql = array();
		$parsesql['cmd'] = 'SELECT';
		$parsesql['begin'] = $matches[1];
		$parsesql['body'] = $matches[3];
		$parsesql['end'] = '';
	}
	else {
		trigger_error('SQL语法解析失败<br/>SQL: ' . $sql . '<br/>');
		return false;
	}

	if (is_null($total) || !is_numeric($total)) {
		$tmp_body = preg_replace('/[\\s]+ORDER[\\s]+BY[\\s\\S]+$/i', '', $parsesql['body']);
		$sql2 = $parsesql['begin'] . 'COUNT(*)' . $tmp_body . $parsesql['end'];
		$ret = db_query($sql2, 'value');
		if ($ret === false && is_numeric($ret)) {
			trigger_error('记录总数统计失败<br/>SQL: ' . $sql . '<br/>');
			return false;
		}

		$retval['total'] = $ret;
	}
	else {
		$retval['total'] = $total;
	}

	$retval['pages'] = ceil($retval['total'] / $page_size);

	if (0 < $retval['total']) {
		$sql2 = $sql . ' LIMIT ' . ($retval['page'] - 1) * $page_size . ', ' . $page_size;
		$retval['rows'] = db_query($sql2, 'rows');
		unset($sql);
		unset($sql2);
	}

	return $retval;
}

function db_debug()
{
}

function db_escape($text)
{
	$text = str_replace('&', '&amp;', $text);
	$text = str_replace('<', '&lt;', $text);
	$text = str_replace('>', '&gt;', $text);
	$text = str_replace('\'', '&apos;', $text);
	$text = str_replace('"', '&quot;', $text);
}

function db_close($host = null)
{
}

function db_closeall()
{
}

function db_closeHostServer($host)
{
}

define('DB_DISCONNECT', 0);
define('DB_CONNECT', 1);
define('DB_ERROR', 2);
define('DBPRE', '');

?>