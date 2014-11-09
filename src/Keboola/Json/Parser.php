<?php

namespace Keboola\Json;

use Keboola\CsvTable\Table;
use Keboola\Temp\Temp;
use Monolog\Logger;
use Keboola\Json\Exception\JsonParserException as Exception;

/**
 * JSON to CSV data analyzer and parser/converter
 *
 * Use to convert JSON data into CSV file(s).
 * Creates multiple files if the JSON contains arrays
 * to store values of child nodes in a separate table,
 * linked by JSON_parentId column.

 * The analyze function loops through each row of an array (generally an array of results) and passes the row into analyzeRow() method. If the row only contains a string, it's stored in a "data" column, otherwise the row should usually be an object, so each of the object's variables will be used as a column name, and it's value analysed:
 * - if it's a scalar, it'll be saved as a value of that column.
 * - if it's another object, it'll be parsed recursively to analyzeRow(), with it's variable names prepended by current object's name
 *	- example:
 *			"parent": {
 *				"child" : "value1"
 *			}
 *			will result into a "parent_child" column with a string type of "value1"
 * - if it's an array, it'll be passed to analyze() to create a new table, linked by JSON_parentId
 *
 *
 * @author		Ondrej Vana (kachna@keboola.com)
 * @package    keboola/json-parser
 * @copyright  Copyright (c) 2014 Keboola Data Services (www.keboola.com)
 * @license    GPL-3.0
 * @link       https://github.com/keboola/php-jsonparser
 *
 * @TODO Ensure the column&table name don't exceed MySQL limits
 */
class Parser {
	/**
	 * Structures of analyzed data
	 * @var array
	 */
	protected $struct;

	/**
	 * Array of headers for each type
	 * @var array
	 */
	protected $headers = array();

	/**
	 * @var Table[]
	 */
	protected $csvFiles = array();

	/**
	 * True if analyze() was called
	 * @var bool
	 */
	protected $analyzed;

	/**
	 * Array of amounts of analyzed rows per data type
	 * @var array
	 */
	protected $rowsAnalyzed = array();

	/**
	 * @var int
	 * Use -1 to always analyze all data
	 */
	protected $analyzeRows;

	/** @var Cache */
	protected $cache;

	/** @var Logger */
	protected $log;

	/**
	 * @var Temp
	 */
	protected $temp;

	/**
	 * Mapping of types that can be "upgraded"
	 * @var array
	 */
	protected $typeUpgrades = array(
		array(
			"slave" => "integer",
			"master" => "double"
		)
	);

	/**
	 * @param Logger $logger
	 * @param array $struct should contain an array with previously cached results from analyze() calls (called automatically by process())
	 * @param int $analyzeRows determines, how many rows of data (counting only the "root" level of each Json)  will be analyzed [default 500, -1 for infinite]
	 */
	public function __construct(Logger $logger, array $struct = array(), $analyzeRows = 500)
	{
		$this->struct = $struct;
		$this->analyzeRows = $analyzeRows;

		$this->log = $logger;
	}

