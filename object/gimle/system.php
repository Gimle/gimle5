<?php
namespace gimle;

class System
{
	private static $autoload = array(array('path' => SITE_DIR . 'module/gimle5/object/', 'toLowercase' => true, 'init' => false));

	public static function autoloadRegister ($path, $toLowercase = true, $initFunction = false)
	{
		self::$autoload[] = array('path' => $path, 'toLowercase' => $toLowercase, 'init' => $initFunction);
	}

	/**
	 * Autoload.
	 *
	 * @param string $name
	 * @return void
	 */
	public static function autoload ($name)
	{
		foreach (static::$autoload as $autoload) {
			$file = $autoload['path'];
			if ($autoload['toLowercase'] === true) {
				$file .= str_replace('\\', '/', strtolower($name)) . '.php';
			} else {
				$file .= str_replace('\\', '/', $name) . '.php';
			}
			if (is_readable($file)) {
				include $file;
				if (($autoload['init'] !== false) && (method_exists($name, $autoload['init']))) {
					call_user_func(array($name, $autoload['init']));
				}
				break;
			}
		}
	}

	/**
	 * Parse a ini or php config file and keep typecasting.
	 *
	 * For ini files, this is similar to the parse_ini_file function, but keeps typecasting and require "" around strings.
	 * For php files this function will look for a variable called $config, and return it.
	 *
	 * @param string $filename the full path to the file to parse.
	 * @return mixed array or false. Array with the read configuration file, or false upon failure.
	 */
	public static function parseConfigFile ($filename)
	{
		if (!is_readable($filename)) {
			return false;
		}

		$return = array();
		$lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return false;
		}
		if (!empty($lines)) {
			foreach ($lines as $linenum => $linestr) {
				if (substr($linestr, 0, 1) === ';') {
					continue;
				}
				$line = explode(' = ', $linestr);
				$key = trim($line[0]);
				if ((isset($line[1])) && (substr($key, 0, 1) !== '[')) {
					if (isset($value)) {
						unset($value);
					}
					if ((substr($line[1], 0, 1) === '"') && (substr($line[1], -1, 1) === '"')) {
						$value = str_replace(array('\\"', '\\\\'), array('"', '\\'), substr($line[1], 1, -1));
					} elseif ((ctype_digit($line[1])) || ((substr($line[1], 0, 1) === '-') && (ctype_digit(substr($line[1], 1))))) {
						$num = $line[1];
						if (substr($num, 0, 1) === '-') {
							$num = substr($line[1], 1);
						}
						if (substr($num, 0, 1) === '0') {
							if (substr($line[1], 0, 1) === '-') {
								$value = -octdec($line[1]);
							} else {
								$value = octdec($line[1]);
							}
						} else {
							$value = (int)$line[1];
						}
						unset($num);
					} elseif ($line[1] === 'true') {
						$value = true;
					} elseif ($line[1] === 'false') {
						$value = false;
					} elseif ($line[1] === 'null') {
						$value = null;
					} elseif (preg_match('/^0[xX][0-9a-fA-F]+$/', $line[1])) {
						$value = hexdec(substr($line[1], 2));
					} elseif (preg_match('/^\-0[xX][0-9a-fA-F]+$/', $line[1])) {
						$value = -hexdec(substr($line[1], 3));
					} elseif (preg_match('/^0b[01]+$/', $line[1])) {
						$value = bindec(substr($line[1], 2));
					} elseif (preg_match('/^\-0b[01]+$/', $line[1])) {
						$value = -bindec(substr($line[1], 3));
					} elseif (filter_var($line[1], FILTER_VALIDATE_FLOAT) !== false) {
						$value = (float)$line[1];
					} elseif (defined($line[1])) {
						$value = constant($line[1]);
					} elseif (defined(__NAMESPACE__ . '\\' . $line[1])) {
						$value = constant(__NAMESPACE__ . '\\' . $line[1]);
					} else {
						throw new \Exception('Unknown value in ini file on line ' . ($linenum + 1) . ': ' . $linestr);
					}
					if (isset($value)) {
						if (!isset($lastkey)) {
							$return[$key] = $value;
						} else {
							$return = ArrayUtils::merge($return, ArrayUtils::stringToNestedArray($lastkey, array($key => $value)));
						}
					}
				} else {
					$lastkey = substr($key, 1, -1);
				}
			}
		}
		return $return;
	}
}
