<?php
namespace gimle\xml;

/**
 * Extend the basic SimpleXmlElement with some additional functionality.
 */
class SimpleXmlElement extends \SimpleXmlElement
{
	/**
	 * Get the first child object.
	 *
	 * @param SimpleXmlElement $ref Reference node, or null if current is to be used.
	 * @return mixed SimpleXmlElement or false if no children.
	 */
	public function firstChild (SimpleXmlElement $ref = null)
	{
		if ($ref === null) {
			return current($this->xpath('./*[1]'));
		}

		return current($ref->xpath('./*[1]'));
	}

	/**
	 * Get the following sibling of the current node.
	 *
	 * return mixed SimpleXmlElement if found, false if not.
	 */
	public function getNext ()
	{
		return current($this->xpath('following-sibling::*[1]'));
	}

	/**
	 * Get the next available numeric attribute.
	 *
	 * @param string $name The attribute name.
	 * @param string $type The element names to search.
	 * @param string $prefix Prefix before the numeric value.
	 *
	 * @return int
	 */
	public function getNextId ($name = 'id', $type = '*', $prefix = '')
	{
		$xpath = '//' . implode('/@' . $name . '|//', explode('|', $type)) . '/@' . $name;
		$ids = $this->xpath($xpath);
		$newId = 1;
		$list = array();
		if (!empty($ids)) {
			foreach ($ids as $id) {
				$id = (string)$id;
				if (substr($id, 0, strlen($prefix)) === $prefix) {
					if (ctype_digit(substr($id, strlen($prefix)))) {
						$list[] = (int)substr($id, strlen($prefix));
					}
				}
			}
		}
		if (!empty($list)) {
			$newId = max($list) + 1;
		}
		return $newId;
	}

	/**
	 * Get the parent of the current node.
	 *
	 * Can not get parent of root node.
	 *
	 * return mixed SimpleXmlElement if found, false if not (root does not have a parent).
	 */
	public function getParent ()
	{
		return current($this->xpath('parent::*'));
	}

	/**
	 * Get the preceding sibling of the current node.
	 *
	 * return mixed SimpleXmlElement if found, false if not.
	 */
	public function getPrevious ()
	{
		return current($this->xpath('preceding-sibling::*[1]'));
	}

	/**
	 * Insert after a given node.
	 *
	 * Can not insert after root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = after self. string = xpath to append, SimpleXmlElement = reference to append.
	 * @return void
	 */
	public function insertAfter ($element, $ref = null)
	{
		if (is_string($element)) {
			$element = new SimpleXmlElement($element);
		}

		if ($ref === null) {
			$dom = dom_import_simplexml($this);
		} elseif (is_string($ref)) {
			$sxml = $this->xpath($ref);
			if (empty($sxml)) {
				return false;
			}
			$dom = dom_import_simplexml($sxml[0]);
		} else {
			$dom = dom_import_simplexml($ref);
		}

		$insert = $dom->ownerDocument->importNode(dom_import_simplexml($element), true);
		if ($dom->nextSibling) {
			return $dom->parentNode->insertBefore($insert, $dom->nextSibling);
		} else {
			return $dom->parentNode->appendChild($insert);
		}
	}

	/**
	 * Insert before a given node.
	 *
	 * Can not insert before root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = before self. string = xpath to prepend, SimpleXmlElement = reference to prepend.
	 * @return void
	 */
	public function insertBefore ($element, $ref = null)
	{
		if (is_string($element)) {
			$element = new SimpleXmlElement($element);
		}

		if ($ref === null) {
			$dom = dom_import_simplexml($this);
		} elseif (is_string($ref)) {
			$sxml = $this->xpath($ref);
			if (empty($sxml)) {
				return false;
			}
			$dom = dom_import_simplexml($sxml[0]);
		} else {
			$dom = dom_import_simplexml($ref);
		}
		$insert = $dom->ownerDocument->importNode(dom_import_simplexml($element), true);

		return $dom->parentNode->insertBefore($insert, $dom);
	}

	/**
	 * Get the last child object.
	 *
	 * @param SimpleXmlElement $ref Reference node, or null if current is to be used.
	 * @return mixed SimpleXmlElement or false if no children.
	 */
	public function lastChild ($ref = null)
	{
		if ($ref === null) {
			return current($this->xpath('./*[last()]'));
		}

		return current($ref->xpath('./*[last()]'));
	}

	/**
	 * Adds a child element to the node.
	 *
	 * Adds a child element to the node and returns a SimpleXMLElement of the child.
	 *
	 * @param string $name The name of the child element to add.
	 * @param string $value If specified, the value of the child element.
	 *
	 * @return SimpleXmlElement The child added to the XML node.
	 */
	public function prependChild ($name, $value = false)
	{
		$dom = dom_import_simplexml($this);
		$new = $dom->ownerDocument->createElement($name, $value);
		$new = $dom->insertBefore($new, $dom->firstChild);

		return simplexml_import_dom($new, get_class($this));
	}

	/**
	 * Get a pretty text version if the xml.
	 *
	 * @return string
	 */
	public function pretty ()
	{
		$dom = dom_import_simplexml($this);
		$that = $dom;

		$dom = $dom->ownerDocument;
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$dom->loadXml($dom->saveXml($that));

		$res = $dom->saveXml($dom->documentElement);

		$res = preg_replace('/^  |\G  /m', "\t", $res);
		return $res;
	}

