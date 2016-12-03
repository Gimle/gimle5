<?php
namespace gimle;

abstract class FileStreamBase
{
	protected $handle;
	protected $path;

	public function stream_open ($path, $mode, $options, &$opened_path)
	{
		$this->setPath($path);

		$this->handle = fopen($this->path, $mode);

		return $this->handle;
	}

	public function stream_read ($count)
	{
		return fread($this->handle, $count);
	}

	public function stream_write ($data)
	{
		return fwrite($this->handle, $data);
	}

	public function stream_eof ()
	{
		return feof($this->handle);
	}

	public function stream_close ()
	{
		fclose($this->handle);
	}

	public function stream_stat ()
	{
		return fstat($this->handle);
	}

	public function url_stat ($path, $flag)
	{
		$this->setPath($path);

		if (file_exists($this->path)) {
			return stat($this->path);
		}
		return false;
	}

	public function mkdir ($path, $mode, $options)
	{
		$this->setPath($path);
		$recursive = ($options & STREAM_MKDIR_RECURSIVE);
		return mkdir($this->path, $mode, $recursive);
	}

	public function rmdir ($path, $options)
	{
		$this->setPath($path);
		return rmdir($this->path);
	}

	public function dir_opendir ($path, $options)
	{
		$this->setPath($path);
		$this->handle = opendir($this->path);
		return $this->handle;
	}

	public function dir_readdir ()
	{
		return readdir($this->handle);
	}

	public function dir_closedir ()
	{
		throw new Exception('Not implemented, need test case.');
	}

	public function rename ($path_from, $path_to)
	{
		$this->setPath($path_from);
		$path_from = $this->path;
		$this->setPath($path_to);
		$path_to = $this->path;
		$this->path = null;
		return rename($path_from, $path_to);
	}

	public function stream_metadata ($path, $options, $value)
	{
		$this->setPath($path);

		if ($options === STREAM_META_TOUCH) {
			if (!isset($value[0])) {
				return touch($this->path);
			}
			elseif (!isset($value[1])) {
				return touch($this->path, $value[0]);
			}
			return touch($this->path, $value[0], $value[1]);
		}

		if ($options === STREAM_META_ACCESS) {
			return chmod($this->path, $value);
		}

		if (in_array($options, [STREAM_META_OWNER, STREAM_META_OWNER_NAME])) {
			return chown($this->path, $value);
		}

		if (in_array($options, [STREAM_META_GROUP, STREAM_META_GROUP_NAME])) {
			return chgrp($this->path, $value);
		}

		throw new Exception('Not implemented, need test case.');
	}

	protected function setPath ($path)
	{
		$scheme = substr($path, 0, strpos($path, ':'));
		$dir = substr($path, strlen($scheme . '://'));
		if (substr($dir, 0, 1) === '/') {
			throw new Exception('Path can not start with a slash.');
		}
		if (strpos($dir, '..') !== false) {
			throw new Exception('Path can not contain a double dot.');
		}
		$this->path = $this->base . $dir;
	}
}
