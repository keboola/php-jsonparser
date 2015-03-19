<?php

namespace Keboola\Json;

use Keboola\CsvTable\Table;
use Keboola\Temp\Temp;
use Monolog\Logger;
use Keboola\Json\Exception\JsonParserException;

/**
 * JSON to CSV data analyzer and parser/converter
 *
 * Use to convert JSON data into CSV file(s).
 * Creates multiple files if the JSON contains arrays
 * to store values of child nodes in a separate table,
 * linked by JSON_parentId column.

 * The analyze function loops through each row of an array (generally an array of results)
 * and passes the row into analyzeRow() method. If the row only contains a string,
 * it's stored in a "data" column, otherwise the row should usually be an object,
 * so each of the object's variables will be used as a column name, and it's value analysed:
 *
 * - if it's a scalar, it'll be saved as a value of that column.
 * - if it's another object, it'll be parsed recursively to analyzeRow(),
 * 		with it's variable names prepended by current object's name
 *	- example:
 *			"parent": {
 *				"child" : "value1"
 *			}
 *			will result into a "parent_child" column with a string type of "value1"
 * - if it's an array, it'll be passed to analyze() to create a new table, linked by JSON_parentId
 *
 *
 * @author		Ondrej Vana (kachna@keboola.com)
 * @package		keboola/json-parser
 * @copyright	Copyright (c) 2014 Keboola Data Services (www.keboola.com)
 * @license		GPL-3.0
 * @link		https://github.com/keboola/php-jsonparser
 *
 * @TODO Use a $file parameter to allow writing the same
 * 		data $type to multiple files
 * 		(ie. type "person" to "customer" and "user")
 */
class Parser {
	const DATA_COLUMN = 'data';
	/**
	 * Structures of analyzed data
	 * @var array
	 */
	protected $struct;

	/**
	 * Headers for each type
	 * @var array
	 */
	protected $headers = [];

	/**
	 * @var Table[]
	 */
	protected $csvFiles = [];

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
	 * @var int
	 */
	protected $analyzeRows;

	/**
	 * @var Cache
	 */
	protected $cache;

	/**
	 * @var Logger
	 */
	protected $log;

	/**
	 * @var Temp
	 */
	protected $temp;

	/**
	 * Mapping of types that can be "upgraded"
	 * @todo Create an object/method to handle this comparison and return the master
	 * @var array
	 */
	protected $typeUpgrades = [
		[
			"slave" => "integer",
			"master" => "double"
		]
	];

	/**
	 * @var array
	 */
	protected $scalars = [
		"integer",
		"double",
		// "float", // get_type returns "double"
		"string",
		"boolean",
		"NULL"
	];

	/**
	 * @var array
	 */
	protected $primaryKeys = [];

	/**
	 * @var bool
	 */
	protected $strict = false;

	/**
	 * @var bool
	 */
	protected $nestedArrayAsJson = false;

	/**
	 * @var bool
	 */
	protected $allowArrayStringMix = false;

	/**
	 * @var bool
	 */
	protected $autoUpgradeToArray = false;

	/**
	 * @param Logger $logger
	 * @param array $struct should contain an array with previously
	 * 		cached results from analyze() calls (called automatically by process())
	 * @param int $analyzeRows determines how many rows of data
	 * 		(counting only the "root" level of each Json)
	 * 		will be analyzed [default -1 for infinite/all]
	 */
	public function __construct(Logger $logger, array $struct = [], $analyzeRows = -1)
	{
		$this->struct = $struct;
		$this->analyzeRows = $analyzeRows;

		$this->log = $logger;
	}

