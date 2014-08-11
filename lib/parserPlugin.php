<?php
/**
 * @copyright  Copyright (C) 2013 - 2014 P.Alex (Alexandru Pruteanu)
 * @license    Licensed under the MIT License; see LICENSE
 */

/**
 * PHP class for parsing the HTML markup and
 * convert the data-* HTML5 attributes in Microdata or RDFa Lite 1.1 semantics
 */
class LibParserPlugin
{
	/**
	 * The type of semantic, will be an instance of LibMicrodata or LibRDFa
	 *
	 * @var null
	 */
	protected $handler = null;

	/**
	 * The suffix to search for when parsing the data-* HTML5 attribute
	 *
	 * @var array
	 */
	protected $suffix = array('sd');

	/**
	 * Initialize the class and setup the default $semantic, Microdata or RDFa
	 *
	 * @param   string  $semantic  The type of semantic to output, Microdata or RDFa
	 * @param   null    $suffix    The suffix to search for when parsing the data-* HTML5 attribute
	 */
	public function __construct($semantic, $suffix = null)
	{
		$this->semantic($semantic);

		if ($suffix)
		{
			$this->suffix($suffix);
		}
	}

	/**
	 * Return the $handler, which is an instance of LibMicrodata or LibRDFa
	 *
	 * @return LibStructuredData
	 */
	public function getHandler()
	{
		return $this->handler;
	}

	/**
	 * Setup the semantic to output, accepted types are 'Microdata' or 'RDFa'
	 *
	 * @param   string  $type  The type of semantic to output, accepted types are 'Microdata' or 'RDFa'
	 *
	 * @throws ErrorException
	 * @return object
	 */
	public function semantic($type)
	{
		// Sanitize the $type
		$type = trim(strtolower($type));

		// Available only 2 possible types of semantic, 'Microdata' or 'RDFa', otherwise throw an Exception
		switch ($type)
		{
			case 'microdata':
				$this->handler = new LibMicrodata;
				break;
			case 'rdfa':
				$this->handler = new LibRDFa;
				break;
			default:
				throw new ErrorException('There is no ' . $type . ' library available');
				break;
		}

		return $this;
	}

	/**
	 * Return the current type of semantic
	 *
	 * @return string
	 */
	public function getSemantic()
	{
		if ($this->handler instanceof LibMicrodata)
		{
			return 'microdata';
		}

		return 'rdfa';
	}

	/**
	 * Setup the $suffix to search for when parsing the data-* HTML5 attribute
	 *
	 * @param   mixed  $suffix  The suffix
	 *
	 * @return object
	 */
	public function suffix($suffix)
	{
		if (is_array($suffix))
		{
			while ($string = array_pop($suffix))
			{
				$this->addSuffix($string);
			}

			return $this;
		}

		$this->addSuffix($suffix);

		return $this;
	}

	/**
	 * Add a new $suffix to search for when parsing the data-* HTML5 attribute
	 *
	 * @param   string  $string  The suffix
	 *
	 * @return void
	 */
	protected function addSuffix($string)
	{
		$string = trim(strtolower((string) $string));

		// Avoid adding a duplicate suffix, also the suffix must be at least one character long
		if (array_search($string, $this->suffix) || empty($string))
		{
			return;
		}

		// Add the new suffix
		array_push($this->suffix, $string);
	}

	/**
	 * Remove a $suffix entry
	 *
	 * @param   string  $string  The suffix
	 *
	 * @return object
	 */
	public function removeSuffix($string)
	{
		$string = strtolower((string) $string);

		// Search and remove the suffix
		unset(
			$this->suffix[array_search($string, $this->suffix)]
		);

		return $this;
	}

	/**
	 * Return the current $suffix
	 *
	 * @return string
	 */
	public function getSuffix()
	{
		return $this->suffix;
	}

	/**
	 * Parse the unit param that will be used to setup the LibStructuredData class,
	 * e.g. giving the following: $string = 'Type.property';
	 * will return an array:
	 * array(
	 *     'type'     => 'Type,
	 *     'property' => 'property'
	 * );
	 *
	 * @param   string  $string  The string to parse
	 *
	 * @return  array
	 */
	protected static function parseParam($string)
	{
		// The default array
		$params = array(
			'type' => null,
			'property' => null
		);

		// Sanitize the $string and parse
		$string = explode('.', trim((string) $string));

		// If no matches found return the default array
		if (empty($string[0]))
		{
			return $params;
		}

		// If the first letter is uppercase, then it should be the 'Type', otherwise it should be the 'property'
		if (ctype_upper($string[0]{0}))
		{
			$params['type'] = $string[0];
		}
		else
		{
			$params['property'] = $string[0];

			return $params;
		}

		// If there is a string after the first '.dot', and it is lowercase, then it should be the 'property'
		if (count($string) > 1 && !empty($string[1]) && ctype_lower($string[1]{0}))
		{
			$params['property'] = $string[1];
		}

		return $params;
	}

