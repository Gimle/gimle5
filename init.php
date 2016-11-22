<?php
namespace gimle;

mb_internal_encoding('utf-8');

foreach (new \RecursiveDirectoryIterator(__DIR__ . '/autoload/', \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
	include __DIR__ . '/autoload/' . $fileInfo->getFilename();
}

require __DIR__ . '/lib/' . str_replace('\\', '/', __NAMESPACE__) . '/system.php';

spl_autoload_register(__NAMESPACE__ . '\\System::autoload');

set_error_handler(function ($errno, $message, $file, $line) {
	throw new ErrorException($message, 0, $errno, $file, $line);
});

$config = System::parseConfigFile(SITE_DIR . 'config.ini');

$env_add = ((PHP_SAPI === 'cli') ? ENV_CLI : ENV_WEB);
if (isset($config['env_mode'])) {
	define(__NAMESPACE__ . '\\ENV_MODE', $config['env_mode'] | $env_add);
	unset($config['env_mode']);
}
else {
	/**
	 * The current env level.
	 *
	 * Default value is ENV_LIVE.
	 * ENV_CLI or ENV_WEB will automatically be added.
	 *
	 * <p>Example defining in config.ini</p>
	 * <code>env_mode = ENV_DEV</code>
	 *
	 * <p>Example checking if current env level is cli mode.</p>
	 * <code>if (ENV_MODE & ENV_CLI) {
	 * <span style="white-space: pre; font-size: 50%;">&Tab;</span>// Code for cli.
	 * }</code>
	 *
	 * <p>Example checking if current env level is development or test.</p>
	 * <code>if (ENV_MODE & (ENV_DEV | ENV_TEST)) {
	 * <span style="white-space: pre; font-size: 50%;">&Tab;</span>// Code for development and test.
	 * }</code>
	 *
	 * <p>Example checking if current env level is live and web.</p>
	 * <code>if ((ENV_MODE & ENV_LIVE) && (ENV_MODE & ENV_WEB)) {
	 * <span style="white-space: pre; font-size: 50%;">&Tab;</span>// Code for live web.
	 * }</code>
	 *
	 * <p>Example checking if current env level is not development.</p>
	 * <code>if ((ENV_MODE | ENV_DEV) !== ENV_MODE) {
	 * <span style="white-space: pre; font-size: 50%;">&Tab;</span>// Code for anything but development.
	 * }</code>
	 *
	 * @var int
	 */
	define(__NAMESPACE__ . '\\ENV_MODE', ENV_LIVE | $env_add);
}
unset($env_add);

if (is_readable(SITE_DIR . 'config.php')) {
	$config = ArrayUtils::merge(include SITE_DIR . 'config.php', $config, true);
}

if ((isset($config['path_info_override'])) && ($config['path_info_override'] !== false)) {
	if ($config['path_info_override'] === true) {
		$_SERVER['PATH_INFO'] = explode('?', urldecode($_SERVER['REQUEST_URI']));
	}
	else {
		$_SERVER['PATH_INFO'] = explode('?', urldecode(substr($_SERVER['REQUEST_URI'], strlen($config['path_info_override']))));
	}
	$_SERVER['PATH_INFO'] = $_SERVER['PATH_INFO'][0];
	unset($config['path_info_override']);
	if (isset($_SERVER['MATCH_SITENAME'])) {
		unset($_SERVER['MATCH_SITENAME']);
	}
}

if (isset($config['umask'])) {
	umask($config['umask']);
	unset($config['umask']);
}

$undefinedDir = sys_get_temp_dir() . '/gimle/%s/' . SITE_ID . '/';
foreach (array('temp', 'cache', 'storage') as $dir) {
	if (isset($config['dir'][$dir])) {
		define(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR', $config['dir'][$dir]);
		unset($config['dir'][$dir]);
	}
	else {
		if (isset($config['dir']['jail'])) {
			define(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR', $config['dir']['jail'] . $dir . '/');
		}
		else {
			/**
			 * Sets constants for storage, chache and temp directories.
			 *
			 * @var string
			 */
			define(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR', sprintf($undefinedDir, $dir));
		}
	}
	if (!is_readable(constant(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR'))) {
		mkdir(constant(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR'), 0777, true);
	}
}
//unset($undefinedDir);

$getBase = function () {
	$base = 'http';
	$port = '';
	if (isset($_SERVER['HTTPS'])) {
		$base .= 's';
		if ($_SERVER['SERVER_PORT'] !== '443') {
			$port = ':' . $_SERVER['SERVER_PORT'];
		}
	}
	elseif ($_SERVER['SERVER_PORT'] !== '80') {
		$port = ':' . $_SERVER['SERVER_PORT'];
	}
	$base .= '://';
	$host = explode(':', $_SERVER['HTTP_HOST']);
	$base .= $host[0] . $port . '/';
	unset($host, $port);

	if (isset($_SERVER['MATCH_SITENAME'])) {
		$_SERVER['PATH_INFO'] = substr(ltrim(urldecode($_SERVER['REQUEST_URI']), '/'), strlen($_SERVER['MATCH_SITENAME']));
		if ($_SERVER['QUERY_STRING'] !== '') {
			$_SERVER['PATH_INFO'] = explode('?', $_SERVER['PATH_INFO'])[0];
		}
		elseif (substr($_SERVER['PATH_INFO'], -1, 1) === '?') {
			$_SERVER['PATH_INFO'] = substr($_SERVER['PATH_INFO'], 0, -1);
		}
	}
	if (isset($_SERVER['PATH_INFO'])) {
		$base .= ltrim(mb_substr(urldecode($_SERVER['REQUEST_URI']), 0, -mb_strlen($_SERVER['PATH_INFO'])), '/');
	}
	else {
		$base .= ltrim($_SERVER['SCRIPT_NAME'], '/');
		if (mb_strlen(basename($_SERVER['SCRIPT_NAME'])) > 0) {
			$base = substr($base, 0, -mb_strlen(basename($base)));
		}
	}
	return $base;
};

if (ENV_MODE & ENV_WEB) {
	if (isset($config['subsite'])) {
		if (isset($config['subsite']['path'])) {
			define(__NAMESPACE__ . '\\SUBSITE_PATH', $config['subsite']['path']);
			foreach ($config['subsite']['of'] as $id => $path) {
				if (defined(__NAMESPACE__ . '\\BASE_PATH')) {
					break;
				}
				$subConfig = System::parseConfigFile($path . 'config.ini');
				if ((isset($subConfig['base'])) && (is_array($subConfig['base']))) {
					$base = $getBase();
					if (isset($matches)) {
						unset($matches);
					}
					foreach ($subConfig['base'] as $key => $value) {
						if ((!isset($value['path'])) || ((!isset($value['start'])) && (!isset($value['regex'])))) {
							continue;
						}

						if (isset($value['key'])) {
							$key = $value['key'];
						}

						if (!defined(__NAMESPACE__ . '\\BASE_PATH')) {
							if ((isset($value['start'])) && ($value['start'] !== substr($base, 0, strlen($value['start'])))) {
								continue;
							}

							if (isset($value['regex'])) {
								if (preg_match($value['regex'], $base, $matches)) {
								}
								else {
									continue;
								}
							}

							define(__NAMESPACE__ . '\\SUBSITE_OF_ID', $id);
							define(__NAMESPACE__ . '\\MAIN_SITE_DIR', $path);
							define(__NAMESPACE__ . '\\MAIN_SITE_ID', substr(trim($path, DIRECTORY_SEPARATOR), strrpos(trim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) + 1));

							foreach (array('temp', 'cache', 'storage') as $dir) {
								if (isset($subConfig['dir'][$dir])) {
									define(__NAMESPACE__ . '\\MAIN_' . strtoupper($dir) . '_DIR', $subConfig['dir'][$dir]);
									unset($subConfig['dir'][$dir]);
								}
								else {
									if (isset($subConfig['dir']['jail'])) {
										define(__NAMESPACE__ . '\\MAIN_' . strtoupper($dir) . '_DIR', $path . $dir . '/');
									}
									else {
										$undefinedMainDir = sys_get_temp_dir() . '/gimle/%s/' . MAIN_SITE_ID . '/';
										/**
										 * Sets constants for storage, chache and temp directories.
										 *
										 * @var string
										 */
										define(__NAMESPACE__ . '\\MAIN_' . strtoupper($dir) . '_DIR', sprintf($undefinedMainDir, $dir));
									}
								}
								if (!is_readable(constant(__NAMESPACE__ . '\\MAIN_' . strtoupper($dir) . '_DIR'))) {
									mkdir(constant(__NAMESPACE__ . '\\MAIN_' . strtoupper($dir) . '_DIR'), 0777, true);
								}
							}

							foreach ($subConfig['base'] as $subKey => $subValue) {
								if ((!isset($subValue['path'])) || ((!isset($subValue['start'])) && (!isset($subValue['regex'])))) {
									continue;
								}

								if ((isset($subValue['regex'])) && (isset($matches))) {
									foreach ($matches as $index => $match) {
										if (is_int($index)) {
											continue;
										}
										$subValue['path'] = str_replace('{' . $index . '}', $match, $subValue['path']);
									}
								}

								if (isset($subValue['key'])) {
									$subKey = $subValue['key'];
								}

								if (!defined(__NAMESPACE__ . '\\MAIN_BASE_' . mb_strtoupper($subKey))) {
									define(__NAMESPACE__ . '\\MAIN_BASE_' . mb_strtoupper($subKey), $subValue['path']);
									define(__NAMESPACE__ . '\\BASE_' . mb_strtoupper($subKey), $subValue['path'] . SUBSITE_PATH);
								}
							}

							if ((isset($value['regex'])) && (isset($matches))) {
								foreach ($matches as $index => $match) {
									if (is_int($index)) {
										continue;
									}
									$value['path'] = str_replace('{' . $index . '}', $match, $value['path']);
								}
							}

							define(__NAMESPACE__ . '\\BASE_PATH', $value['path'] . SUBSITE_PATH);
							define(__NAMESPACE__ . '\\MAIN_BASE_PATH', $value['path']);
							define(__NAMESPACE__ . '\\BASE_PATH_KEY', $key);
							define(__NAMESPACE__ . '\\IS_SUBSITE', true);

							if (is_readable(MAIN_SITE_DIR . 'config.php')) {
								$subConfig = ArrayUtils::merge(include MAIN_SITE_DIR . 'config.php', $subConfig, true);
							}

							MainConfig::setAll($subConfig);
						}
					}
				}
			}
		}
	}

	if (defined(__NAMESPACE__ . '\\IS_SUBSITE')) {
	}
	elseif (!isset($config['base'])) {
		throw new \Exception('No basepath set.');
	}
	elseif (!is_array($config['base'])) {
		throw new \Exception('Invalid basepath set.');
	}
	elseif (is_array($config['base'])) {
		define(__NAMESPACE__ . '\\IS_SUBSITE', false);
		$base = $getBase();

		if (isset($matches)) {
			unset($matches);
		}

		foreach ($config['base'] as $key => $value) {
			if ((!isset($value['path'])) || ((!isset($value['start'])) && (!isset($value['regex'])))) {
				throw new \Exception('Basepath configuration missing.');
			}

			if (isset($value['key'])) {
				$key = $value['key'];
			}

			if (!defined(__NAMESPACE__ . '\\BASE_PATH')) {
				if ((isset($value['start'])) && ($value['start'] === substr($base, 0, strlen($value['start'])))) {
					define(__NAMESPACE__ . '\\BASE_PATH', $value['path']);
					define(__NAMESPACE__ . '\\BASE_PATH_KEY', $key);
				}
				elseif ((isset($value['regex'])) && (preg_match($value['regex'], $base, $matches))) {
					foreach ($matches as $index => $match) {
						if (is_int($index)) {
							continue;
						}
						$value['path'] = str_replace('{' . $index . '}', $match, $value['path']);
					}

					/**
					 * The public base path of the site.
					 *
					 * This must be set in a config file.
					 * When multiple domains is matched, it will match in the same order as in the config.
					 * The default value will be calculated automatically.
					 *
					 * <p>Example single domain as string in config.ini</p>
					 * <code>base = "http://example.com/"</code>
					 *
					 * <p>Example multiple domain with string start match in config.ini</p>
					 * <code>[base.mobile]
					 * start = "http://m.";
					 * path = "http://m.example.com/"
					 *
					 * [base.default]
					 * start = "http://";
					 * path = "http://example.com/"</code>
					 * <p>To search with a regular expression, change the "start" keyword with "regex".</p>
					 *
					 * @var string
					 */
					define(__NAMESPACE__ . '\\BASE_PATH', $value['path']);

					/**
					 * The key to the currenty matched base path from config.
					 *
					 * <p>When working with multiple bases in config, this will contain the key of the matched block.</p>
					 */
					define(__NAMESPACE__ . '\\BASE_PATH_KEY', $key);
				}
			}
		}

		foreach ($config['base'] as $key => $value) {
			/**
			 * The absolute path to the base of each of the base paths defined in config.
			 *
			 * <p>When working with multiple bases in config, each will be assigned to their own constant, starting with BASE_</p>
			 */
			if (!defined(__NAMESPACE__ . '\\BASE_' . mb_strtoupper($key))) {
				if ((isset($value['regex'])) && (isset($matches))) {
					foreach ($matches as $index => $match) {
						if (is_int($index)) {
							continue;
						}
						$value['path'] = str_replace('{' . $index . '}', $match, $value['path']);
					}
				}
				define(__NAMESPACE__ . '\\BASE_' . mb_strtoupper($key), $value['path']);
				define(__NAMESPACE__ . '\\MAIN_BASE_' . mb_strtoupper($key), $value['path']);
			}
		}

		if (!defined(__NAMESPACE__ . '\\BASE_PATH')) {
			throw new \Exception('No matching basepath configuration.');
		}

		define(__NAMESPACE__ . '\\MAIN_SITE_ID', SITE_ID);
		define(__NAMESPACE__ . '\\MAIN_SITE_DIR', SITE_DIR);
		define(__NAMESPACE__ . '\\MAIN_BASE_PATH', BASE_PATH);
		define(__NAMESPACE__ . '\\MAIN_TEMP_DIR', TEMP_DIR);
		define(__NAMESPACE__ . '\\MAIN_CACHE_DIR', CACHE_DIR);
		define(__NAMESPACE__ . '\\MAIN_STORAGE_DIR', STORAGE_DIR);
	}
	unset($config['base']);

	$thisPath = BASE_PATH;
	if (isset($_SERVER['PATH_INFO'])) {
		$pathInfo = trim($_SERVER['PATH_INFO'], '/');
		if ($pathInfo !== '') {
			$thisPath .= $pathInfo;
		}
	}
	define(__NAMESPACE__ . '\\THIS_PATH', $thisPath);
	unset($thisPath);

	define(__NAMESPACE__ . '\\PAGE_PATH', (string) substr(THIS_PATH, strlen(BASE_PATH)));
	if (isset($config['base'])) {
		unset($config['base']);
	}
}


if (isset($config['timezone'])) {
	date_default_timezone_set($config['timezone']);
	unset($config['timezone']);
}
else {
	date_default_timezone_set('CET');
}

if (ENV_MODE & ENV_CLI) {
	ini_set('html_errors', false);
}
if ((isset($config['server']['override'])) && (is_array($config['server']['override'])) && (!empty($config['server']['override']))) {
	if ((ENV_MODE & ENV_WEB) && (isset($config['server']['override']['html_errors']))) {
		ini_set('html_errors', $config['server']['override']['html_errors']);
	}
	if (isset($config['server']['override']['error_reporting'])) {
		ini_set('error_reporting', $config['server']['override']['error_reporting']);
		error_reporting($config['server']['override']['error_reporting']);
	}
	if (isset($config['server']['override']['max_execution_time'])) {
		ini_set('max_execution_time', $config['server']['override']['max_execution_time']);
	}
	if (isset($config['server']['override']['memory_limit'])) {
		ini_set('memory_limit', $config['server']['override']['memory_limit']);
	}
	if (ENV_MODE & ENV_CLI) {
		if (isset($config['server']['override']['html_errors_cli'])) {
			ini_set('html_errors', $config['server']['override']['html_errors_cli']);
		}
		if (isset($config['server']['override']['error_reporting_cli'])) {
			ini_set('error_reporting', $config['server']['override']['error_reporting_cli']);
			error_reporting($config['server']['override']['error_reporting_cli']);
		}
		if (isset($config['server']['override']['max_execution_time_cli'])) {
			ini_set('max_execution_time', $config['server']['override']['max_execution_time_cli']);
		}
		if (isset($config['server']['override']['memory_limit_cli'])) {
			ini_set('memory_limit', $config['server']['override']['memory_limit_cli']);
		}
	}
	unset($config['server']['override']);
	if (empty($config['server'])) {
		unset($config['server']);
	}
}

Config::setAll($config);

unset($config);

foreach (System::getModules(GIMLE5) as $name) {
	if (is_executable(SITE_DIR . 'module/' . $name . '/autoload/')) {
		foreach (new \RecursiveDirectoryIterator(SITE_DIR . 'module/' . $name . '/autoload/', \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
			include SITE_DIR . 'module/' . $name . '/autoload/' . $fileInfo->getFilename();
		}
	}
	if (is_executable(SITE_DIR . 'module/' . $name . '/lib/')) {
		System::autoloadRegister(SITE_DIR . 'module/' . $name . '/lib/');
	}
}

if (is_executable(SITE_DIR . 'lib/')) {
	System::autoloadRegister(SITE_DIR . 'lib/');
}

set_exception_handler(function ($e) {
	if (ENV_MODE & ENV_WEB) {
		ob_clean();
		$getCanvas = function ($canvas) {
			if ((substr($canvas, 0, strlen(SITE_DIR)) === SITE_DIR) && (is_readable($canvas))) {
				return $canvas;
			}
			if (is_readable(SITE_DIR . 'canvas/' . $canvas . '.php')) {
				return SITE_DIR . 'canvas/' . $canvas . '.php';
			}
			if (is_readable(SITE_DIR . 'module/' . GIMLE5 . '/canvas/' . $canvas . '.php')) {
				return SITE_DIR . 'module/' . GIMLE5 . '/canvas/' . $canvas . '.php';
			}
			return false;
		};

		$canvas = router\Router::getInstance()->getCanvas();
		$canvasCheck = current($e->getTrace());
		if ((is_array($canvasCheck)) && (isset($canvasCheck['file'])) && ($canvasCheck['file'] === $canvas)) {
			$canvas = SITE_DIR . 'module/' . GIMLE5 . '/canvas/pc.php';
			$headers = headers_list();
			foreach ($headers as $header) {
				$check = 'Content-type: application/json;';
				if (substr($header, 0, strlen($check)) === $check) {
					$canvas = SITE_DIR . 'module/' . GIMLE5 . '/canvas/json.php';
				}
			}
		}
		else {

			if ($canvas !== false) {
				$canvas = $getCanvas($canvas);
			}
			if ($canvas === false) {
				$headers = headers_list();
				foreach ($headers as $header) {
					$check = 'Content-type: application/json;';
					if (substr($header, 0, strlen($check)) === $check) {
						$canvas = $getCanvas('json');
					}
				}
			}
			if ($canvas === false) {
				$canvas = $getCanvas('pc');
			}
		}
		canvas\Canvas::_override($canvas);
		$template = 500;
		if (($e instanceof \gimle\router\Exception) && ($e->getCode() === router\Router::E_ROUTE_NOT_FOUND)) {
			$template = 404;
		}
		if ($e instanceof \gimle\template\Exception) {
			$template = $e->getCode();
		}

		$findTemplate = function ($name) {
			if (is_readable(SITE_DIR . 'template/error/' . $name . '.php')) {
				return SITE_DIR . 'template/error/' . $name . '.php';
			}
			foreach (System::getModules() as $module) {
				if (is_readable(SITE_DIR . 'module/' . $module . '/template/error/' . $name . '.php')) {
					return SITE_DIR . 'module/' . $module . '/template/error/' . $name . '.php';
				}
			}
			return false;
		};

		$template = $findTemplate($template);
		if ($template === false) {
			$template = $findTemplate(500);
		}
		inc($template, $e);

		Spectacle::getInstance()->tab('Spectacle')->push($e);
		canvas\Canvas::_create();
		return;
	}
	d($e);
	echo "\n";
	echo $e->getMessage() . ' in ';
	echo $e->getFile() . ' on line ';
	echo $e->getLine() . "\n";
	echo "\n";
});

if (is_executable(SITE_DIR . 'autoload/')) {
	foreach (new \RecursiveDirectoryIterator(SITE_DIR . '/autoload/', \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
		include SITE_DIR . '/autoload/' . $fileInfo->getFilename();
	}
}