	/**
	 * Parse an array of results. If their structure isn't known,
	 * it is stored, analyzed and then parsed upon retrieval by getCsvFiles()
	 * Expects an array of results in the $data parameter
	 * Checks whether the data needs to be analyzed,
	 * and either analyzes or parses it into $this->csvFiles[$type]
	 * ($type is polished to comply with SAPI naming requirements)
	 * If the data is analyzed, it is stored in Cache
	 * and **NOT PARSED** until $this->getCsvFiles() is called
	 *
	 * @TODO FIXME keep the order of data as on the input
	 * 	- try to parse data from Cache before parsing new data
	 * 	- sort of fixed by defaulting to -1 analyze default
	 *
	 * @param array $data
	 * @param string $type is used for naming the resulting table(s)
	 * @param string|array $parentId may be either a string,
	 * 		which will be saved in a JSON_parentId column,
	 * 		or an array with "column_name" => "value",
	 * 		which will name the column(s) by array key provided
	 *
	 * @return void
	 *
	 * @api
	 */
	public function process(array $data, $type = "root", $parentId = null)
	{
		// The analyzer wouldn't set the $struct and parse fails!
		if (empty($data) && empty($this->struct[$type])) {
			$this->log->log("warning", "Empty data set received for {$type}", [
				"data" => $data,
				"type" => $type,
				"parentId" => $parentId
			]);

			return;
		}

		// If we don't know the data (enough), store it in Cache,
		// analyze, and parse when asked for it in getCsvFiles()
		if (
			!array_key_exists($type, $this->struct) ||
			$this->analyzeRows == -1 ||
			(!empty($this->rowsAnalyzed[$type]) && $this->rowsAnalyzed[$type] < $this->analyzeRows)
		) {
			if (empty($this->rowsAnalyzed[$type])) {
				$this->log->log("debug", "Analyzing {$type}", [
// 					"struct" => json_encode($this->struct),
					"analyzeRows" => $this->analyzeRows,
					"rowsAnalyzed" => json_encode($this->rowsAnalyzed)
				]);
			}

			$this->rowsAnalyzed[$type] = empty($this->rowsAnalyzed[$type])
				? count($data)
				: ($this->rowsAnalyzed[$type] + count($data));

			if (empty($this->cache)) {
				$this->cache = new Cache();
			}

			$this->cache->store([
				"data" => $data,
				"type" => $type,
				"parentId" => $parentId
			]);

			$this->analyze($data, $type);
		} else {
			$this->parse($data, $type, $parentId);
		}
		// TODO return the files written into
	}

	/**
	 * Get header for a data type
	 * @param string $type Data type
	 * @param string|array $parent String with a $parentId or an array with $colName => $parentId
	 * @return array
	 */
	public function getHeader($type, $parent = false)
	{
		$header = [];
		if (is_scalar($this->struct[$type])) {
			$header[] = self::DATA_COLUMN;
		} else {
			foreach($this->struct[$type] as $column => $dataType) {
				if ($dataType == "object") {
					foreach($this->getHeader($type . "." . $column) as $col => $val) {
						$header[] = $column . "_" . $val;
					}
				} else {
					$header[] = $column;
				}
			}
		}

		if ($parent) {
			if (is_array($parent)) {
				$header = array_merge($header, array_keys($parent));
			} else {
				// TODO allow rename on root level/all levels separately
				// - allow the parent to be an array of "parentColName" => id?
				$header[] = "JSON_parentId";
			}
		}

		// TODO set $this->headerNames[$type] = array_combine($validatedHeader, $header);
		// & add a getHeaderNames fn()
		return $this->validateHeader($header);
	}

	/**
	 * Validate header column names to comply with MySQL limitations
	 *
	 * @param array $header Input header
	 * @return array
	 */
	protected function validateHeader(array $header)
	{
		$newHeader = [];
		foreach($header as $key => $colName) {
			$newName = $this->createSafeSapiName($colName);

			// prevent duplicates
			if (in_array($newName, $newHeader)) {
				$newHeader[$key] = md5($colName);
			} else {
				$newHeader[$key] = $newName;
			}
		}
		return $newHeader;
	}

	/**
	 * Validates a string for use as MySQL column/table name
	 *
	 * @param string $name A string to be validated
	 * @return string
	 */
	protected function createSafeSapiName($name)
	{
		if (strlen($name) > 64) {
			if(str_word_count($name) > 1 && preg_match_all('/\b(\w)/', $name, $m)) {
				$short = implode('',$m[1]);
			} else {
				$short = md5($name);
			}
			$short .= "_";
			$remaining = 64 - strlen($short);
			$nextSpace = strpos($name, " ", (strlen($name)-$remaining))
				? : strpos($name, "_", (strlen($name)-$remaining));

			if ($nextSpace !== false) {
				$newName = $short . substr($name, $nextSpace);
			} else {
				$newName = $short;
			}
		} else {
			$newName = $name;
		}

		$newName = preg_replace('/[^A-Za-z0-9-]/', '_', $newName);
		return trim($newName, "_");
	}