	/**
	 * @brief Parse an array of results. If their structure isn't known, it is stored, analyzed and then parsed upon retrieval by getCsvFiles()
	 * Expects an array of results in the $data parameter
	 * Checks whether the data needs to be analyzed, and either analyzes or parses it into $this->csvFiles[$type] ($type is polished to comply with SAPI naming requirements)
	 * If the data is analyzed, it is stored in Cache and **NOT PARSED** until $this->getCsvFiles() is called
	 *
	 * @TODO FIXME keep the order of data as on the input - try to parse data from Cache before parsing new data
	 *
	 * @param array $data
	 * @param string $type is used for naming the resulting table(s)
	 * @param string|array $parentId may be either a string, which will be saved in a JSON_parentId column, or an array with "column_name" => "value", which will name the column(s) by array key provided
	 *
	 * @return void
	 */
	public function process(array $data, $type = "root", $parentId = null)
	{
		// The analyzer wouldn't set the $struct and parse fails!
		if (empty($data) && empty($this->struct[$type])) {
			$this->log->log("warning", "Empty data set received for {$type}", array(
				"data" => $data,
				"type" => $type,
				"parentId" => $parentId
			));

			return;
		}

		// If we don't know the data (enough), store it in Cache, analyze, and parse when asked for it in getCsvFiles()
		if (
			!array_key_exists($type, $this->struct) ||
			$this->analyzeRows == -1 ||
			(!empty($this->rowsAnalyzed[$type]) && $this->rowsAnalyzed[$type] < $this->analyzeRows)
		) {
			if (empty($this->rowsAnalyzed[$type])) {
				$this->log->log("debug", "analyzing {$type}", array(
					"struct" => json_encode($this->struct),
					"analyzeRows" => $this->analyzeRows,
					"rowsAnalyzed" => json_encode($this->rowsAnalyzed)
				));
			}

			$this->rowsAnalyzed[$type] = empty($this->rowsAnalyzed[$type])
				? count($data)
				: ($this->rowsAnalyzed[$type] + count($data));

			if (empty($this->cache)) {
				$this->cache = new Cache();
			}

			$this->cache->store(array(
				"data" => $data,
				"type" => $type,
				"parentId" => $parentId
			));

			$this->analyze($data, $type);
		} else {
			$this->parse($data, $type, $parentId);
		}
	}

	/**
	 * Get header for a data type
	 * @param string $type Data type
	 * @param string|array $parent String with a $parentId or an array with $colName => $parentId
	 * @return array
	 */
	public function getHeader($type, $parent = false)
	{
		$header = array();
		if (is_scalar($this->struct[$type])) {
			$header[] = "data";
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
				$header[] = "JSON_parentId"; // TODO allow rename on root level/all levels separately - allow the parent to be an array of "parentColName" => id?
			}
		}

