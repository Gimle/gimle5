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

	public function push ($tab, ...$data)
	{
		$name = $tab;
		$tab = preg_replace('/[^a-zA-Z1-9]/', '_', mb_strtolower($tab));
		if ($tab === 'info') {
			return false;
		}
		if (isset($this->data['tabs'][$tab])) {
			$this->data['tabs'][$tab]['content'] .= $this->datafy($data);
		}
		else {
			$this->data['tabs'][$tab] = ['title' => $name, 'content' => $this->datafy($data)];
		}
		return $this;
	}

	public function unshift ($tab, ...$data)
	{
		$name = $tab;
		$tab = preg_replace('/[^a-zA-Z1-9]/', '_', mb_strtolower($tab));
		if ($tab === 'info') {
			return false;
		}
		if (isset($this->data['tabs'][$tab])) {
			$this->data['tabs'][$tab]['content'] = $this->datafy($data) . $this->data['tabs'][$tab]['content'];
		}
		else {
			$this->data['tabs'][$tab] = ['title' => $name, 'content' => $this->datafy($data)];
		}
		return $this;
	}

	private function datafy ($data)
	{
		$return = [];
		$backtrace = debug_backtrace();
		$file = $backtrace[1]['file'];
		$line = $backtrace[1]['line'];
		$return[] = '<p>Spectacle: <b style="color: DarkBlue;">' . $file . '</b> on line <b style="color: DarkBlue;">' . $line . '</b></p>';

		foreach ($data as $item) {
			if (!is_string($item)) {
				$return[] = d($item, true, 'item');
			}
			else {
				$return[] = d($item, true, 'item');
			}
		}
		return implode('', $return);
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
