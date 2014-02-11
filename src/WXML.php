<?php

/*
* @author Alexander Fedulin
* @date 2014-02-11
*/
function is_node_list($dom) {
	if (!($dom instanceof Traversable)) return false;
	if ($dom instanceof ArrayIterator) {
		$dom = iterator_to_array($dom);
	}
	return count(array_filter($dom, function($e) {
		return $e instanceof DOMNode;
	})) == count($dom);
}

function is_dom_element($dom) {
	return $dom instanceof DOMElement;
}

function strip_xml_version_tag($str) {
	return preg_replace('/<\?xml[^<>]*>/', '', $str);
}

class WXMLException extends Exception {
	public __construct($str) {
		parent::__construct('WXML: '.$str);
	}
}

class WXML implements Iterator {
	private $dom = null;
	private $registered_namespaces = null;
	
	public function __construct($str = null) {
		$this->dom = null;
		$this->registered_namespaces = array();
		if ($str == null) {
			// used for creation;
			$this->dom = new ArrayIterator(array(new DOMDocument("1.0", "utf-8")));
		} else if (is_string($str)) {
			$this->dom = new DOMDocument("1.0", "utf-8");
			if (!$this->dom->loadXML($str)) {
				throw new Exception('Cannont parse message as XML');
			}
			$this->dom = new ArrayIterator(array($this->dom));
		} else if (is_node_list($str)) {
			$this->dom = $str;
		} else {
			throw new WXMLException('constructor accepts string or node list');
		}
	}

	/*
	* Set $prefix to null if you wanna define default namespace.
	* null prefix is not used yet
	*/
	public function registerNamespace($namespaceURI, $prefix) {
		$this->registered_namespaces[$prefix] = $namespaceURI;
	}

	public function registerNS($uri, $prefix) {
		$this->registerNamespace($uri, $prefix);
	}

	public function setRegisteredNS($arr) {
		$this->registered_namespaces = $arr;
	}

	public function isEmpty() {
		return count($this->dom) == 0;
	}

	public static function parseTag($key) {
	    $ary = preg_split("/:/", $key, 2);

	    if (count($ary) == 1) {
	    	return array(null, $key);
	    } else {
	    	return $ary;
	    }
	}

	public function getPrefixNS($prefix) {
		if (is_null($prefix)) {
			return null;
		}

		if (!array_key_exists($prefix, $this->registered_namespaces)) {
			throw new WXMLException('uknown prefix '.$prefix.'. Unregistered prefix-namespaceURI pair.');
		}
		return $this->registered_namespaces[$prefix];
	}

	/*
	* @return array
	*/
	private function getFilteredChildren($key) {
		list($prefix, $tag_name) = self::parseTag($key);
		$nsURI = $this->getPrefixNS($prefix);

		$result = array();

		foreach($this->dom as $node) {
			foreach ($node->childNodes as $ch) {
				// remain only instance of DOMElement
				if (!is_dom_element($ch)) {
					continue;
				}
				
				// filter by nsURI-tag_name pair
				if (is_null($nsURI) && $ch->localName === $tag_name
					|| $ch->localName === $tag_name && $nsURI === $ch->namespaceURI) {
					$result[] = $ch;
				}
			}
		}
		return array_filter($result); // just to be sure that there is no empty objects
	}


	public function __get($key) {
		$result = new ArrayIterator($this->getFilteredChildren($key));
		$result = new WXML($result);
		$result->setRegisteredNS($this->registered_namespaces);
		return $result;
	}

	public static function getDocument($node) {
		return ($node instanceof DOMDocument) ? $node : $node->ownerDocument;
	}