		// TODO set $this->headerNames[$type] = array_combine($validatedHeader, $header); & add a getHeaderNames fn()
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
		$newHeader = array();
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
			$nextSpace = strpos($name, " ", (strlen($name)-$remaining)) ? : strpos($name, "_", (strlen($name)-$remaining));

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
				"Json::parse() ran into an unknown data type {$type} - trying on-the-fly analysis",
				array(
					"data" => $data,
					"type" => $type,
					"parentId" => $parentId
				)
			);

			$this->analyze($data, $type);
		}

		if (empty($this->headers[$type])) {
			$this->headers[$type] = $this->getHeader($type, $parentId);
		}

		$safeType = $this->createSafeSapiName($type);
		if (empty($this->csvFiles[$safeType])) {
			$this->csvFiles[$safeType] = Table::create($safeType, $this->headers[$type], $this->getTemp());
			$this->csvFiles[$safeType]->addAttributes(array("fullDisplayName" => $type));
		}

		foreach($data as $row) {
			$parsed = $this->parseRow($row, $type);
			// ensure no fields are missing in CSV row (required in case an object is null and doesn't generate )
			$csvRow = array_replace(array_fill_keys($this->headers[$type], null), $parsed);
			if (!empty($parentId)) {
				if (is_array($parentId)) {
					$csvRow = array_merge($csvRow, $parentId);
				} else {
					$csvRow["JSON_parentId"] = $parentId;
				}
			}
			$this->csvFiles[$safeType]->writeRow($csvRow);
		}
	}

	/**
	 * Parse a single row
	 * If the row contains an array, it's recursively parsed
	 *
	 * @param mixed $dataRow Input data
	 * @param string $type
	 * @return array
	 */
	public function parseRow($dataRow, $type)
	{
		// in case of non-associative array of strings
		if (is_scalar($dataRow)) {
			return array("data" => $dataRow);
		} elseif ($this->struct[$type] == "NULL") {
			return array("data" => json_encode($dataRow));
		}

		$row = array();
		foreach($this->struct[$type] as $column => $dataType) {
			if (empty($dataRow->{$column})) {
				$row[$column] = null;
				continue;
			}

			switch ($dataType) {
				case "array":
					$row[$column] = $type . "_" . uniqid(); // TODO try to use parent's ID - somehow set it or detect it (not sure if that'd be unique)
					$this->parse($dataRow->{$column}, $type . "." . $column, $row[$column]);
					break;
				case "object":
					foreach($this->parseRow($dataRow->{$column}, $type . "." . $column) as $col => $val) {
						$row[$column . "_" . $col] = $val;
					}
					break;
				default:
					// If a column is an object/array while $struct expects a single column, log an error
					if (is_array($dataRow->{$column}) || is_object($dataRow->{$column})) {
						$jsonColumn = json_encode($dataRow->{$column});
						$realType = gettype($dataRow->{$column});
						$this->log->log(
							"ERROR",
							"Data parse error - unexpected '{$realType}'!",
							array(
								"data" => $jsonColumn,
								"row" => json_encode($dataRow),
								"column" => $column,
								"type" => $realType,
								"expected_type" => $dataType
							)
						);
						$row[$column] = $jsonColumn;
					} else {
						$row[$column] = $dataRow->{$column};
					}
					break;
			}
		}

		return $row;
	}

	/**
	 * @brief Analyze an array of input data and save the result in $this->struct
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
	 * @brief Analyze row of input data & create $this->struct
	 *
	 * @param mixed $row
	 * @param string $type
	 * @return void
	 */
	public function analyzeRow($row, $type)
	{
		// Analyze the current row
		if (!is_array($row) && !is_object($row)) {
			$struct = gettype($row);
		} else {
			foreach($row as $key => $field) {
				$fieldType = gettype($field);
				if ($fieldType == "object") {
					// Only assign the type if the object isn't empty
					if (get_object_vars($field) == array()) {
						continue;
					}

					$this->analyzeRow($field, $type . "." . $key);
				} elseif ($fieldType == "array") {
					$this->analyze($field, $type . "." . $key);
				}

				$struct[$key] = $fieldType;
			}
		}

		// Save the analysis result
		if (empty($this->struct[$type]) || $this->struct[$type] == "NULL") {
			// if we already know the row's types
			$this->struct[$type] = $struct;
		} elseif ($this->struct[$type] !== $struct) {
			// If the current row doesn't match the known structure
			$diff = array_diff_assoc($struct, $this->struct[$type]);
			// Walk through different fields
			foreach($diff as $diffKey => $diffVal) {
				if (empty($this->struct[$type][$diffKey]) || $this->struct[$type][$diffKey] == "NULL") {
					// Assign if the field is new
					$this->struct[$type][$diffKey] = $struct[$diffKey];
				} elseif (
					$struct[$diffKey] == "NULL"
					|| $struct[$diffKey] == $this->struct[$type][$diffKey]
					|| in_array(array(
							"slave" => $struct[$diffKey],
							"master" => $this->struct[$type][$diffKey]
						), $this->typeUpgrades)
				) {
					// If new type is null, unchanged, or the master of a master-slave pair,
					// do nothing and keep the originally stored type!
				} elseif (in_array(array(
						"slave" => $this->struct[$type][$diffKey],
						"master" => $struct[$diffKey]
					), $this->typeUpgrades)
				) {
					// When current values are in the "master-slave" array
					// and the "slave" is stored, upgrade type to the "master" type
					$this->struct[$type][$diffKey] = $struct[$diffKey];
				} elseif ($struct[$diffKey] != "NULL") {
					// Throw an Exception 'cos of a type mismatch
					$old = json_encode($this->struct[$type][$diffKey]);
					$new = json_encode($struct[$diffKey]);
					$e = new Exception("Unhandled type change from {$old} to {$new} in '{$type}.{$diffKey}'"); // 500
					$e->setData(array("newValue" => json_encode($row->{$diffKey})));
					throw $e;
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
		if(!empty($this->cache)) {
			while ($batch = $this->cache->getNext()) {
				$this->parse($batch["data"], $batch["type"], $batch["parentId"]);
			}
		}
		return $this->csvFiles;
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
	 * @brief Override the self-initialized Temp
	 * @param Temp $temp
	 */
	public function setTemp(Temp $temp)
	{
		$this->temp = $temp;
	}
}
