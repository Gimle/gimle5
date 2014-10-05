<?php
namespace gimle\router;

use const gimle\SITE_DIR;
use const gimle\BASE_PATH_KEY;

use gimle\Canvas;

class Router
{
	use \gimle\trick\Singelton;

	const R_GET = 1;
	const R_POST = 2;
	const R_PUT = 4;
	const R_PATCH = 8;
	const R_DELETE = 16;
	const R_COPY = 32;
	const R_HEAD = 64;
	const R_OPTIONS = 128;
	const R_LINK = 256;
	const R_UNLINK = 512;
	const R_PURGE = 1024;

	const E_ROUTE_NOT_FOUND = 1;
	const E_METHOD_NOT_FOUND = 2;
	const E_CANVAS_NOT_FOUND = 4;
	const E_TEMPALTE_NOT_FOUND = 8;
	const E_FALLBACK_NOT_FOUND = 16;

	private $requestMethod;

	private $canvas = false;
	private $parseCanvas = true;
	private $template = false;
	private $routes = array();
	private $fallback = false;

	private $paramsHolder;
	private $error = 0;

	public function __construct ()
	{
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET':
				$this->requestMethod = self::R_GET;
				break;
			case 'POST':
				$this->requestMethod = self::R_POST;
				break;
			case 'PUT':
				$this->requestMethod = self::R_PUT;
				break;
			case 'PATCH':
				$this->requestMethod = self::R_PATCH;
				break;
			case 'DELETE':
				$this->requestMethod = self::R_DELETE;
				break;
			case 'COPY':
				$this->requestMethod = self::R_COPY;
				break;
			case 'HEAD':
				$this->requestMethod = self::R_HEAD;
				break;
			case 'OPTIONS':
				$this->requestMethod = self::R_OPTIONS;
				break;
			case 'LINK':
				$this->requestMethod = self::R_LINK;
				break;
			case 'UNLINK':
				$this->requestMethod = self::R_UNLINK;
				break;
			case 'PURGE':
				$this->requestMethod = self::R_PURGE;
				break;
			default:
				$this->requestMethod = 0;
		}
	}

	public function setCanvas ($name, $parse = true)
	{
		$this->canvas = $name;
		$this->parseCanvas = $parse;
	}

	public function setTemplate ($name)
	{
		$this->template = $name;
	}

	public function bindByRegex ($basePathKey, $path, $callback, $requestMethod = self::R_GET | self::R_POST | self::R_HEAD)
	{
		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {

			$this->routes[$path] = array(
				'callback' => $callback,
				'requestMethod' => $requestMethod,
			);
		}
	}

	public function fallback ($basePathKey, $callback)
	{
		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {
			$this->fallback = $callback;
		}
	}

	public function dispatch ()
	{
		$routeFound = false;
		$methodMatch = false;

		$url = Url::getInstance();

		foreach ($this->routes as $path => $route) {

			// Check if the current page matches the route.
			if (preg_match($path, $url->getString(), $matches)) {
				$url->setParts($matches);
				$routeFound = true;

				if ($this->requestMethod & $route['requestMethod']) {
					$route['callback']();
					$methodMatch = true;
					break;
				}
			}
		}

		if ($routeFound === false) {
			$this->error += self::E_ROUTE_NOT_FOUND;
		}
		if ($methodMatch === false) {
			$this->error += self::E_METHOD_NOT_FOUND;
		}

		if ($this->canvas !== false) {
			if (is_readable(SITE_DIR . 'canvas/' . $this->canvas . '.php')) {
				$this->canvas = SITE_DIR . 'canvas/' . $this->canvas . '.php';
			} else {
				$this->canvas = false;
				$this->error += self::E_CANVAS_NOT_FOUND;
			}
		}

		if ($this->template !== false) {
			if (is_readable(SITE_DIR . 'template/' . $this->template . '.php')) {
				$this->template = SITE_DIR . 'template/' . $this->template . '.php';
			} else {
				$this->template = false;
				$this->error += self::E_TEMPALTE_NOT_FOUND;
			}
		}

		if (($this->error & self::E_CANVAS_NOT_FOUND) || ($this->error & self::E_TEMPALTE_NOT_FOUND)) {
			throw new Exception('Router error', $this->error);
		}

		if ($this->canvas !== false) {
			if ($this->parseCanvas === true) {
				$params = Canvas::_set($this->canvas);
				if ($params !== false) {
					if ($this->template !== false) {
						$this->paramsHolder = $params;
						$return = include $this->template;
						if ($return !== true) {
							if (is_array($this->paramsHolder)) {
								$params = ArrayUtils::merge($this->paramsHolder, $return);
							} else {
								$params = $return;
							}
							$this->dispatchFallback($params);
						}
					} elseif ($this->error !== 0) {
						$this->dispatchFallback($params);
					}
				}
				Canvas::_create();
				return;
			}
			include $this->canvas;
		}
	}

	private function dispatchFallback ($params)
	{
		if ($this->fallback !== false) {
			call_user_func($this->fallback, $params, $this->error);
			return;
		}
		$this->error += self::E_FALLBACK_NOT_FOUND;
		throw new Exception('Router error', $this->error);
	}
}
