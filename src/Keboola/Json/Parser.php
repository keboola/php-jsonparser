<?php

namespace Keboola\Json;

use Keboola\CsvTable\Table;
use Keboola\Temp\Temp;
use Keboola\Json\Exception\JsonParserException;
use Keboola\Json\Exception\NoDataException;
use Psr\Log\LoggerInterface;
use SebastianBergmann\CodeCoverage\Report\Xml\Node;

/**
 * JSON to CSV data analyzer and parser/converter
 *
 * Use to convert JSON objects into CSV file(s).
 * Creates multiple files if the JSON contains arrays
 * to store values of child nodes in a separate table,
 * linked by JSON_parentId column.

 * The analyze function loops through each row of an array
 * (generally an array of results) and passes the row into analyzeRow() method.
 * If the row only contains a string, it's stored in a "data" column,
 * otherwise the row should usually be an object,
 * so each of the object's variables will be used as a column name,
 * and it's value analysed:
 *
 * - if it's a scalar, it'll be saved as a value of that column.
 * - if it's another object, it'll be parsed recursively to analyzeRow(),
 *         with it's variable names prepended by current object's name
 *    - example:
 *            "parent": {
 *                "child" : "value1"
 *            }
 *            will result into a "parent_child" column with a string type of "value1"
 * - if it's an array, it'll be passed to analyze() to create a new table,
 *      linked by JSON_parentId
 *
 *
 * @author        Ondrej Vana (kachna@keboola.com)
 * @package        keboola/json-parser
 * @copyright    Copyright (c) 2014 Keboola Data Services (www.keboola.com)
 * @license        GPL-3.0
 * @link        https://github.com/keboola/php-jsonparser
 *
 * @todo Use a $file parameter to allow writing the same
 *         data $type to multiple files
 *         (ie. type "person" to "customer" and "user")
 *
 */
class Parser
{
    /**
     * Column name for an array of scalars
     */
    const DATA_COLUMN = 'data';

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
     * @var Cache
     */
    protected $cache;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var Temp
     */
    protected $temp;

    /**
     * @var array
     */
    protected $primaryKeys = [];

    /**
     * @var Analyzer
     */
    protected $analyzer;

    /**
     * @var Struct
     */
    protected $struct;

    /**
     * @var Structure
     */
    private $structure;

    public function __construct(LoggerInterface $logger, Analyzer $analyzer, Struct $struct, Structure $structure)
    {
        ini_set('serialize_precision', 17);
        $this->log = $logger;
        $this->analyzer = $analyzer;
        $this->struct = $struct;
        $this->structure = $structure;
    }

    /**
     * @param LoggerInterface $logger
     * @param array $definitions should contain an array with previously
     *         cached results from analyze() calls (called automatically by process())
     * @param int $analyzeRows determines how many rows of data
     *         (counting only the "root" level of each Json)
     *         will be analyzed [default -1 for infinite/all]
     * @return Parser
     */
    public static function create(LoggerInterface $logger, array $definitions = [], $analyzeRows = -1)
    {
        $struct = new Struct($logger);
        $struct->load($definitions);
        $structure = new Structure();
        $analyzer = new Analyzer($logger, $struct, $structure, $analyzeRows);

        return new static($logger, $analyzer, $struct, $structure);
    }

