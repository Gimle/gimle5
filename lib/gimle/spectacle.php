<?php
namespace gimle;

class Spectacle
{
	use trick\Singelton;

	private $id;
	private $data = [];
	private static $dir = TEMP_DIR . 'gimle/chrome/';
	private static $lifeTime = 3600;

	public function __construct ()
	{
		if (!file_exists(self::$dir)) {
			mkdir(self::$dir, 0777, true);
		}
		else {
			foreach (new \DirectoryIterator(self::$dir) as $item) {
				$name = $item->getFilename();
				if (substr($name, 0, 1) === '.') {
					continue;
				}
				$age = time() - $item->getMTime();
				if ($age > self::$lifeTime) {
					unlink(self::$dir . $name);
				}
			}
		}
		$file = basename(tempnam(self::$dir, ''));
		header('X-Gimle-chrome-id: ' . $file);
		if ($base = Config::get('spectacle.base')) {
			header('X-Gimle-base-path: ' . $base);
		}
		else {
			header('X-Gimle-base-path: ' . BASE_PATH);
		}
		$this->id = $file;

		register_shutdown_function([$this, 'shutdown']);
	}

	public function push ($tab, $name, $data)
	{
		$tab = mb_strtolower($tab);
		if ($tab === 'info') {
			return false;
		}
		if (isset($this->data['tabs'][$tab])) {
			$this->data['tabs'][$tab]['content'] .= $data;
		}
		else {
			$this->data['tabs'][$tab] = ['title' => $name, 'content' => $data];
		}
	}

	public function unshift ($tab, $data)
	{
		$tab = mb_strtolower($tab);
		if ($tab === 'info') {
			return false;
		}
		if (isset($this->data['tabs'][$tab])) {
			$this->data['tabs'][$tab]['content'] = $data . $this->data['tabs'][$tab]['content'];
		}
		else {
			$this->data['tabs'][$tab] = ['title' => $name, 'content' => $data];
		}
	}

	public function shutdown ()
	{
		$this->data['time'] = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
		$dbs = sql\Mysql::getInstances();
		if (!empty($dbs)) {
			$this->data['tabs']['mysql'] = ['title' => 'Mysql', 'content' => ''];
			$this->data['info']['mysql'] = ['connections' => 0, 'count' => 0, 'time' => 0, 'doubles' => false];
			foreach ($dbs as $name) {
				$this->data['info']['mysql']['connections']++;
				$db = sql\Mysql::getInstance($name);
				$this->data['tabs']['mysql']['content'] .= $db->explain();
				$stats = $db->getQueryTotals();
				$this->data['info']['mysql']['count'] += $stats['count'];
				$this->data['info']['mysql']['time'] += $stats['time'];
				if (($stats['doubles'] === true) && ($this->data['info']['mysql']['doubles'] === false)) {
					$this->data['info']['mysql']['doubles'] = true;
					$this->data['tabs']['mysql']['content'] = '<p style="color: deeppink;">You have duplicate queries!</p>' . $this->data['tabs']['mysql']['content'];
				}
			}
		}
		else {
			$this->data['info']['mysql'] = false;
		}
	}

	public static function read ($id)
	{
		if (file_exists(self::$dir . $id)) {
			return file_get_contents(self::$dir . $id);
		}
		return false;
	}

	public function __destruct ()
	{
		file_put_contents(self::$dir . $this->id, json_encode($this->data) . "\n");
		chmod(self::$dir . $this->id, 0664);
	}
}
