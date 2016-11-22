<?php
namespace
{
	/**
	 * This block loads some functions that is not included in older / any php versions.
	 *
	 */

	if (!function_exists('mb_ucfirst')) {
		/**
		 * Make a string's first character uppercase.
		 *
		 * @param string $string The input string.
		 * @return string The resulting string.
		 */
		function mb_ucfirst ($string)
		{
			$return = '';
			$fc = mb_strtoupper(mb_substr($string, 0, 1));
			$return .= $fc . mb_substr($string, 1, mb_strlen($string));
			return $return;
		}
	}

	if (!function_exists('mb_str_pad')) {
		/**
		 * Pad a string to a certain length with another string.
		 *
		 * If the value of $pad_length is negative, less than, or equal to the length of the input string, no padding takes place.
		 * The $pad_string may be truncated if the required number of padding characters can't be evenly divided by the pad_string's length.
		 *
		 * @param string $input The input string.
		 * @param int $pad_length Pad length.
		 * @param string $pad_string Pad string.
		 * @param constant $pad_type Can be STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH.
		 * @param string $encoding The character encoding to use.
		 * @return string The padded string.
		 */
		function mb_str_pad ($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = null)
		{
			$diff = strlen($input) - mb_strlen($input, $encoding);
			return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
		}
	}

	if (!function_exists('is_binary')) {
		/**
		 * Checks if the input is binary.
		 *
		 * @param string $value The input string.
		 * @return bool True if binary, otherwise false.
		 */
		function is_binary ($value)
		{
			$filename = tempnam(TEMP_DIR, 'tmp_');
			file_put_contents($filename, $value);
			exec('file -i ' . $filename, $match);
			unlink($filename);
			$len = strlen($filename . ': ');
			$desc = substr($match[0], $len);
			if (substr($desc, 0, 4) == 'text') {
				return false;
			}
			return true;
		}
	}
}

namespace gimle
{
	/**
	 * Get information about the current url.
	 *
	 * @param string $part The part you want returned. (Optional).
	 * @return mixed If no $part passed in, and array of available parts is returned.
	 *               If a valid part is part is passed in, that part is returned as a string.
	 *               If part was not found, false will be returned.
	 */
	function page ($part = false)
	{
		return router\Router::getInstance()->page($part);
	}

	/**
	 * Include a file and pass arguments to it.
	 *
	 * @param string $file The file to include.
	 * @param mixed ...$args One or more arguments..
	 * @return mixed The return value from the included file.
	 */
	function inc ($file, ...$args)
	{
		$GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh'][] = $args;
		$res = include $file;
		array_pop($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh']);
		if (empty($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh'])) {
			unset($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh']);
		}
		return $res;
	}

	/**
	 * Get arguments set when file was included.
	 *
	 * @return array The arguments passed in.
	 */
	function inc_get_args ()
	{
		if (isset($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh'])) {
			return end($GLOBALS['stUpiDlonGVarIabLeThatNoOneWilLRandMlyGueSsDotDoTSlaSh']);
		}
		return [];
	}

	/**
	 * Get the users preferred language.
	 *
	 * @param array $avail A list of the available languages.
	 * @return string or false if empty array passed in.
	 */
	function get_preferred_language (array $avail)
	{
		if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			return current($avail);
		}
		$accepts = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$result = [];
		foreach ($accepts as $accept) {
			$accept = explode(';q=', $accept);
			if (!isset($accept[1])) {
				$accept[1] = 1.0;
			}
			else {
				$accept[1] = (float) $accept[1];
			}
			$result[$accept[1] * 100][] = $accept[0];
		}
		krsort($result);
		foreach ($result as $values) {
			foreach ($values as $value) {
				if (in_array($value, $avail)) {
					return $value;
				}
				elseif (array_key_exists($value, $avail)) {
					return $avail[$value];
				}
			}
		}
		reset($avail);
		return current($avail);
	}
}
