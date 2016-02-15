<?php
namespace gimle\rest;

use const gimle\CACHE_DIR;

use gimle\DiskIO;

use function gimle\d;
use function \simplexml_load_string;

class FetchCache extends Fetch
{
	const TEXT = 0;
	const XML = 1;
	const JSON_OBJECT = 2;
	const JSON_ARRAY = 3;
	const PHP_SERIALIZED = 4;
	const BINARY = 5;

	private $folder = CACHE_DIR;
	private $expect = self::TEXT;
	private $expire = false;

	public function folder ($folder)
	{
		if (($folder !== '') && (substr($folder, -1, 1) !== '/')) {
			$folder .= '/';
		}
		$this->folder = CACHE_DIR . DiskIO::safeDirectoryName($folder);

		return $this;
	}

	public function expect ($type)
	{
		$this->expect = $type;
		return $this;
	}

	public function expire ($search)
	{
		$this->expire = $search;
		return $this;
	}

	public function age ($url)
	{
		$filename = $this->cacheName($url);

		if (!file_exists($filename)) {
			return false;
		}
		return DiskIO::getModifiedAge($filename);
	}

	/*
	$ttl
		int = secs to live.
		false = never renew.
		null = do not cache at all.
	*/
	public function query ($url, $ttl = 600, $validationCallback = false)
	{
		$filename = $this->cacheName($url);

		$return = array();
		$validation = null;

		$doQuery = false;

		if ($ttl === null) {
			$doQuery = true;
		} elseif (!file_exists($filename)) {
			$doQuery = true;
		} elseif (($this->expect === self::XML) && ($this->expire !== false)) {
			$sxml = $this->loadXml(file_get_contents($filename));
			$expire = $sxml->xpath($this->expire);
			if (!empty($expire)) {
				$expire = (string)$expire[0];
				if (ctype_digit($expire)) {
					$expire = (int)$expire;
				} else {
					$expire = strtotime($expire);
				}

				if (($expire < time()) && ((is_int($ttl)) && (DiskIO::getModifiedAge($filename) > $ttl))) {
					$doQuery = true;
				}
			}
		} elseif ((is_int($ttl)) && (DiskIO::getModifiedAge($filename) > $ttl)) {
			$doQuery = true;
		}

		$validation = null;

		if ($doQuery === true) {
			$return = parent::query($url);

			$cacheIt = false;
			if ($this->expect === self::XML) {
				$sxmlNew = $this->loadXml($return['reply']);
				if ($sxmlNew !== false) {
					if ($validationCallback !== false) {
						$validation = false;
						$callback = $return;
						$callback['reply'] = $sxmlNew;
						$res = $validationCallback($callback);
						if ($res === true) {
							$validation = true;
							$cacheIt = true;
						} elseif (is_string($res)) {
							$sxmlNew = $this->loadXml($res);
							if ($sxmlNew !== false) {
								$validation = true;
								$return['reply'] = $res;
								$cacheIt = true;
							}
						} elseif ((is_object($res)) && ((is_subclass_of($res, 'SimpleXmlElement', false) === true) || (strtolower(get_class($res)) === 'simplexmlelement'))) {
							$sxmlNew = $res;
							$return['reply'] = $res->asXml();
							$validation = true;
							$cacheIt = true;
						}
						if ($validation !== true) {
							$sxmlNew = false;
							$return['reply'] = false;
						}
					} else {
						$cacheIt = true;
					}
				}
			} else {
				$cacheIt = true;
			}

			if (($ttl !== null) && ($cacheIt === true)) {
				if (!file_exists($this->folder)) {
					mkdir($this->folder, 0777, true);
				}
				file_put_contents($filename, $return['reply']);
			}
		}

		$cacheHit = false;

		if ($this->expect === self::XML) {
			if ((isset($sxmlNew)) && ($sxmlNew !== false)) {
				$return['reply'] = $sxmlNew;
			} elseif ((isset($sxml)) && ($sxml !== false)) {
				$return['reply'] = $sxml;
				$cacheHit = true;
			} else {
				$return['reply'] = false;
			}
		} else {
			$return['reply'] = file_get_contents($filename);;
			$cacheHit = true;
		}

		$return['cacheHit'] = $cacheHit;
		$return['validation'] = $validation;

		return $return;
	}

	private function cacheName ($url)
	{
		return $this->folder . DiskIO::safeFileName($url);
	}

	private function loadXml ($xmlstring)
	{
		$cond = libxml_use_internal_errors(true);
		$xml = simplexml_load_string((string)$xmlstring, '\gimle\xml\SimpleXmlElement');
		if ($cond !== true) {
			libxml_use_internal_errors($cond);
		}
		return $xml;
	}
}