	/**
	 * Parse data of known type
	 *
	 * @param array $data
	 * @param string $type
	 * @param string|array $parentId
	 * @return void
	 * @see Parser::process()
	 */
	public function parse(array $data, $type, $parentId = null)
	{
		if (empty($this->struct[$type])) {
			// analyse instead of failing if the data is unknown!
			$this->log->log(
				"debug",
				"Json::parse() ran into an unknown data type '{$type}' - trying on-the-fly analysis",
				[
					"data" => $data,
					"type" => $type,
					"parentId" => $parentId
				]
			);

			$this->analyze($data, $type);
		}

		if (empty($this->headers[$type])) {
			$this->headers[$type] = $this->getHeader($type, $parentId);
		}

		// TODO add a $file parameter to use instead of $type
		// to allow saving a single type to different files
		$safeType = $this->createSafeSapiName($type);
		if (empty($this->csvFiles[$safeType])) {
			$this->csvFiles[$safeType] = Table::create(
				$safeType,
				$this->headers[$type],
				$this->getTemp()
			);
			$this->csvFiles[$safeType]->addAttributes(["fullDisplayName" => $type]);
		}

		if (!empty($parentId)) {
			if (is_array($parentId)) {
				// Ensure the parentId array is not multidimensional
				// TODO should be a different exception
				// - separate parse and "setup" exceptions
				if (count($parentId) != count($parentId, COUNT_RECURSIVE)) {
					throw new JsonParserException(
						'Error assigning parentId to a CSV file! $parentId array cannot be multidimensional.',
						[
							'parentId' => $parentId,
							'type' => $type,
							'dataRow' => $row
						]
					);
				}
			} else {
				$parentId = ['JSON_parentId' => $parentId];
			}
		} else {
			$parentId = [];
		}

		$parentCols = array_fill_keys(array_keys($parentId), "string");

		foreach($data as $row) {
			// in case of non-associative array of strings
			// prepare {"data": $value} objects for each row
			if (is_scalar($row) || is_null($row)) {
				$row = (object) [self::DATA_COLUMN => $row];
			} elseif ($this->nestedArrayAsJson && is_array($row)) {
				$row = (object) [self::DATA_COLUMN => json_encode($row)];
			}

			if (!empty($parentId)) {
				$row = (object) array_replace((array) $row, $parentId);
			}

			$parsed = $this->parseRow($row, $type, $parentCols);

			// ensure no fields are missing in CSV row
			// (required in case an object is null and doesn't generate all columns)
			$csvRow = array_replace(array_fill_keys($this->headers[$type], null), $parsed);

			$this->csvFiles[$safeType]->writeRow($csvRow);
		}
	}

	/**
	 * Parse a single row
	 * If the row contains an array, it's recursively parsed
	 *
	 * @param \stdClass $dataRow Input data
	 * @param string $type
	 * @param array $parentCols to inject parent columns, which aren't part of $this->struct
	 * @return array
	 */
	public function parseRow(\stdClass $dataRow, $type, array $parentCols = [])
	{
		if ($this->struct[$type] == "NULL") {
			$this->log->log(
				"WARNING", "Encountered data where 'NULL' was expected from previous analysis",
				[
					'type' => $type,
					'data' => $dataRow
				]
			);
			return [self::DATA_COLUMN => json_encode($dataRow)];
		}

		// Generate parent ID for arrays
		$arrayParentId = $this->getPrimaryKeyValue(
			$dataRow,
			$type
		);

		$row = [];
		foreach(array_merge($this->struct[$type], $parentCols) as $column => $dataType) {
			// TODO validate against header, or ideally save in $struct with already validated name.
			// Ideally make the $row an object that contains a $column => "header" map,
			// and assign data into it by a setter that looks at that header, then perhaps finally
			// return as an array. It would, therefore, contain even empty values and wouldn't
			// require the array_merge at getCsvFiles().
			// getHeader would save a k=>v[mapping struct column to a csv column name] instead
			// of just v, and then the row object would get that in constructor.
			$safeColumn = $this->createSafeSapiName($column);

			// skip empty objects & arrays to prevent creating empty tables
			// or incomplete column names
			if (
				!isset($dataRow->{$column})
				|| is_null($dataRow->{$column})
				|| (empty($dataRow->{$column}) && !is_scalar($dataRow->{$column}))
			) {
				// do not save empty objects to prevent creation of ["obj_name" => null]
				if ($dataType != 'object') {
					$row[$safeColumn] = null;
				}

				continue;
			}

			if ($this->autoUpgradeToArray && substr($dataType, 0, 11) == 'autoArrayOf') {
				if (!is_array($dataRow->{$column})) {
					$dataRow->{$column} = [$dataRow->{$column}];
				}
				$dataType = 'array';
			}

			if ($this->allowArrayStringMix && $dataType == 'stringOrArray') {
				$dataType = gettype($dataRow->{$column});
			}

			switch ($dataType) {
				case "array":
					$row[$safeColumn] = $arrayParentId;
					$this->parse($dataRow->{$column}, $type . "." . $column, $row[$safeColumn]);
					break;
				case "object":
					foreach($this->parseRow($dataRow->{$column}, $type . "." . $column) as $col => $val) {
						$row[$column . "_" . $col] = $val;
					}
					break;
				default:
					// If a column is an object/array while $struct expects a single column, log an error
					if (is_scalar($dataRow->{$column})) {
						$row[$safeColumn] = $dataRow->{$column};
					} else {
						$jsonColumn = json_encode($dataRow->{$column});

						$this->log->log(
							"ERROR",
							"Data parse error in '{$column}' - unexpected '"
								. gettype($dataRow->{$column})
								. "' where '{$dataType}' was expected!",
							[ "data" => $jsonColumn, "row" => json_encode($dataRow) ]
						);

						$row[$safeColumn] = $jsonColumn;
					}
					break;
			}
		}

		return $row;
	}