	/**
	 * Parse the params that will be used to setup the LibStructuredData class,
	 * e.g giving the following: $string ='Type Type.property ... FType.fProperty gProperty';
	 * will return an array:
	 * array(
	 *     'setType'   => 'Type',
	 *     'fallbacks' => array(
	 *         'specialized' => array(
	 *             'Type'  => 'property',
	 *             'FType' => 'fproperty'
	 *             ...
	 *         ),
	 *         'global' => array(
	 *              ...
	 *             'gProperty'
	 *         )
	 *     )
	 * );
	 *
	 * @param   string  $string  The string to parse
	 *
	 * @return  array
	 */
	protected static function parseParams($string)
	{
		// The default array
		$params = array(
			'setType'   => null,
			'fallbacks' => array(
				'specialized' => array(),
				'global' => array()
			)
		);

		// Sanitize the $string, remove single and multiple whitespaces
		$string = trim(preg_replace('/\s+/', ' ', (string) $string));

		// Break the strings in small param chunks
		$string = explode(' ', $string);

		// Parse the small param chunks
		foreach ($string as $match)
		{
			$tmp      = self::parseParam($match);
			$type     = $tmp['type'];
			$property = $tmp['property'];

			// If a 'type' is available and there is no 'property', then it should be a 'setType'
			if ($type && !$property && !$params['setType'])
			{
				$params['setType'] = $type;
			}

			// If a 'property' is available and there is no 'type', then it should be a 'global' fallback
			if (!$type && $property)
			{
				array_push($params['fallbacks']['global'], $property);
			}

			// If both 'type' and 'property' is available, then it should be a 'specialized' fallback
			if ($type && $property && !array_key_exists($type, $params['fallbacks']['specialized']))
			{
				$params['fallbacks']['specialized'][$type] = $property;
			}
		}

		return $params;
	}

	/**
	 * Generate the Microdata or RDFa semantics
	 *
	 * @param   array  $params  The params used to setup the LibStructuredData library
	 *
	 * @return string
	 */
	protected function display($params)
	{
		$setType    = $params['setType'];

		// Specialized fallbacks
		$sFallbacks = $params['fallbacks']['specialized'];

		// Global fallbacks
		$gFallbacks = $params['fallbacks']['global'];

		// Set the current Type if available
		if ($setType)
		{
			$this->handler->setType($setType);
		}

		// If no properties available and there is a 'setType', return and display the scope
		if ($setType && !$sFallbacks && !$gFallbacks)
		{
			return $this->handler->displayScope();
		}

		$currentType = $this->handler->getType();

		// Check if there is an available 'specialized' fallback property for the current Type
		if ($sFallbacks && array_key_exists($currentType, $sFallbacks))
		{
			return $this->handler->property($sFallbacks[$currentType])->display('inline');
		}

		// Check if there is an available 'global' fallback property for the current Type
		if ($gFallbacks)
		{
			foreach ($gFallbacks as $property)
			{
				if (LibStructuredData::isPropertyInType($currentType, $property))
				{
					return $this->handler->property($property)->display('inline');
				}
			}
		}

		return $this->handler->display('inline');
	}

	/**
	 * Find the first data-suffix attribute match available in the node
	 * e.g. <tag data-one="suffix" data-two="suffix" /> will return 'one'
	 *
	 * @param   DOMElement  $node  The node to parse
	 *
	 * @return mixed
	 */
	protected function getNodeSuffix(DOMElement $node)
	{
		foreach ($this->suffix as $suffix)
		{
			if ($node->hasAttribute("data-$suffix"))
			{
				return $suffix;
			}
		}

		return null;
	}

	/**
	 * Parse the HTML and replace the data-* HTML5 attributes with Microdata or RDFa semantics
	 *
	 * @param   string  $html  The HTML to parse
	 *
	 * @return  string
	 */
	public function parse($html)
	{
		// Disable frontend error reporting
		libxml_use_internal_errors(true);

		// Create a new DOMDocument
		$doc = new DOMDocument;
		$doc->loadHTML($html);

		// Create a new DOMXPath, to make XPath queries
		$xpath = new DOMXPath($doc);

		// Create the query pattern
		$query = array();

		foreach ($this->suffix as $suffix)
		{
			array_push($query, "//*[@data-" . $suffix . "]");
		}

		// Search for the data-* HTML5 attributes
		$nodeList = $xpath->query(implode('|', $query));

		// Replace each match
		foreach ($nodeList as $node)
		{
			// Retrieve the params used to setup the LibStructuredData library
			$suffix    = $this->getNodeSuffix($node);
			$attribute = $node->getAttribute("data-" . $suffix);
			$params    = $this->parseParams($attribute);

			// Generate the Microdata or RDFa semantic
			$semantic  = $this->display($params);

			// Replace the data-* HTML5 attributes with Microdata or RDFa semantics
			$pattern   = '/data-' . $suffix . "=." . $attribute . "./";
			$html      = preg_replace($pattern, $semantic, $html, 1);
		}

		return $html;
	}
}
