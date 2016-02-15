<?php
namespace gimle;

/*
[DiskIO]
replaceChar = "★"
safeName[] = "/[\/*;]"
safeName[] = "/^[-]/"
*/

class DiskIO
{
	const NONE = 0;
	const TEMP = 1;
	const CACHE = 2;

	public static function safeFileName ($name)
	{
		$name = self::safeDirectoryName($name);
		return str_replace(['\\', '/'], Config::get('DiskIO.replaceChar'), $name);
	}

	public static function safeDirectoryName ($name)
	{
		Config::set('DiskIO.replaceChar', '★');
		Config::set('DiskIO.safeName', ['/[*;]/', '/^[-]/']);

		$regexs = Config::get('DiskIO.safeName');
		foreach ($regexs as $regex) {
			$name = preg_replace($regex, Config::get('DiskIO.replaceChar'), $name);
		}
		return $name;
	}

	/**
	 * Converts a config file formatted filesize string to bytes.
	 *
	 * @param string $size
	 * @return int Number of bytes.
	 */
	public static function stringToBytes ($size)
	{
		$size = trim($size);
		$last = strtolower(substr($size, -1));
		$size = (int)$size;
		switch ($last) {
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}
		return $size;
	}

	/**
	 * Convert bytes to readable number.
	 *
	 * @param int $filesize Number of bytes.
	 * @param int $decimals optional Number of decimals to include in string.
	 * @return array containing prefix, float value and readable string.
	 */
	public static function bytesToArray ($filesize = 0, $decimals = 2)
	{
		$return = [];
		$count = 0;
		$units = ['', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
		while ((($filesize / 1024) >= 1) && ($count < (count($units) - 1))) {
			$filesize = $filesize / 1024;
			$count++;
		}
		if (round($filesize, $decimals) === (float) 1024) {
			$filesize = $filesize / 1024;
			$count++;
		}
		$return['units']  = $units[$count];
		$return['value']  = (float) $filesize;
		$return['string1'] = round($filesize, $decimals) . (($count > 0) ? ' ' . $units[$count] : '');
		$return['string2'] = round($filesize, $decimals) . ' ' . $units[$count] . 'B';
		return $return;
	}

	public static function getModifiedAge ($name, $type = self::NONE)
	{
		if (file_exists($name)) {
			return (time() - filemtime($name));
		}
		return false;
	}

	public static function getMimetype ($name)
	{
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$return = finfo_file($finfo, $name);
		finfo_close($finfo);

		if ($return === 'application/octet-stream') {
			$size = filesize($name);
			if ($size > 12) {
				$fp = fopen($name, 'r');
				$check1 = fread($fp, 4);
				fseek($fp, 8);
				$check2 = fread($fp, 4);

				fclose($fp);

				if (($check1 === 'RIFF') && (($check2 === 'WEBP'))) {
					$return = 'image/webp';
				}
			}
		}

		return $return;
	}

	public static function createFile ($name = false, $prefix = false, $suffix = false, $type = self::NONE)
	{
		if ($name === false) {
			$name = self::randomName();
		}

		if ($type === self::NONE) {
			$dir = '';
		} elseif ($type === self::TEMP) {
			$dir = TEMP_DIR;
		} elseif ($type === self::CACHE) {
			$dir = CACHE_DIR;
		}

		if ($prefix !== false) {
			$name = $prefix . $name;
		}
		if ($suffix !== false) {
			$name .= $suffix;
		}
		if (!file_exists(dirname($dir . $name))) {
			if (!is_writeable()) {
				return false;
			}
			mkdir($dir . $name, 0777, true);
		}
		if (!file_exists($dir . $name)) {
			if (!is_writeable()) {
				return false;
			}
			touch($dir . $name);
			return $dir . $name;
		}
		return self::createFile($name, $prefix, $suffix, $type);
	}

	public static function createDirectory ($name = false, $prefix = false, $suffix = false, $type = self::ROOT)
	{
	}

	public static function randomName ($length = 8)
	{
		$var = '0123456789abcdefghijklmnopqrstuvwxyz';
		$len = strlen($var);
		$return = '';
		for ($i = 0; $i < $length; $i++) {
			$return .= $var[rand(0, $len - 1)];
		}
		return $return;
	}

	public static function setAttr ($name, $value, $ns = false)
	{
		if (!file_exists($name)) {
			return false;
		}

		$exec = 'setfattr -n user.gimle -v ' . escapeshellarg(str_replace('\\', '\\\\', $value)) . ' ' . escapeshellarg($name);

		exec($exec, $res);
	}

	public static function removeAttr ($name, $ns = false)
	{
		if (!file_exists($name)) {
			return false;
		}

		$exec = 'setfattr -x user.gimle ' . escapeshellarg($name);

		exec($exec, $res);
	}


	public static function getAttr ($name, $ns = false)
	{
		if (!file_exists($name)) {
			return false;
		}

		exec('getfattr -n user.gimle ' . escapeshellarg($name), $res);
		$keyLen = strlen('user.gimle="');
		if ((isset($res[1])) && (substr($res[1], 0, $keyLen) === 'user.gimle="') && (substr($res[1], -1, 1) === '"')) {
			$result = substr($res[1], $keyLen, -1);
			$result = str_replace(['\\\\\'', '\\"', '\\\\'], ['\'', '"', '\\'], $result);
			return $result;
		}
		return false;
	}

	public static function delete ($path)
	{
		if (is_dir($path)) {
			$objects = scandir($path);
			foreach ($objects as $object) {
				if (($object !== '.') && ($object !== '..')) {
					if (is_dir($path . '/' . $object)) {
						self::delete($path . '/'. $object);
					}
					else {
						unlink($path . '/' . $object);
					}
				}
			}
			rmdir($path);
		}
		else {
			unlink($path);
		}
	}
}
