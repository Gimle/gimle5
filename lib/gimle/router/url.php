<?php
namespace gimle\router;

class Url
{
	use \gimle\trick\Singelton;

	private $path = array();
	private $parts = array();

	public function __construct ()
	{
		if ((isset($_SERVER['PATH_INFO'])) && (trim($_SERVER['PATH_INFO'], '/') !== '')) {
			$this->path = explode('/', trim($_SERVER['PATH_INFO'], '/'));
		}
	}

	public function getString ()
	{
		return implode('/', $this->path);
	}

	public function setParts ($parts)
	{
		foreach ($parts as $key => $value) {
			if (is_int($key)) {
				continue;
			}
			$this->parts[$key] = $value;
		}
	}

	public function getPart ($part)
	{
		if (isset($this->parts[$part])) {
			return $this->parts[$part];
		}
		return false;
	}
}
