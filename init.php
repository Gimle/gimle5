<?php
namespace gimle;

foreach (new \RecursiveDirectoryIterator(__DIR__ . '/autoload/', \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
	include __DIR__ . '/autoload/' . $fileInfo->getFilename();
}

require __DIR__ . '/object/' . str_replace('\\', '/', __NAMESPACE__) . '/system.php';

spl_autoload_register(__NAMESPACE__ . '\\System::autoload');

$config = parse_config_file(SITE_DIR . 'config.ini');

$env_add = ((PHP_SAPI === 'cli') ? ENV_CLI : ENV_WEB);
if (isset($config['env_mode'])) {
	define(__NAMESPACE__ . '\\ENV_MODE', $config['env_mode'] | $env_add);
	unset($config['env_mode']);
} else {
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

if (file_exists(SITE_DIR . 'config.php')) {
	$config = ArrayUtils::merge(require SITE_DIR . 'config.php', $config, true);
}

if (isset($config['module'])) {
	foreach ($config['module'] as $name => $value) {
		System::autoloadRegister(SITE_DIR . 'module/' . $name . '/object/');
	}
}

if ((isset($config['path_info_override'])) && ($config['path_info_override'] !== false)) {
	if ($config['path_info_override'] === true) {
		$_SERVER['PATH_INFO'] = explode('?', urldecode($_SERVER['REQUEST_URI']));
	} else {
		$_SERVER['PATH_INFO'] = explode('?', urldecode(substr($_SERVER['REQUEST_URI'], strlen($config['path_info_override']))));
	}
	$_SERVER['PATH_INFO'] = $_SERVER['PATH_INFO'][0];
	unset($config['path_info_override']);
}

if (isset($config['umask'])) {
	umask($config['umask']);
}

$undefinedDir = sys_get_temp_dir() . '/gimle/%s/' . SITE_ID . '/';
foreach (array('temp', 'cache', 'storage') as $dir) {
	if (isset($config['dir'][$dir])) {
		define(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR', $config['dir'][$dir]);
		unset($config['dir'][$dir]);
	} else {
		if (isset($config['dir']['jail'])) {
			define(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR', $config['dir']['jail'] . $dir . '/');
		} else {
			/**
			 * The local absolute location of where files should be stored.
			 *
			 * This will default to the systems default temp directory.
			 *
			 * @var string
			 */
			define(__NAMESPACE__ . '\\' . strtoupper($dir) . '_DIR', sprintf($undefinedDir, $dir));
		}
	}
}
unset($undefinedDir);

if (isset($config['timezone'])) {
	date_default_timezone_set($config['timezone']);
}

Config::setAll($config);
unset($config);

if (Config::exists('module')) {
	foreach (Config::get('module') as $name => $value) {
		if (file_exists(SITE_DIR . 'module/' . $name . '/autoload/')) {
			foreach (new \RecursiveDirectoryIterator(SITE_DIR . 'module/' . $name . '/autoload/', \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
				include SITE_DIR . 'module/' . $name . '/autoload/' . $fileInfo->getFilename();
			}
		}
	}
}
