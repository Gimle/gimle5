<?php
namespace gimle\i18n;

use const gimle\STORAGE_DIR;
use const gimle\STATIC_DIR;
use function gimle\d;
use gimle\Exception;
use gimle\Config;

class gettext
{
	use \gimle\trick\Multiton;

	private $original;
	private $lc;

	public function __construct ($identifier, $params)
	{
		if (is_string($params)) {
			$params = [$params];
		}

		$this->lc = Config::get('i18n.lang.' . $identifier . '.lc');
		if ($this->lc === null) {
			throw new Exception('LC not configured.');
		}

		$this->original = setlocale(LC_MESSAGES, 0);

		bindtextdomain('messages', str_replace(['storage://', 'static://'], [STORAGE_DIR, STATIC_DIR], current($params)));
		textdomain('messages');
	}

	public function _ (...$message)
	{
		if ($this->original !== $this->lc) {
			setlocale(LC_MESSAGES, $this->lc);
		}

		$properties = [];
		if (is_array(end($message))) {
			$properties = array_pop($message);
			if ((isset($properties['quantity'])) && (!is_int($properties['quantity']))) {
				if ($properties['quantity'] === 'one') {
					$properties['quantity'] = 1;
				}
				else {
					$properties['quantity'] = 1000;
				}
			}
		}

		$return = false;
		foreach ($message as $lookup) {
			if (isset($properties['quantity'])) {
				if (!is_array($lookup)) {
					$check = ngettext($lookup, $lookup, $properties['quantity']);
				}
				else {
					$check = ngettext($lookup[0], $lookup[1], $properties['quantity']);
				}
			}
			else {
				$check = gettext($lookup);
			}
			if ($check !== $lookup) {
				$return = $check;
				break;
			}
		}

		if ($this->original !== $this->lc) {
			setlocale(LC_MESSAGES, $this->original);
		}

		return $return;
	}
}
