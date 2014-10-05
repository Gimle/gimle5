<?php
namespace gimle\trick;

trait Singelton
{
	private static $instance = false;

	public static function getInstance (...$args)
	{
		if (self::$instance === false) {
			$me = get_called_class();

			self::$instance = new $me(...$args);
		}

		return self::$instance;
	}

	private function __construct ()
	{
	}
}
