<?php
/**
 * This file will import all constants and some functions from the gimle namespace to the root namespace;
 */

foreach (get_defined_constants(true)['user'] as $name => $value) {
	if (substr($name, 0, 6) === 'gimle\\') {
		$name = substr($name, 6);
		if (!defined($name)) {
			define($name, $value);
		}
	}
}

if (!function_exists('d')) {
	function d ($var, $return = false, $title = false, $background = false, $mode = 'auto')
	{
		if ($title === false) {
			$title = [
				'steps' => 1,
				'match' => '/d\((.*)/'
			];
		}
		return \gimle\var_dump($var, $return, $title, $background, $mode);
	}
}

if (!function_exists('page')) {
	function page ($part = false)
	{
		return \gimle\router\Router::getInstance()->page($part);
	}
}