	private function createNewElement($context_node, $key, $value) {
		if (!($context_node instanceof DOMNode)) {
			throw new WXMLException('createNewElement should only be used for DOM classes');
		}

		$dom_document = self::getDocument($context_node);
		list($prefix, $tag_name) = self::parseTag($key);
		$nsURI = $this->getPrefixNS($prefix);
		
		if ($nsURI) {
			$element = $dom_document->createElementNS($nsURI, $key);
		} else {
		    $element = $dom_document->createElement($key);
		}

		if (is_string($value)) {
			$element->nodeValue = $value;
		} else if ($value instanceof DOMNode) {
			$element->appendChild($value);
		} else if (is_null($value)) {
		} else if ($value instanceof WXML) {
			foreach ($value->importNodes($dom_document) as $node) {
				$element->appendChild($node);
			}
		} else {
			throw new WXMLException("undefined type of value (".get_class($value).") to set for element");
		}
		return $element;
	}

	// if $key == null, then consider text node
	private function appendNewElement($node, $key, $value) {
		if (!($node instanceof DOMNode)) {
			throw new WXMLException('appendNewElement should only be used for DOM classes');
		}

		if ($key) {
			$element = $this->createNewElement($node, $key, $value);
		} else {
			$dom_document = self::getDocument($node);
			if (is_string($value)) {
				$element = $dom_document->createTextNode($value);
			} else if ($value instanceof DOMNode) {
				$element = $value;
			} else if ($value instanceof WXML) {
				$element = $value->importNodes($dom_document);
			} else {
				throw new WXMLException("undefined type of value (".get_class($value).") to set for element");	
			}
		}
		if (is_array($element) || $element instanceof Traversable) {
			foreach ($element as $n) {
			 	$node->appendChild($n);
			}
		} else {
			$node->appendChild($element);
		}
	}

	public function __set($key, $value) {
		$children = $this->getFilteredChildren($key);
		if (count($children) > 0) {
			foreach ($children as $child) {
				while ($child->hasChildNodes()) {
					$child->removeChild($child->firstChild);
				}
				// append directly to child
				$this->appendNewElement($child, null, $value); 
			}
		} else {
			if (count($this->dom) == 0) {
				throw new WXMLException('cannot set value for key \''.$key.'\', the nodeset is empty');
			}
			foreach ($this->dom as $node) {
				$this->appendNewElement($node, $key, $value);
			}
		}
	}
	
	public function asXML($with_xml_version_tag = true) {
		$result = '';
		foreach ($this->dom as $node) {
			$with_xvt = !$result ? $with_xml_version_tag : false;
			if ($node instanceof WXML) {
				$part = $node->asXML();
			} else if ($node instanceof DOMDocument) {
				$part = $node->saveXML();
			} else {
				$part = $node->ownerDocument->saveXML($node);
			}

			if (!$with_xvt) {
				$part = strip_xml_version_tag($part);
			}
			$result .= $part; 
		}
		return $result;
	}

	public function importNodes($dom_document) {
		if (!($dom_document instanceof DOMDocument)) {
			throw new WXMLException('importNodes accept DOMDocument, which to assign DOMNodes to');
		}
		$result = array();
		
		foreach ($this->dom as $node) {
			if ($node instanceof DOMDocument) {
				$node = $node->documentElement;
			}
			$result[] = $dom_document->importNode($node, /*recursively with attributes */true);
		}
		return $result;
	}

	public function getChildren() {
		$result = array();
		foreach ($this->dom as $node) {
			foreach ($node->childNodes as $child) {
				if (!is_dom_element($child)) {
					continue;
				}
				$result[] = $child;
			}
		}
		
		$result = array_filter($result);
		$result = new ArrayIterator($result);
		$result = new WXML($result);
		$result->setRegisteredNS($this->registered_namespaces);
		return $result;
	}

	public function __toString() {
		$result = '';
		foreach ($this->dom as $node) {
			$result .= $node->nodeValue;
		}
		return $result;
	}

	public function current() {
		// don't look at me as if you think i am lazy
		return new WXML(new ArrayIterator(array($this->dom->current())));
	}

	public function key() {
		return $this->dom->key();
	}

	public function next() {
		return $this->dom->next();
	}

	public function rewind() {
		$this->dom->rewind();
	}

	public function valid() {
		return $this->dom->valid();
	}
}