    /**
     * Analyze and store an array of data for parsing.
     * The analysis is done immediately, based on the analyzer settings,
     * then the data is stored using \Keboola\Json\Cache and parsed
     * upon retrieval using getCsvFiles().
     *
     * @param array $data
     * @param string $type is used for naming the resulting table(s)
     * @param string|array $parentId may be either a string,
     *         which will be saved in a JSON_parentId column,
     *         or an array with "column_name" => "value",
     *         which will name the column(s) by array key provided
     * @return void
     * @throws NoDataException
     * @api
     */
    public function process(array $data, $type = "root", $parentId = null)
    {
        // The analyzer wouldn't set the $struct and parse fails!
        if ((empty($data) || $data == [null]) && !$this->struct->hasDefinitions($type)) {
            throw new NoDataException("Empty data set received for '{$type}'", [
                "data" => $data,
                "type" => $type,
                "parentId" => $parentId
            ]);
        }

        // Log it here since we shouldn't log children analysis
        /*
        if (empty($this->analyzer->getRowsAnalyzed()[$type])) {
            $this->log->debug("Analyzing {$type}", [
                "rowsAnalyzed" => $this->analyzer->getRowsAnalyzed(),
                "rowsToAnalyze" => count($data)
            ]);
        }
        */

     //   if (empty($this->analyzer->getRowsAnalyzed()[$type])) {
            $this->analyzer->analyze($data, $type);
            $this->analyzer->analyzeData($data, $type);
    //    }
        //$this->structure = $this->analyzer->getStructure();

        $this->getCache()->store([
            "data" => $data,
            "type" => $type,
            "parentId" => $parentId
        ]);
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
    public function parse(array $data, $type, NodePath $nodePath, $parentId = null)
    {

        if (!$this->analyzer->isAnalyzed($type)
            && (empty($this->analyzer->getRowsAnalyzed()[$type])
            || $this->analyzer->getRowsAnalyzed()[$type] < count($data))
        ) {
            // analyse instead of failing if the data is unknown!
            $this->log->debug(
                "Trying to parse an unknown data type '{$type}'. Trying on-the-fly analysis",
                [
                    "data" => $data,
                    "type" => $type,
                    "parentId" => $parentId
                ]
            );

            $this->analyzer->analyze($data, $type);
            $this->analyzer->analyzeData($data, $type);
        }

        $parentId = $this->validateParentId($parentId);

        $csvFile = $this->createCsvFile($type, $nodePath, $parentId);

        $parentCols = array_fill_keys(array_keys($parentId), "string");

        foreach ($data as $row) {
            // in case of non-associative array of strings
            // prepare {"data": $value} objects for each row
            if (is_scalar($row) || is_null($row)) {
                $row = (object) [self::DATA_COLUMN => $row];
            } elseif ($this->analyzer->getNestedArrayAsJson() && is_array($row)) {
                $row = (object) [self::DATA_COLUMN => json_encode($row)];
            }

            // Add parentId to each row
            if (!empty($parentId)) {
                $row = (object) array_replace((array) $row, $parentId);
            }

            $csvRow = $this->parseRow($row, $type, $nodePath, $parentCols);

            $csvFile->writeRow($csvRow->getRow());
        }
    }

    /**
     * Parse a single row
     * If the row contains an array, it's recursively parsed
     *
     * @param \stdClass $dataRow Input data
     * @param string $type
     * @param array $parentCols to inject parent columns, which aren't part of $this->struct
     * @param string $outerObjectHash Outer object hash to distinguish different parents in deep nested arrays
     * @return CsvRow
     */
    protected function parseRow(
        \stdClass $dataRow,
        $type,
        NodePath $nodePath,
        array $parentCols = [],
        $outerObjectHash = null
    ) {
        // move back out to parse/switch if it causes issues
        $csvRow = new CsvRow($this->getHeader($type, $nodePath, $parentCols));

        // Generate parent ID for arrays
        $arrayParentId = $this->getPrimaryKeyValue(
            $dataRow,
            $type,
            $outerObjectHash
        );

        $arr = $this->structure->getDefinitions($type);
        $arr2 = $this->struct->getDefinitions($type);
        $arr3 = $this->structure->getDefinitionsNodePath($nodePath);
        foreach (array_replace($arr, $parentCols) as $column => $dataType) {
            $this->parseField($dataRow, $csvRow, $arrayParentId, $column, $dataType, $type, $nodePath);
        }

        return $csvRow;
    }

    /**
     * Handle the actual write to CsvRow
     * @param \stdClass $dataRow
     * @param CsvRow $csvRow
     * @param string $arrayParentId
     * @param string $column
     * @param string $dataType
     * @param string $type
     * @return void
     */
    protected function parseField(
        \stdClass $dataRow,
        CsvRow $csvRow,
        $arrayParentId,
        $column,
        $dataType,
        $type,
        NodePath $nodePath
    ) {
        // TODO safeColumn should be associated with $this->struct[$type]
        // (and parentCols -> create in parse() where the arr is created)
        // Actually, the csvRow should REALLY have a pointer to the real name (not validated),
        // perhaps sorting the child columns on its own?
        // (because keys in struct don't contain child objects)
        $safeColumn = $this->createSafeName($column);

        // A hack allowing access to numeric keys in object
        if (!isset($dataRow->{$column})
            && isset(json_decode(json_encode($dataRow), true)[$column])
        ) {
            $dataRow->{$column} = json_decode(json_encode($dataRow), true)[$column];
        }

        // skip empty objects & arrays to prevent creating empty tables
        // or incomplete column names
        if (!isset($dataRow->{$column})
            || is_null($dataRow->{$column})
            || (empty($dataRow->{$column}) && !is_scalar($dataRow->{$column}))
        ) {
            // do not save empty objects to prevent creation of ["obj_name" => null]
            if ($dataType != 'object') {
                $csvRow->setValue($safeColumn, null);
            }

            return;
        }

        if ($dataType == "NULL") {
            // Throw exception instead? Any usecase? TODO get rid of it maybe?
            $this->log->warning(
                "Encountered data where 'NULL' was expected from previous analysis",
                [
                    'type' => $type,
                    'data' => $dataRow
                ]
            );

            $csvRow->setValue($column, json_encode($dataRow));
            return;
        }

        if ($this->struct->isArrayOf($dataType)) {
            if (!is_array($dataRow->{$column})) {
                $dataRow->{$column} = [$dataRow->{$column}];
            }
            $dataType = 'array';
        }

        switch ($dataType) {
            case "array":
                if (!is_array($dataRow->{$column})) {
                    $dataRow->{$column} = [$dataRow->{$column}];
                }
                $csvRow->setValue($safeColumn, $arrayParentId);
                $this->parse($dataRow->{$column}, $type . "." . $column, $nodePath->addArrayChild()->addChild($column), $arrayParentId);
                break;
            case "object":
                $childRow = $this->parseRow($dataRow->{$column}, $type . "." . $column, $nodePath->addChild($column), [], $arrayParentId);

                foreach ($childRow->getRow() as $key => $value) {
                    // FIXME createSafeName is duplicated here
                    $csvRow->setValue($this->createSafeName($safeColumn . '_' . $key), $value);
                }
                break;
            default:
                // If a column is an object/array while $struct expects a single column, log an error
                if (is_scalar($dataRow->{$column})) {
                    $csvRow->setValue($safeColumn, $dataRow->{$column});
                } else {
                    $jsonColumn = json_encode($dataRow->{$column});

                    /*
                     * todo

                    $this->log->error(
                        "Data parse error in '{$column}' - unexpected '"
                            . $this->analyzer->getType($dataRow->{$column})
                            . "' where '{$dataType}' was expected!",
                        [ "data" => $jsonColumn, "row" => json_encode($dataRow) ]
                    );
                    */
                    $csvRow->setValue($safeColumn, $jsonColumn);
                }
                break;
        }
    }

    /**
     * Get header for a data type
     * @param string $type Data type
     * @param string|array|bool $parent String with a $parentId or an array with $colName => $parentId
     * @return array
     */
    protected function getHeader($type, NodePath $nodePath, $parent = false)
    {
        $header = [];

        $arr = $this->structure->getDefinitions($type);
        $arr2 = $this->struct->getDefinitions($type);
        $arr3 = $this->structure->getDefinitionsNodePath($nodePath);
        foreach ($arr as $column => $dataType) {
            if ($dataType == "object") {
                foreach ($this->getHeader($type . "." . $column, $nodePath->addChild($column)) as $val) {
                    // FIXME this is awkward, the createSafeName shouldn't need to be used twice
                    // (here and in validateHeader again)
                    // Is used to trim multiple "_" in column name before appending
                    $header[] = $this->createSafeName($column) . "_" . $val;
                }
            } else {
                $header[] = $column;
            }
        }

        if ($parent) {
            if (is_array($parent)) {
                $header = array_merge($header, array_keys($parent));
            } else {
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
        foreach ($header as $key => $colName) {
            $newName = $this->createSafeName($colName);

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
     * @todo Could use just a part of the md5 hash
     */
    protected function createSafeName($name)
    {
        if (strlen($name) > 64) {
            if (str_word_count($name) > 1 && preg_match_all('/\b(\w)/', $name, $m)) {
                // Create an "acronym" from first letters
                $short = implode('', $m[1]);
            } else {
                $short = md5($name);
            }
            $short .= "_";
            $remaining = 64 - strlen($short);
            $nextSpace = strpos($name, " ", (strlen($name)-$remaining))
                ? : strpos($name, "_", (strlen($name)-$remaining));

            $newName = $nextSpace === false
                ? $short
                : $short . substr($name, $nextSpace);
        } else {
            $newName = $name;
        }

        $newName = preg_replace('/[^A-Za-z0-9-]/', '_', $newName);
        return trim($newName, "_");
    }

    /**
     * @todo Add a $file parameter to use instead of $type
     * to allow saving a single type to different files
     *
     * @param string $type
     * @return Table
     */
    protected function createCsvFile($type, NodePath $nodePath, $parentId)
    {
        if (empty($this->headers[$type])) {
            $this->headers[$type] = $this->getHeader($type, $nodePath, $parentId);
        }

        $safeType = $this->createSafeName($type);
        if (empty($this->csvFiles[$safeType])) {
            $this->csvFiles[$safeType] = Table::create(
                $safeType,
                $this->headers[$type],
                $this->getTemp()
            );
            $this->csvFiles[$safeType]->addAttributes(["fullDisplayName" => $type]);
            if (!empty($this->primaryKeys[$safeType])) {
                $this->csvFiles[$safeType]->setPrimaryKey($this->primaryKeys[$safeType]);
            }
        }

        return $this->csvFiles[$safeType];
    }

    /**
     * @param \stdClass $dataRow
     * @param string $type for logging
     * @param string $outerObjectHash
     * @return string
     */
    protected function getPrimaryKeyValue(\stdClass $dataRow, $type, $outerObjectHash = null)
    {
        // Try to find a "real" parent ID
        if (!empty($this->primaryKeys[$this->createSafeName($type)])) {
            $pk = $this->primaryKeys[$this->createSafeName($type)];
            $pKeyCols = explode(',', $pk);
            $pKeyCols = array_map('trim', $pKeyCols);
            $values = [];
            foreach ($pKeyCols as $pKeyCol) {
                if (empty($dataRow->{$pKeyCol})) {
                    $values[] = md5(serialize($dataRow) . $outerObjectHash);
                    $this->log->warning(
                        "Primary key for type '{$type}' was set to '{$pk}', but its column '{$pKeyCol}' does not exist! Using hash to link child objects instead.",
                        ['row' => $dataRow]
                    );
                } else {
                    $values[] = $dataRow->{$pKeyCol};
                }
            }

            return $type . "_" . join(";", $values);
        } else {
            // Of no pkey is specified to get the real ID, use a hash of the row
            return $type . "_" . md5(serialize($dataRow) . $outerObjectHash);
        }
    }

    /**
     * Ensure the parentId array is not multidimensional
     *
     * @param string|array $parentId
     * @return array
     * @throws JsonParserException
     */
    protected function validateParentId($parentId)
    {
        if (!empty($parentId)) {
            if (is_array($parentId)) {
                if (count($parentId) != count($parentId, COUNT_RECURSIVE)) {
                    throw new JsonParserException(
                        'Error assigning parentId to a CSV file! $parentId array cannot be multidimensional.',
                        [
                            'parentId' => $parentId
                        ]
                    );
                }
            } else {
                $parentId = ['JSON_parentId' => $parentId];
            }
        } else {
            $parentId = [];
        }

        return $parentId;
    }

    /**
     * Returns an array of CSV files containing results
     * @return Table[]
     */
    public function getCsvFiles()
    {
        // parse what's in cache before returning results
        $this->processCache();

        return $this->csvFiles;
    }

    /**
     * @return Cache
     */
    protected function getCache()
    {
        if (empty($this->cache)) {
            $this->cache = new Cache();
        }

        return $this->cache;
    }

    /**
     * @return void
     */
    public function processCache()
    {
        if (!empty($this->cache)) {
            while ($batch = $this->cache->getNext()) {
                $this->parse($batch["data"], $batch["type"], new NodePath([$batch['type'], '[]']), $batch["parentId"]);
            }
        }
    }

    /**
     * @return Struct
     */
    public function getStruct()
    {
        return $this->struct;
    }

    /**
     * Version of $struct array used in parser
     * @return double
     * @deprecated use Struct::getStructVersion()
     */
    public function getStructVersion()
    {
        return $this->struct->getStructVersion();
    }

    /**
     * Returns (bool) whether the analyzer analyzed anything in this instance
     * @return bool
     * @deprecated
     */
    public function hasAnalyzed()
    {
        return !empty($this->getAnalyzer()->getRowsAnalyzed());
    }

    /**
     * @return Analyzer
     */
    public function getAnalyzer()
    {
        return $this->analyzer;
    }

    /**
     * Initialize $this->temp
     * @return Temp
     */
    protected function getTemp()
    {
        if (!($this->temp instanceof Temp)) {
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
     * @throws JsonParserException
     */
    public function addPrimaryKeys(array $pks)
    {
        if (!empty($this->csvFiles)) {
            throw new JsonParserException('"addPrimaryKeys" must be used before any data is parsed');
        }

        $this->primaryKeys += $pks;
    }

    /**
     * Set maximum memory used before Cache starts using php://temp
     * @param string|int $limit
     */
    public function setCacheMemoryLimit($limit)
    {
        return $this->getCache()->setMemoryLimit($limit);
    }
}