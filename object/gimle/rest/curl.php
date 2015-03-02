<?php
namespace gimle\rest;

class Curl
{
	private $option = array();
	private $header = array();

	private $post = false;

	public function reset ($full = false)
	{
		if ($full === true) {
			$this->option = array();
			$this->header = array();
		}

		$this->post = false;
	}

	public function option ($key, $value)
	{
		$this->option[$key] = $value;
	}

	public function header ($key, $value)
	{
		$this->header[$key] = $value;
	}

	public function post ($key, $data = false)
	{
		if ($data === false) {
			if (!is_array($this->post)) {
				if (!isset($this->header['Content-Type'])) {
					$this->header['Content-Type'] = 'text/plain';
				}
				$this->post = $key;
			} else {
				trigger_error('Can not send raw data when post data is sendt.');
			}
		} elseif (!is_string($this->post)) {
			$this->post[$key] = $data;
		} else {
			trigger_error('Can not send post data when raw data is sendt.');
		}
	}

	public function file ($key, $path, $name = false)
	{
		if (($this->post === false) || (is_array($this->post))) {
			if ($name === false) {
				$name = basename($path);
			}
			$finfo = finfo_open(FILEINFO_MIME_TYPE | FILEINFO_MIME_ENCODING);
			$mimetype = finfo_file($finfo, $path);
			$this->post[$key] = new \CurlFile($path, $mimetype, $name);
		} else {
			trigger_error('Can not send file when raw data is sendt.');
		}
	}

	public function query ($endpoint, $method = false)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if ($this->post !== false) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if (!isset($this->header['Expect'])) {
				$this->header['Expect'] = '';
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->post);
		}
		if (!empty($this->header)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($v, $k) {
				return $k . ': ' . $v;
			}, $this->header, array_keys($this->header)));
		}

		foreach ($this->option as $key => $value) {
			if ($key === 'encoding') {
				curl_setopt($ch, CURLOPT_ENCODING, $value);
			} elseif (($key === 'followRedirect') && ($value === true)) {
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			} elseif ($key === 'connectionTimeout') {
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, (int)($value * 1000));
			} elseif ($key === 'resultTimeout') {
				curl_setopt($ch, CURLOPT_TIMEOUT_MS, (int)($value * 1000));
			}
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

		if ($method !== false) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		$result = curl_exec($ch);

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		$return['reply'] = substr($result, $header_size);
		$return['header'] = array_values(array_diff(explode("\r\n", substr($result, 0, $header_size)), array('')));
		$return['info'] = curl_getinfo($ch);
		$return['error'] = curl_errno($ch);

		curl_close($ch);

		return $return;
	}
}
