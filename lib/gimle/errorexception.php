<?php
namespace gimle;

class ErrorException extends \ErrorException
{
	private $params;

	public function set ($key, $value)
	{
		$this->params = ArrayUtils::merge($this->params, ArrayUtils::stringToNestedArray($key, $value));
	}

	public function get ($key = false)
	{
		if ($key === false) {
			return $this->params;
		}

		if (isset($this->params[$key])) {
			return $this->params[$key];
		}

		return null;
	}
}
