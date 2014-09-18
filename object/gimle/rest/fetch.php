<?php
namespace gimle\rest;

class Fetch
{
	private $wrapper;

	public function __construct ()
	{
		if (function_exists('curl_init')) {
			$this->wrapper = new Curl();
		} else {
			$this->wrapper = new Stream();
		}

		$this->reset(true);
	}

	public function reset ($full = false)
	{
		$this->wrapper->reset($full);

		if ($full === true) {
			$this->wrapper->option('connectionTimeout', 1);
			$this->wrapper->option('resultTimeout', 1);
			$this->wrapper->option('followRedirect', true);

			$this->wrapper->header('Connection', 'close');
			$this->wrapper->header('Accept', '*/*');
		}
		return $this;
	}

	public function connectionTimeout ($float)
	{
		$this->wrapper->option('connectionTimeout', $float);
		return $this;
	}

	public function resultTimeout ($float)
	{
		$this->wrapper->option('resultTimeout', $float);
		return $this;
	}

	public function post ($key, $data = false)
	{
		$this->wrapper->post($key, $data);
		return $this;
	}

	public function file ($key, $data, $name = false)
	{
		$this->wrapper->file($key, $data, $name);
		return $this;
	}

	public function header ($key, $value)
	{
		$this->wrapper->header($key, $value);
		return $this;
	}

	public function followRedirect ($bool = true)
	{
		$this->wrapper->followRedirect($bool);
		return $this;
	}

	public function query ($endpoint)
	{
		$return = $this->wrapper->query($endpoint);

		foreach ($return['header'] as $header) {
			if (substr($header, 0, 7) === 'HTTP/1.') {
				$return['code'][] = (int)substr($header, 9, 3);
			}
		}

		return $return;
	}
}
