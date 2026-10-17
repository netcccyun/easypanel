<?php
/** Shared handling for Kangle's directory;certificate|key host bindings. */
class EpSsl
{
	public static function splitBinding($value)
	{
		$parts = explode(';', (string) $value, 2);
		$files = isset($parts[1]) ? explode('|', $parts[1], 2) : array();
		return array(
			'dir' => $parts[0],
			'certificate' => isset($files[0]) ? $files[0] : '',
			'key' => isset($files[1]) ? $files[1] : '',
			'ssl' => count($files) === 2 && $files[0] !== '' && $files[1] !== ''
		);
	}

	public static function readFile($root, $name)
	{
		if ($name === '' || strpos($name, "\0") !== false) {
			return '';
		}
		$file = rtrim($root, '/\\') . '/' . $name;
		if (is_link($file) || !is_file($file)) {
			return '';
		}
		$content = @file_get_contents($file, false, null, 0, 1024000);
		return $content === false ? '' : $content;
	}

	/** Stage both PEM files before replacing either active file. */
	public static function writePair($root, $certName, $keyName, $certificate, $key)
	{
		$root = rtrim($root, '/\\');
		$names = array($certName, $keyName);
		$contents = array($certificate, $key);
		$temps = array();
		$previous = array();
		foreach ($names as $i => $name) {
			$file = $root . '/' . $name;
			if ($name === '' || basename($name) !== $name || strpos($name, '\\') !== false || is_link($file)) {
				self::removeTemps($temps);
				return false;
			}
			$previous[$i] = is_file($file) ? @file_get_contents($file) : false;
			if (is_file($file) && $previous[$i] === false) {
				self::removeTemps($temps);
				return false;
			}
			$temp = @tempnam($root, '.ep-ssl-');
			if ($temp === false) {
				self::removeTemps($temps);
				return false;
			}
			$temps[$i] = $temp;
			if (!@chmod($temp, 0600) || @file_put_contents($temp, $contents[$i]) !== strlen($contents[$i])) {
				self::removeTemps($temps);
				return false;
			}
		}
		foreach ($names as $i => $name) {
			if (!@rename($temps[$i], $root . '/' . $name)) {
				// Restore a replaced certificate if installing the key failed.
				for ($j = 0; $j < $i; $j++) {
					if ($previous[$j] === false) {
						@unlink($root . '/' . $names[$j]);
					} else {
						@file_put_contents($root . '/' . $names[$j], $previous[$j]);
					}
				}
				self::removeTemps($temps);
				return false;
			}
		}
		return true;
	}

	private static function removeTemps($temps)
	{
		foreach ($temps as $temp) {
			@unlink($temp);
		}
	}

	public static function certificateInfo($certificate, $now = null, $domain = null)
	{
		if ($certificate === '') {
			return false;
		}
		$info = @openssl_x509_parse($certificate);
		if (!$info) {
			return array('error' => '无法解析已配置的SSL证书，请检查证书内容');
		}
		$issuer = isset($info['issuer']) ? $info['issuer'] : array();
		$brand = isset($issuer['O']) ? $issuer['O'] : (isset($issuer['CN']) ? $issuer['CN'] : '未知颁发机构');
		if (is_array($brand)) {
			$brand = implode(' / ', $brand);
		}
		$domains = array();
		if (isset($info['extensions']['subjectAltName'])) {
			foreach (explode(',', $info['extensions']['subjectAltName']) as $entry) {
				if (preg_match('/^\s*(?:DNS|IP Address):\s*(.+)$/i', $entry, $match)) {
					$domains[] = trim($match[1]);
				}
			}
		}
		if (!$domains && isset($info['subject']['CN'])) {
			$domains = (array) $info['subject']['CN'];
		}
		$domainMismatch = $domain !== null;
		if ($domain !== null) {
			foreach ($domains as $certificateDomain) {
				if (self::domainMatches($certificateDomain, $domain)) {
					$domainMismatch = false;
					break;
				}
			}
		}
		$expires = isset($info['validTo_time_t']) ? $info['validTo_time_t'] : 0;
		$starts = isset($info['validFrom_time_t']) ? $info['validFrom_time_t'] : 0;
		$now = $now === null ? time() : $now;
		return array(
			'error' => '',
			'brand' => $brand,
			'domains' => $domains ? implode('、', array_unique($domains)) : '未知',
			'domain_mismatch' => $domainMismatch,
			'expires' => $expires ? date('Y-m-d H:i:s', $expires) : '未知',
			'expired' => $expires > 0 && $expires <= $now,
			'not_yet_valid' => $starts > $now
		);
	}

	private static function domainMatches($certificateDomain, $domain)
	{
		$certificateDomain = self::normalizeDomain($certificateDomain);
		$domain = self::normalizeDomain($domain);
		if ($certificateDomain === '' || $domain === '') { return false; }
		if ($certificateDomain === $domain) { return true; }
		// A wildcard covers exactly one label, not the base domain or deeper
		// subdomains. A concrete certificate cannot cover a wildcard binding.
		if (substr($certificateDomain, 0, 2) !== '*.' || strpos($domain, '*') !== false) { return false; }
		$dot = strpos($domain, '.');
		return $dot !== false && substr($domain, $dot + 1) === substr($certificateDomain, 2);
	}

	private static function normalizeDomain($domain)
	{
		$domain = strtolower(rtrim(trim($domain), '.'));
		$wildcard = substr($domain, 0, 2) === '*.';
		$name = $wildcard ? substr($domain, 2) : $domain;
		if ($name !== '' && function_exists('idn_to_ascii')) {
			$ascii = defined('INTL_IDNA_VARIANT_UTS46')
				? @idn_to_ascii($name, 0, INTL_IDNA_VARIANT_UTS46) : @idn_to_ascii($name);
			if ($ascii !== false) { $name = strtolower($ascii); }
		}
		return ($wildcard ? '*.' : '') . $name;
	}
}
