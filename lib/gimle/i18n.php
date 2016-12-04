<?php
namespace gimle;

class i18n
{
	use trick\Singelton;

	private $config;
	private $language = false;
	private $objects = [];

	public function __construct ()
	{
		$this->config = Config::get('i18n');
		if ((!isset($this->config['lang'])) || (!is_array($this->config['lang'])) || (empty($this->config['lang']))) {
			throw new Exception('No language configured.');
		}
	}

	private function setup ()
	{
		if (isset($this->config['lang'][$this->language]['lc'])) {
			setlocale(LC_TIME, $this->config['lang'][$this->language]['lc']);
			setlocale(LC_COLLATE, $this->config['lang'][$this->language]['lc']);
			setlocale(LC_CTYPE, $this->config['lang'][$this->language]['lc']);
		}

		if ((isset($this->config['lang'][$this->language]['objects'])) && (is_array($this->config['lang'][$this->language]['objects'])) && (!empty($this->config['lang'][$this->language]['objects']))) {
			foreach ($this->config['lang'][$this->language]['objects'] as $object => $params) {
				$this->objects[] = call_user_func_array([__NAMESPACE__ . '\\i18n\\' . $object, 'getInstance'], [$this->language, $params]);
			}
		}
	}

	public function setLanguage ($language = false)
	{
		if ($language === false) {
			$this->language = get_preferred_language(array_keys($this->config['lang']));
			if ($this->language === false) {
				if ((isset($this->config['default'])) && (isset($this->config['lang'][$this->config['default']]))) {
					$this->language = $this->config['default'];
				}
				else {
					$this->language = current(array_keys($this->config['lang']));
				}
			}
		}
		else {
			if (isset($this->config['lang'][$language])) {
				$this->language = $language;
			}
			elseif ((isset($this->config['default'])) && (isset($this->config['lang'][$this->config['default']]))) {
				$this->language = $this->config['default'];
			}
			else {
				$this->language = current(array_keys($this->config['lang']));
			}
		}

		$this->setup();
	}

	public function _ (...$message)
	{
		if ($this->language === false) {
			$this->setLanguage();
		}

		if (!empty($this->objects)) {
			foreach ($this->objects as $object) {
				$result = $object->_(...$message);
				if ($result !== false) {
					return $result;
				}
			}
		}
		return current($message);
	}
}
