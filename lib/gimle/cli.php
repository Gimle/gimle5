<?php
namespace gimle;

class Cli
{
	use trick\Singelton;

	private $title = '';
	private $description = '';
	private $isRoot = false;
	private $requireRoot = false;

	private $optionsSet = [];
	private $optionsAvailable = ['help' =>
		['short' => 'h', 'description' => 'Show command help.', 'value' => false],
	];
	private $parametersSet = [];
	private $parametersAvailable = [];

	private $routes = [];

	public function __construct ()
	{
		if (posix_getpwuid(posix_geteuid())['name'] === 'root') {
			$this->isRoot = true;
		}
	}

	public function title ($set = null)
	{
		if ($set === null) {
			return $this->title;
		}
		$this->title = (string) $set;
		return $this;
	}

	public function description ($set = null)
	{
		if ($set === null) {
			return $this->description;
		}
		$this->description = (string) $set;
		return $this;
	}

	public function requireRoot ($set = null)
	{
		if ($set === null) {
			return $this->requireRoot;
		}
		$this->requireRoot = (bool) $set;
		return $this;
	}

	public function dispatch ()
	{
		$count = count($_SERVER['argv']);
		$parameterKeys = array_keys($this->parametersAvailable);
		for ($i = 1; $i < $count; $i++) {
			if (substr($_SERVER['argv'][$i], 0, 2) === '--') {
				$name = substr($_SERVER['argv'][$i], 2);
				if (isset($this->optionsAvailable[$name])) {
					if ($this->optionsAvailable[$name]['value'] === false) {
						$this->optionsSet[$name] = true;
						continue;
					}
					if (isset($_SERVER['argv'][$i + 1])) {
						$this->optionsSet[$name] = $_SERVER['argv'][++$i];
						continue;
					}
				}
				if (!array_key_exists($name, $this->optionsAvailable)) {
					$this->err('Invalid options.');
					exit(1);
				}
				continue;
			}

			if (substr($_SERVER['argv'][$i], 0, 1) === '-') {
				$values = str_split(substr($_SERVER['argv'][$i], 1));
				$valueCount = count($values);
				for ($j = 0; $j < $valueCount; $j++) {
					foreach ($this->optionsAvailable as $name => $option) {
						if ($option['short'] === $values[$j]) {
							if ($option['value'] === false) {
								$this->optionsSet[$name] = true;
								continue 2;
							}
							if ($valueCount > $j + 1) {
								continue 2;
							}
							if (isset($_SERVER['argv'][$i + 1])) {
								$this->optionsSet[$name][] = $_SERVER['argv'][++$i];
								continue;
							}
						}
					}
				}
				$available = [];
				foreach ($this->optionsAvailable as $option) {
					$available[] = $option['short'];
				}
				foreach ($values as $value) {
					if (!in_array($value, $available)) {
						$this->err('Invalid options.');
						exit(1);
					}
				}
				continue;
			}

			$index = count($this->parametersSet);
			if (isset($parameterKeys[$index])) {
				$this->parametersSet[$parameterKeys[$index]] = $_SERVER['argv'][$i];
			}
			else {
				$this->err('Invalid parameters.');
				exit(1);
			}
		}

		if ((isset($this->optionsSet['help'])) && (count($this->optionsSet) === 1) && (empty($this->parametersSet))) {
			$this->showHelp();
		}

		if (($this->isRoot === false) && ($this->requireRoot)) {
			$this->err('This script needs to run as root.');
			exit(1);
		}

		foreach ($this->routes as $route) {
			foreach ($route['parameters'] as $name => $type) {
				if ($type !== false) {
					if (!isset($this->parametersSet[$name])) {
						continue 2;
					}
				}
			}
			foreach ($route['options'] as $name => $type) {
				if ($type !== false) {
					if (!isset($this->optionsSet[$name])) {
						continue 2;
					}
					if ((is_string($type)) && ($this->optionsSet[$name] !== $type)) {
						continue 2;
					}
					if (is_array($type)) {
						foreach ($this->optionsSet[$name] as $set) {
							if (!in_array($set, $type)) {
								continue 3;
							}
						}
					}

				}
			}

			foreach (array_keys($this->parametersSet) as $name) {
				if (!array_key_exists($name, $route['parameters'])) {
					continue 2;
				}
			}
			foreach (array_keys($this->optionsSet) as $name) {
				if (!array_key_exists($name, $route['options'])) {
					continue 2;
				}
			}

			$result = $route['callback']($this->optionsSet, $this->parametersSet);
			if ($result !== false) {
				exit();
			}
		}

		$this->err('Invalid arguments.');
		exit(1);
	}

	public function route ($options, $parameters, $callback)
	{
		array_unshift($this->routes, ['options' => $options, 'parameters' => $parameters, 'callback' => $callback]);
	}

	private function showHelp ()
	{
		$this->out();
		if ($this->title !== '') {
			$this->out($this->title);
		}

		if ($this->description !== '') {
			$this->out();
			$this->out($this->description);
		}

		$this->out();

		if (!empty($this->parametersAvailable)) {
			$count = 0;
			$this->out('Parameters:');
			foreach ($this->parametersAvailable as $parameter) {
				$this->out('  ' . $parameter);
			}
			$this->out();
		}


		$this->out('Options:');
		$this->out();
		$len = 1;
		foreach (array_keys($this->optionsAvailable) as $value) {
			if (strlen($value) > $len) {
				$len = strlen($value);
			}
		}
		foreach ($this->optionsAvailable as $key => $value) {
			$line = '  ';
			if ($value['short'] !== false) {
				$line .= '-' . $value['short'] . ', ';
			}
			else {
				$line .= '    ';
			}
			$line .= '--' . str_pad($key, $len + 2, ' ', STR_PAD_RIGHT);
			$line .= $value['description'];
			$this->out($line);
		}

		$this->out();

		exit();
	}

	public function addOption ($key, $description = '', $short = false, $value = false)
	{
		$this->optionsAvailable[$key] = ['short' => $short, 'description' => $description, 'value' => $value];
	}

	public function addParameter ($id, $description)
	{
		$this->parametersAvailable[$id] = $description;
	}

	public function out ($message = '', $color = false, $newline = true)
	{
		echo $message;
		if ($newline === true) {
			echo "\n";
		}
	}

	public function err ($message = '', $color = false, $newline = true)
	{
		fwrite(STDERR, $message);
		if ($newline === true) {
			fwrite(STDERR, "\n");
		}
	}

}
