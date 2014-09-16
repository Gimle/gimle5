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
	public static function autoload ($name) {
		foreach (static::$autoload as $autoload) {
			$file = $autoload['path'];
			if ($autoload['toLowercase'] === true) {
				$file .= str_replace('\\', '/', strtolower($name)) . '.php';
			} else {
				$file .= str_replace('\\', '/', $name) . '.php';
			}
			if (file_exists($file)) {
				include $file;
				if (($autoload['init'] !== false) && (method_exists($name, $autoload['init']))) {
					call_user_func(array($name, $autoload['init']));
				}
				break;
			}
		}
	}
}
