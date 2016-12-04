<?php
namespace gimle\i18n;

use function gimle\d;
use gimle\xml\SimpleXmlElement;

class Android
{
	use \gimle\trick\Multiton;

	private $sxml = [];

	public function __construct ($identifier, $params)
	{
		if (is_string($params)) {
			$params = [$params];
		}

		foreach ($params as $file) {
			$this->sxml[] = new SimpleXmlElement(file_get_contents($file));
		}
	}

	public function _ (...$message)
	{
		$properties = [];
		if (is_array(end($message))) {
			$properties = array_pop($message);
			if ((isset($properties['quantity'])) && (is_int($properties['quantity']))) {
				if ($properties['quantity'] === 1) {
					$properties['quantity'] = 'one';
				}
				else {
					$properties['quantity'] = 'other';
				}
			}
		}
		foreach ($message as $lookup) {
			if (isset($properties['quantity'])) {
				$query = '//plurals[@name=' . current($this->sxml)->real_escape_string($lookup) . ']/item[@quantity="' . $properties['quantity'] . '"]';
			}
			elseif (in_array('array', $properties)) {
				$query = '//string-array[@name=' . current($this->sxml)->real_escape_string($lookup) . ']';
			}
			else {
				$query = '//string[@name=' . current($this->sxml)->real_escape_string($lookup) . ']';
			}
			foreach ($this->sxml as $sxml) {
				$result = current($sxml->xpath($query));
				if ($result !== false) {
					if (in_array($result->getName(), ['string', 'item'])) {
						$result = $result->innerXml();
						if ((substr($result, 0, 1) === '"') && (substr($result, -1, 1) === '"')) {
							return substr($result, 1, -1);
						}
						return str_replace(['\\\'', '\\"'], ['\'', '"'], $result);
					}

					$return = [];
					foreach ($result->xpath('./item') as $item) {
						$result = $item->innerXml();
						if ((substr($result, 0, 1) === '"') && (substr($result, -1, 1) === '"')) {
							$return[] = substr($result, 1, -1);
						}
						$return[] = str_replace(['\\\'', '\\"'], ['\'', '"'], $result);
					}
					return $return;
				}
			}
		}
		return false;
	}
}
