<?php
namespace Keboola\Json;

use Monolog\Logger;

class Analyzer
{
	/**
	 * Structures of analyzed data
	 * @var Struct
	 */
	protected $struct;

	/**
	 * @var int
	 */
	protected $analyzeRows;

	/**
	 * @var Logger
	 */
	protected $log;

	public function __construct(Logger $logger, Struct $struct = null, $analyzeRows = -1)
	{
		$this->log = $logger;
		$this->struct = $struct;
		$this->analyzeRows = $analyzeRows;
	}

	/**
	 * Analyze an array of input data and save the result in $this->struct
	 *
	 * @param array $data
	 * @param string $type
	 * @return void
	 */
	public function analyze(array $data, $type)
	{
		foreach($data as $row) {
			$this->analyzeRow($row, $type);
		}
		$this->analyzed = true;
	}

	/**
	 * Analyze row of input data & create $this->struct
	 *
	 * @param mixed $row
	 * @param string $type
	 * @return void
	 */
	protected function analyzeRow($row, $type)
	{
		// Current row's structure
		$struct = [];

		// If the row is scalar, make it a {"data" => $value} object
		if (is_scalar($row) || is_null($row)) {
			$struct[Parser::DATA_COLUMN] = gettype($row);
			$row = (object) [Parser::DATA_COLUMN => $row];
		} elseif (is_object($row)) {
			// process each property of the object
			foreach($row as $key => $field) {
				$fieldType = gettype($field);

				if ($fieldType == "object") {
					// Only assign the type if the object isn't empty
					if ($this->isEmptyObject($field)) {
						continue;
					}

					$this->analyzeRow($field, $type . "." . $key);
				} elseif ($fieldType == "array") {
					$this->analyze($field, $type . "." . $key);
				}

				$struct[$key] = $fieldType;
			}
		} elseif ($this->nestedArrayAsJson && is_array($row)) {
			$this->log->log(
				"WARNING", "Unsupported array nesting in '{$type}'! Converting to JSON string.",
				['row' => $row]
			);
			$struct[Parser::DATA_COLUMN] = 'string';
			$row = (object) [Parser::DATA_COLUMN => json_encode($row)];
		} else {
			throw new JsonParserException("Unsupported data row in '{$type}'!", ['row' => $row]);
		}

		$this->getStruct()->add($type, $struct);
	}

	/**
	 * Recursively scans $object for non-empty objects
	 * Returns true if the object contains no scalar nor array
	 * @param \stdClass $object
	 * @return bool
	 */
	protected function isEmptyObject(\stdClass $object)
	{
		$vars = get_object_vars($object);
		if($vars == []) {
			return true;
		} else {
			foreach($vars as $var) {
				if (!is_object($var)) {
					return false;
				} else {
					return $this->isEmptyObject((object) $var);
				}
			}
		}
	}

	/**
	 * Read results of data analysis from $this->struct
	 * @return Struct
	 */
	public function getStruct()
	{
		if (empty($this->struct) || !($this->struct instanceof Struct)) {
			$this->struct = new Struct($this->log);
		}

		return $this->struct;
	}
}
