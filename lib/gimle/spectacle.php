<?php
namespace gimle;

class Spectacle
{
	use trick\Singelton;

	private $id;
	private $data = [];
	private $match = [];
	private $tab = 'Spectacle';

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

	public function push (...$data)
	{
		$tab = $this->tab;
		$this->tab = 'Spectacle';
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
	}

	public function unshift (...$data)
	{
		$tab = $this->tab;
		$this->tab = 'Spectacle';
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
	}

	public function match ($match = [])
	{
		$this->match = $match;
		return $this;
	}

	public function tab ($name)
	{
		$this->tab = $name;
		return $this;
	}

	private function datafy ($data)
	{
		$match = $this->match;

		if (!isset($match['match'])) {
			$match['match'] = '/([a-zA-Z\\\\]+|)(Spectacle::getInstance\(\)(.*?)(->(push|unshift)\((.*)))/';
		}
		if (!isset($match['steps'])) {
			$match['steps'] = 1;
		}
		if (!isset($match['index'])) {
			$match['index'] = 4;
		}
		if (!isset($match['fallback'])) {
			$match['fallback'] = false;
		}

		$return = [];
		$backtrace = debug_backtrace();
		$backtrace = $backtrace[$match['steps']];
		$file = $backtrace['file'];
		$line = $backtrace['line'];

		if (substr($backtrace['file'], -13) == 'eval()\'d code') {
			$title = 'eval()';
		}
		else {
			$con = explode("\n", file_get_contents($backtrace['file']));
			$callee = $con[$backtrace['line'] - 1];
			preg_match_all($match['match'], $callee, $matches);
			if (!empty($matches)) {
				$i = 0;
				$title = '';
				foreach (str_split($matches[$match['index']][0], 1) as $value) {
					if ($value === '(') {
						$i++;
					}
					if (($i === 0) && ($value === ',')) {
						break;
					}
					if ($value === ')') {
						$i--;
					}
					if (($i === 0) && ($value === ')')) {
						$title .= $value;
						break;
					}
					$title .= $value;
				}
			}
			elseif ($match['fallback'] === false) {
				$title = trim($con[$backtrace['line'] - 1]);
			}
			else {
				$title = $match['fallback'];
			}
		}
		$this->match = [];

		$return[] = '<p><span style="font-family: monospace; color: DarkBlue;">' . $title . '</span> in <span style="color: DarkBlue;">' . $file . '</span> on line <span style="color: DarkBlue;">' . $line . '</span></p>';

		foreach ($data as $index => $item) {
			$return[] = d($item, true, 'param' . ($index + 1));
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
