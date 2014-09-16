<?php
namespace gimle;

class Config
{
	private static $config = array();

	public static function set ($key, $value)
	{
		if (!self::exists($key)) {
			$set = ArrayUtils::stringToNestedArray($key, $value);
			self::$config = ArrayUtils::merge(self::$config, $set);
		}
		return false;
	}

	public static function setAll ($config)
	{
		self::$config = $config;
	}

	public static function getAll ()
	{
		return self::$config;
	}

	public static function exists ($key)
	{
		$params = explode('.', $key);
		$check = self::$config;
		foreach ($params as $param) {
			if (isset($check[$param])) {
				$check = $check[$param];
			} else {
				return false;
			}
		}
		return true;
	}

	public static function get ($key)
	{
		$params = explode('.', $key);
		$return = self::$config;
		foreach ($params as $param) {
			if (isset($return[$param])) {
				$return = $return[$param];
			} else {
				return null;
			}
		}
		return $return;
	}
}
