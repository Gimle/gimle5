<?php
namespace gimle\trick;

trait Multiton
{
	private static $instances = [];

	public static function getInstance ($identifier, ...$args)
	{
		if (!isset(self::$instances[$identifier])) {
			$me = get_called_class();

			self::$instances[$identifier] = new $me($identifier, ...$args);
		}

		return self::$instances[$identifier];
	}

	private function __construct ()
	{
	}
}
