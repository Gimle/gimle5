<?php
namespace gimle;

class ArrayUtils
{
	public static function stringToNestedArray ($key, $value, $separator = '.') {
		if (strpos($key, $separator) === false) {
			return array($key => $value);
		}
		$key = explode($separator, $key);
		$pre = array_shift($key);
		$return = array($pre => self::stringToNestedArray(implode($separator, $key), $value, $separator));
		return $return;
	}

	/**
	 * Merge two or more arrays recursivly and preserve keys.
	 *
	 * Values will overwrite previous array for every additional array passed to the method.
	 * Add the boolean value false to the end to have latest array control the order.
	 *
	 * @param array $array Variable list of arrays to recursively merge.
	 * @return array The merged array.
	 */
	public static function merge ($array) {
		$arrays = func_get_args();
		$reposition = false;
		if (is_bool($arrays[count($arrays) - 1])) {
			if ($arrays[count($arrays) - 1]) {
				$reposition = true;
			}
			array_pop($arrays);
		}
		if (count($arrays) > 1) {
			array_shift($arrays);
			foreach ($arrays as $array2) {
				if (!empty($array2)) {
					foreach ($array2 as $key => $val) {
						if (is_array($array2[$key])) {
							$array[$key] = ((isset($array[$key])) && (is_array($array[$key])) ? self::merge($array[$key], $array2[$key], $reposition) : $array2[$key]);
						}
						else {
							if ((isset($array[$key])) && ($reposition === true)) {
								unset($array[$key]);
							}
							$array[$key] = $val;
						}
					}
				}
			}
		}
		return $array;
	}
}
