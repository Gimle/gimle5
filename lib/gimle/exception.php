<?php
namespace gimle;

class Exception extends \Exception
{
	private $params;

	public function set ($key, $value)
	{
		$this->params = ArrayUtils::merge($this->params, ArrayUtils::stringToNestedArray($key, $value));
	}

	public function get ($key)
	{
	}
}
