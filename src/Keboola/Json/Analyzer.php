<?php
namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;
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
	 * @var bool
	 */
	protected $strict = false;

	/**
	 * True if analyze() was called
	 * @var bool
	 */
	protected $analyzed;

	/**
	 * Counts of analyzed rows per data type
	 * @var array
	 */
	protected $rowsAnalyzed = [];

	/**
	 * @var bool
	 */
	protected $nestedArrayAsJson = false;

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
		if ($this->isAnalyzed($type) || empty($data)) {
			return false;
		}

		$this->rowsAnalyzed[$type] = empty($this->rowsAnalyzed[$type])
			? count($data)
			: ($this->rowsAnalyzed[$type] + count($data));

		$rowType = $this->getArrayType($type);
		foreach($data as $row) {
			$newType = $this->analyzeRow($row, $type);
			if (
				!is_null($rowType)
				&& $newType != $rowType
				&& $newType != 'NULL'
				&& $rowType != 'NULL'
			) {
				throw new JsonParserException("Data array in '{$type}' contains incompatible data types '{$rowType}' and '{$newType}'!");
			}
			$rowType = $newType;
		}
		$this->analyzed = true;

		return $rowType;
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

		$rowType = $this->getType($row);

		// If the row is scalar, make it a {"data" => $value} object
		if (is_scalar($row) || is_null($row)) {
			$struct[Parser::DATA_COLUMN] = $this->getType($row);
			$row = (object) [Parser::DATA_COLUMN => $row];
		} elseif (is_object($row)) {
			// process each property of the object
			foreach($row as $key => $field) {
				$fieldType = $this->getType($field);

				if ($fieldType == "object") {
					// Only assign the type if the object isn't empty
					if ($this->isEmptyObject($field)) {
						continue;
					}

					$this->analyzeRow($field, $type . "." . $key);
				} elseif ($fieldType == "array") {
					$arrayType = $this->analyze($field, $type . "." . $key);
					if (false !== $arrayType) {
						$fieldType = 'arrayOf' . $arrayType;
					} else {
						$fieldType = 'NULL';
					}
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

		return $rowType;
	}

	protected function getArrayType($type)
	{
		$separator = strrpos($type, '.');
		if (!$separator) {
			$parent = $type;
			$child = Parser::DATA_COLUMN;
		} else {
			$parent = substr($type, 0, $separator);
			$child = substr($type, $separator + 1);
		}

		if ($this->getStruct()->hasType($parent, $child)) {
			$array = $this->getStruct()->getType($parent, $child);
			return str_replace('arrayOf', '', $array);
		} else {
			return null;
		}
	}

	public function getType($var)
	{
		return $this->strict ? gettype($var) :
			(is_scalar($var) ? 'scalar' : gettype($var));
	}

	/**
	 * Check whether the data type has been analyzed (enough)
	 * @param string $type
	 * @return bool
	 */
	public function isAnalyzed($type)
	{
// 		return !(!$this->getStruct()->hasDefinitions($type) ||
// 			$this->analyzeRows == -1 ||
// 			(!empty($this->rowsAnalyzed[$type]) && $this->rowsAnalyzed[$type] < $this->analyzeRows));

		return $this->getStruct()->hasDefinitions($type)
			&& $this->analyzeRows != -1
			&& !empty($this->rowsAnalyzed[$type])
			&& $this->rowsAnalyzed[$type] >= $this->analyzeRows;
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

	/**
	 * @return array
	 */
	public function getRowsAnalyzed()
	{
		return $this->rowsAnalyzed;
	}

	/**
	 * If enabled, nested arrays will be saved as JSON strings instead
	 * @param bool $bool
	 */
	public function setNestedArrayAsJson($bool)
	{
		$this->nestedArrayAsJson = (bool) $bool;
	}

	/**
	 * @return bool
	 */
	public function getNestedArrayAsJson()
	{
		return $this->nestedArrayAsJson;
	}

	/**
	 * Set whether scalars are treated as compatible
	 * within a field (default = false -> compatible)
	 * @param bool $strict
	 */
	public function setStrict($strict)
	{
		$this->strict = (bool) $strict;
	}
}