	/**
	 * Remove a given node.
	 *
	 * Can not remove root node.
	 *
	 * @param mixed $ref null = delete self. string = xpath to delete, SimpleXmlElement = reference to delete.
	 * @return void
	 */
	public function remove ($ref = null)
	{
		if ($ref === null) {
			$dom = dom_import_simplexml($this);
			$dom->parentNode->removeChild($dom);
			return;
		}

		if (is_string($ref)) {
			$nodes = $this->xpath($ref);
			foreach ($nodes as $node) {
				$dom = dom_import_simplexml($node);
				$dom->parentNode->removeChild($dom);
			}
			return;
		}

		$dom = dom_import_simplexml($this);
		$ref = dom_import_simplexml($ref);
		if ($ref->ownerDocument !== $dom->ownerDocument) {
			throw new \DOMException('The reference node does not come from the same document as the context node', DOM_WRONG_DOCUMENT_ERR);
		}
		$dom->removeChild($ref);
	}

	/**
	 * Rename a given node.
	 *
	 * Can not rename root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = rename self. string = xpath to rename, SimpleXmlElement = reference to rename.
	 * @return void
	 */
	public function rename ($name, $ref = null)
	{
		if ($ref === null) {
			$dom = dom_import_simplexml($this);
			$newNode = $dom->ownerDocument->createElement($name);
			if ($dom->attributes->length) {
				foreach ($dom->attributes as $attribute) {
					$newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
				}
			}
			while ($dom->firstChild) {
				$newNode->appendChild($dom->firstChild);
			}
			$dom->parentNode->replaceChild($newNode, $dom);
			return;
		}

		if (is_string($ref)) {
			$nodes = $this->xpath($ref);
			foreach ($nodes as $node) {
				$dom = dom_import_simplexml($this);
				$ref = dom_import_simplexml($node);
				if ($ref->ownerDocument !== $dom->ownerDocument) {
					throw new \DOMException('The reference node does not come from the same document as the context node', DOM_WRONG_DOCUMENT_ERR);
				}

				$newNode = $ref->ownerDocument->createElement($name);
				if ($ref->attributes->length) {
					foreach ($ref->attributes as $attribute) {
						$newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
					}
				}
				while ($ref->firstChild) {
					$newNode->appendChild($ref->firstChild);
				}
				$ref->parentNode->replaceChild($newNode, $ref);
			}
			return;
		}

		$dom = dom_import_simplexml($this);
		$ref = dom_import_simplexml($ref);
		if ($ref->ownerDocument !== $dom->ownerDocument) {
			throw new \DOMException('The reference node does not come from the same document as the context node', DOM_WRONG_DOCUMENT_ERR);
		}

		$newNode = $ref->ownerDocument->createElement($name);
		if ($ref->attributes->length) {
			foreach ($ref->attributes as $attribute) {
				$newNode->setAttribute($attribute->nodeName, $attribute->nodeValue);
			}
		}
		while ($ref->firstChild) {
			$newNode->appendChild($ref->firstChild);
		}
		$ref->parentNode->replaceChild($newNode, $ref);
	}

	/**
	 * Replace a given node.
	 *
	 * Can not replace root node.
	 *
	 * @param mixed $element SimpleXmlElement or xml string.
	 * @param mixed $ref null = replace self. string = xpath to replace, SimpleXmlElement = reference to replace.
	 * @return void
	 */
	public function replace ($element, $ref = null)
	{
		if (is_string($element)) {
			$element = new SimpleXmlElement($element);
		}
		if ($ref === null) {
			$dom = dom_import_simplexml($this);
			$import = $dom->ownerDocument->importNode(dom_import_simplexml($element), true);
			$dom->parentNode->replaceChild($import, $dom);
			return;
		}

		if (is_string($ref)) {
			$nodes = $this->xpath($ref);
			foreach ($nodes as $node) {
				$dom = dom_import_simplexml($node);
				$import = $dom->ownerDocument->importNode(dom_import_simplexml($element), true);
				$dom->parentNode->replaceChild($import, $dom);
			}
			return;
		}

		$dom = dom_import_simplexml($this);
		$ref = dom_import_simplexml($ref);
		if ($ref->ownerDocument !== $dom->ownerDocument) {
			throw new \DOMException('The reference node does not come from the same document as the context node', DOM_WRONG_DOCUMENT_ERR);
		}
		$import = $dom->ownerDocument->importNode(dom_import_simplexml($element), true);
		$dom->replaceChild($import, $ref);
	}

	/**
	 * Validates a document based on a schema.
	 *
	 * @param string $filename The path to the schema.
	 * @return bool
	 */
	public function schemaValidate ($filename)
	{
		$dom = dom_import_simplexml($this);
		return $dom->schemaValidate($filename);
	}

	/**
	 * Set a given node value.
	 *
	 * @param string $string The new value.
	 * @param mixed $ref null = set new value to self. string = xpath to set new value, SimpleXmlElement = reference to set new value.
	 * @return void
	 */
	public function value ($string, $ref = null)
	{
		if ($ref === null) {
			dom_import_simplexml($this)->nodeValue = $string;
			return;
		}

		if (is_string($ref)) {
			$nodes = $this->xpath($ref);
			foreach ($nodes as $node) {
				dom_import_simplexml($node)->nodeValue = $string;
			}
			return;
		}

		dom_import_simplexml($ref)->nodeValue = $string;
	}
}
