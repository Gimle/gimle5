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

	const E_NONE = 0;
	const E_ROUTE_NOT_FOUND = 1;
	const E_METHOD_NOT_FOUND = 2;
	const E_CANVAS_NOT_FOUND = 4;
	const E_TEMPALTE_NOT_FOUND = 8;
	const E_FALLBACK_NOT_FOUND = 16;

	private $requestMethod;

	private $canvas = false;
	private $parseCanvas = true;
	private $template = false;
	private $routes = [];
	private $fallback = false;

	private $paramsHolder;
	private $error = self::E_NONE;

	private $url = [];
	private $urlString = '';

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

		if (isset($_SERVER['PATH_INFO'])) {
			$this->urlString = trim($_SERVER['PATH_INFO'], '/');
			$this->url = explode('/', $this->urlString);
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

	public function bindByRegex ($basePathKey, $path, $callback, $requestMethod = self::R_GET | self::R_HEAD)
	{
		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {

			$this->routes[$path] = [
				'callback' => $callback,
				'requestMethod' => $requestMethod,
			];
		}
	}


	public function bind ($basePathKey, $path, $callback = false, $conditions = [], $requestMethod = self::R_GET | self::R_HEAD)
	{
		if (!is_array($conditions)) {
			$requestMethod = $conditions;
			$conditions = [];
		}
		$path = $this->bindToRegex($path, $conditions);

		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {

			$this->routes[$path] = [
				'callback' => $callback,
				'requestMethod' => $requestMethod,
			];
		}
	}

	public function bindToRegex ($path, $conditions = [])
	{
		return '#^' . preg_replace_callback('#:([\w]+)\+?#', function ($match) use ($conditions) {
			if (isset($conditions[$match[1]])) {
				return '(?P<' . $match[1] . '>' . $conditions[$match[1]] . ')';
			}
			if (substr($match[0], -1) === '+') {
				return '(?P<' . $match[1] . '>.+)';
			}
			return '(?P<' . $match[1] . '>[^/]+)';
		}, str_replace(')', ')?', (string) $path)) . '$#u';
	}

	public function fallback ($basePathKey, $callback)
	{
		if (($basePathKey === '*') || ($basePathKey === BASE_PATH_KEY)) {
			$this->fallback = $callback;
		}
	}

	function page ($part = false) {
		if ($part !== false) {
			if (isset($this->url[$part])) {
				return $this->url[$part];
			}
			return false;
		}
		return $this->url;
	}

	public function dispatch ()
	{
		$routeFound = false;
		$methodMatch = false;

		foreach ($this->routes as $path => $route) {

			// Check if the current page matches the route.
			if (preg_match($path, $this->urlString, $matches)) {
				$routeFound = true;

				foreach ($matches as $key => $url) {
					if (!is_int($key)) {
						$this->url[$key] = $url;
					}
				}

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
			}
			else {
				$this->template = false;
				$this->error += self::E_TEMPALTE_NOT_FOUND;
			}
		}

		if ($this->error !== 0) {
			// throw new Exception('Router error', $this->error);
		}

		if ($this->canvas !== false) {
			if ($this->parseCanvas === true) {
				$params = Canvas::_set($this->canvas);
				if ($params !== false) {
					if ($this->template !== false) {
						$this->paramsHolder = $params;

						ob_start();
						$return = include $this->template;
						$content = ob_get_contents();
						ob_end_clean();

						if (!is_array($return)) {
							echo $content;
						}
						else {
							$return['content'] = $content;
							header('Content-type: application/json; charset: ' . mb_internal_encoding());
							echo json_encode($return);
							$return = true;
						}
						if ($return !== true) {
							if (is_array($this->paramsHolder)) {
								$params = ArrayUtils::merge($this->paramsHolder, $return);
							}
							else {
								$params = $return;
							}
							$this->dispatchFallback($params);
						}
					}
					elseif ($this->error !== 0) {
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
