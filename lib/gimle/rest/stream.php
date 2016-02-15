<?php
namespace gimle\rest;

class Stream
{
	private $option = array();
	private $header = array();

	private $post = false;
	private $file = array();

	public function reset ($full = false)
	{
		if ($full === true) {
			$this->option = array();
			$this->header = array();
		}

		$this->post = false;
		$this->file = array();
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
			$this->file[$key] = array('path' => $path, 'mimetype' => $mimetype, 'name' => $name);
		} else {
			trigger_error('Can not send file when raw data is sendt.');
		}
	}

	public function query ($endpoint)
	{
		$return = array();

		$options = array('http' => array('protocol_version' => 1.1));

		if ($this->post !== false) {
			$options['http']['method'] = 'POST';
			if (is_string($this->post)) {
				$options['http']['content'] = $this->post;
			} else {
				if (!empty($this->file)) {
					$boundary = '--------------------------' . microtime(true);
					// $this->header['Expect'] = '100-continue';
					$this->header['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
					$options['http']['content'] = '';
					if ($this->post !== false) {
						foreach ($this->post as $key => $value) {
							$options['http']['content'] .= "--$boundary\r\n" .
							"Content-Disposition: form-data; name=\"$key\"\r\n\r\n" .
							"$value\r\n";
						}
					}
					foreach ($this->file as $key => $value) {
						$options['http']['content'] .= "--$boundary\r\n" .
						"Content-Disposition: form-data; name=\"$key\"; filename=\"{$value['name']}\"\r\n" .
						"Content-Type: {$value['mimetype']}\r\n\r\n" .
						file_get_contents($value['path']) . "\r\n";
					}
					$options['http']['content'] .= "--" . $boundary . "--\r\n";
				} elseif ($this->post !== false) {
					$this->header['Content-Type'] = 'application/x-www-form-urlencoded';
					$options['http']['content'] = http_build_query($this->post);
				}
			}
		}

		if (!empty($this->header)) {
			$options['http']['header'] = implode("\r\n", array_map(function ($v, $k) {
				return $k . ': ' . $v;
			}, $this->header, array_keys($this->header)));
		}
		foreach ($this->option as $key => $value) {
			if (($key === 'followRedirect') && ($value === false)) {
				$options['http']['follow_location'] = 0;
			}
		}
		if ((isset($this->option['connectionTimeout'])) || (isset($this->option['resultTimeout']))) {
			$options['http']['timeout'] = 0;
			if (isset($this->option['connectionTimeout'])) {
				$options['http']['timeout'] += $this->option['connectionTimeout'];
			}
			if (isset($this->option['resultTimeout'])) {
				$options['http']['timeout'] += $this->option['resultTimeout'];
			}
		}

		$this->wrapper = stream_context_create($options);

		if ($stream = @fopen($endpoint, 'r', false, $this->wrapper)) {
			$metadata = stream_get_meta_data($stream);
			$return['reply'] = stream_get_contents($stream);
			fclose($stream);
			$return['header'] = $metadata['wrapper_data'];
			unset($metadata['wrapper_data']);
			$return['info'] = $metadata;
			$return['error'] = 0;
		} else {
			$return['header'] = array();
			$return['reply'] = false;
			$return['info'] = array();
			$return['error'] = 7;
		}

		return $return;
	}
}