	/**
	 * @param \stdClass $dataRow
	 * @param string $type for logging
	 * @return string
	 */
	protected function getPrimaryKeyValue(\stdClass $dataRow, $type)
	{
		// Try to find a "real" parent ID
		if (!empty($this->primaryKeys[$this->createSafeSapiName($type)])) {
			$pk = $this->primaryKeys[$this->createSafeSapiName($type)];
			$pKeyCols = explode(',', $pk);
			$pKeyCols = array_map('trim', $pKeyCols);
			$values = [];
			foreach($pKeyCols as $pKeyCol) {
				if (empty($dataRow->{$pKeyCol})) {
					$values[] = md5(serialize($dataRow));
					$this->log->log(
						"WARNING", "Primary key for type '{$type}' was set to '{$pk}', but its column '{$pKeyCol}' does not exist! Using hash to link child objects instead.",
						[
							'row' => $dataRow,
							'hash' => $val
						]
					);
				} else {
					$values[] = $dataRow->{$pKeyCol};
				}
			}

			return $type . "_" . join(";", $values);
		} else {
			// Of no pkey is specified to get the real ID, use a hash of the row
			return $type . "_" . md5(serialize($dataRow));
		}
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
	public function analyzeRow($row, $type)
	{
		// If the row is scalar, make it a {"data" => $value} object
		if (is_scalar($row) || is_null($row)) {
			$struct[self::DATA_COLUMN] = gettype($row);
			$row = (object) [self::DATA_COLUMN => $row];
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
			$struct[self::DATA_COLUMN] = 'string';
			$row = (object) [self::DATA_COLUMN => json_encode($row)];
		} else {
			throw new JsonParserException("Unsupported data row in '{$type}'!", ['row' => $row]);
		}

		// Save the analysis result
		if (empty($this->struct[$type]) || $this->struct[$type] == "NULL") {
			// if we already know the row's types
			$this->struct[$type] = is_array($struct) ? $struct : [self::DATA_COLUMN => $struct];
		} elseif ($this->struct[$type] !== $struct) {
			// If the current row doesn't match the known structure
			$diff = array_diff_assoc($struct, $this->struct[$type]);
			// Walk through mismatched fields
			foreach($diff as $diffKey => $diffVal) {
				$this->struct[$type][$diffKey] = $this->updateStruct(
					empty($this->struct[$type][$diffKey]) ? null : $this->struct[$type][$diffKey],
					$struct[$diffKey],
					"{$type}.{$diffKey}",
					$row->{$diffKey}
				);
			}
		}
	}

	/**
	 * Return currently stored dataType with currently analyzed one,
	 * if it is a valid update
	 * @param string &$oldType
	 * @param string $newType
	 * @param string $type for logging
	 * @param string $currentRow for logging
	 * @return mixed $oldType|$newType
	 */
	protected function updateStruct($oldType, $newType, $type, $currentRow)
	{
		if (
			empty($oldType)
			|| $oldType == "NULL"
			|| in_array([
					"slave" => $oldType,
					"master" => $newType
				], $this->typeUpgrades)
		) {
			// Assign if the field is new
			// OR
			// When current values are in the "master-slave" array
			// and the "slave" is stored, upgrade type to the "master" type
			return $newType;
		} elseif (
			$newType == "NULL"
			|| $newType == $oldType
			|| in_array([
					"slave" => $newType,
					"master" => $oldType
				], $this->typeUpgrades)
			|| (
				!$this->strict
				&& in_array($newType, $this->scalars)
				&& in_array($oldType, $this->scalars)
			)
		) {
			// If new type is null, unchanged,
			// or the master of a master-slave pair,
			// or $this->strict is off AND both values are scalar
			// do nothing and keep the originally stored type!
			return $oldType;
		} elseif (
			$this->autoUpgradeToArray
			&& (
				(substr($oldType, 0, 11) == 'autoArrayOf' && substr($oldType, 11) == $newType)
				|| $oldType == 'array'
				|| $newType == 'array'
			)
		) {
			// TODO No support for scalars in non-strict mode (yet)
			if (substr($oldType, 0, 11) == 'autoArrayOf') {
				return $oldType;
			} elseif ($oldType == 'array') {
				return 'autoArrayOf' . $newType;
			} else {
				return 'autoArrayOf' . $oldType;
			}
		} elseif (
			$this->allowArrayStringMix
			&& (
				in_array($oldType, array_merge(['array', 'stringOrArray'], $this->scalars))
// 				&& (in_array($newType, $this->scalars) || $newType == 'array')
				&& $newType !== 'object'
			)
		) {
			if($oldType != 'stringOrArray') {
				$this->log->log(
					"WARNING",
					"An array was encountered where scalar '{$oldType}' was expected!",
					['row' => $currentRow]
				);
			}
			return 'stringOrArray';
		} elseif ($newType != "NULL") {
			// Throw a JsonParserException 'cos of a type mismatch
			$old = json_encode($oldType);
			$new = json_encode($newType);
			throw new JsonParserException(
				"Unhandled type change from {$old} to {$new} in '{$type}'",
				['newValue' => json_encode($currentRow)]
			);
		} else {
			// Now obviously this shouldn't ever possibly happen,
			// but if it does, let's have something to work with
			throw new JsonParserException(
				"Unexpected error occured while updating the structure tree!",
				[
					'oldType' => $oldType,
					'newType' => $newType,
					'type' => $type,
					'newValue' => json_encode($currentRow)
				]
			);
		}
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
	 * Returns an array of CSV files containing results
	 * @return Table[]
	 */
	public function getCsvFiles()
	{
		// parse what's in cache before returning results
		$this->processCache();

		foreach($this->primaryKeys as $table => $pk) {
			if (array_key_exists($table, $this->csvFiles)) {
				$this->csvFiles[$table]->setPrimaryKey($pk);
			}
		}

		return $this->csvFiles;
	}

	/**
	 * @return void
	 */
	protected function processCache()
	{
		if(!empty($this->cache)) {
			while ($batch = $this->cache->getNext()) {
				$this->parse($batch["data"], $batch["type"], $batch["parentId"]);
			}
		}
	}


	/**
	 * Read results of data analysis from $this->struct
	 * @return array
	 */
	public function getStruct()
	{
		return $this->struct;
	}

	/**
	 * Returns (bool) whether the analyzer analyzed anything in this instance
	 * @return bool
	 */
	public function hasAnalyzed()
	{
		return (bool) $this->analyzed;
	}

	/**
	 * Initialize $this->temp
	 * @return Temp
	 */
	protected function getTemp()
	{
		if(!($this->temp instanceof Temp)) {
			$this->temp = new Temp("ex-parser-data");
		}
		return $this->temp;
	}

	/**
	 * Override the self-initialized Temp
	 * @param Temp $temp
	 */
	public function setTemp(Temp $temp)
	{
		$this->temp = $temp;
	}

	/**
	 * @param array $pks
	 */
	public function addPrimaryKeys(array $pks)
	{
		$this->primaryKeys += $pks;
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

	/**
	 * If enabled, nested arrays will be saved as JSON strings instead
	 * @param bool $bool
	 */
	public function setNestedArrayAsJson($bool)
	{
		$this->nestedArrayAsJson = (bool) $bool;
	}

	/**
	 * If enabled, and an object contains an array where
	 * a string is expected, a "link" ID is saved in place
	 * of the string and a child CSV is created
	 * @param bool $bool
	 */
	public function setAllowArrayStringMix($allow)
	{
		$this->allowArrayStringMix = (bool) $allow;
	}

	/**
	 * If enabled, and an object contains an array where
	 * an array is not expected, a "link" ID is saved in place
	 * of the string and a child CSV is created.
	 * Takes priority over allowArrayStringMix
	 *
	 * This should **only** be used with $analyzeRows = -1
	 *
	 * Only enable this as a last resort if you cannot supply a JSON
	 * without inconsistent array/object conflicts
	 * @param bool $bool
	 * @experimental
	 */
	public function setAutoUpgradeToArray($enable)
	{
		if ($this->analyzeRows != -1) {
			throw new JsonParserException("autoUpgradeToArray can only be used with \$analyzeRows == -1 to prevent unexpected behavior (parsing before discovering a value should be an array)");
		}

		$this->log->log('warning', "Using automatic conversion of single values to arrays where required. Strict mode enabled.");

		$this->autoUpgradeToArray = (bool) $enable;
	}
}
