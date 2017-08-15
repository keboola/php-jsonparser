<?php

namespace Keboola\Json;

use Keboola\CsvTable\Table;
use Keboola\Temp\Temp;
use Keboola\Json\Exception\JsonParserException;
use Keboola\Json\Exception\NoDataException;
use Psr\Log\LoggerInterface;

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
 */
class Parser
{
    /**
     * Column name for an array of scalars
     */
    const DATA_COLUMN = 'data';

    /**
     * @var Table[]
     */
    private $csvFiles = [];

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Temp
     */
    private $temp;

    /**
     * @var array
     */
    private $primaryKeys = [];

    /**
     * @var Analyzer
     */
    private $analyzer;

    /**
     * @var Structure
     */
    private $structure;

    /**
     * Parser constructor.
     * @param Analyzer $analyzer
     * @param array $definitions should contain an array with previously
     *         cached results from analyze() calls (called automatically by process())
     */
    public function __construct(Analyzer $analyzer, array $definitions = [])
    {
        ini_set('serialize_precision', 17);
        $this->analyzer = $analyzer;
        $analyzer->getStructure()->load($definitions);
        $this->structure = $analyzer->getStructure();
        $this->temp = new Temp("json-parser");
        $this->cache = new Cache();
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
     * @throws NoDataException
     */
    public function process(array $data, $type = "root", $parentId = null)
    {
        if (empty($data) || $data == [null]) {
            throw new NoDataException("Empty data set received for '{$type}'", [
                "data" => $data,
                "type" => $type,
                "parentId" => $parentId
            ]);
        }

        $this->analyzer->analyzeData($data, $type);
        $this->structure->getHeaderNames();
        $this->cache->store(["data" => $data, "type" => $type, "parentId" => $parentId]);
    }

    /**
     * Parse data of known type
     *
     * @param array $data
     * @param NodePath $nodePath
     * @param string|array $parentId
     */
    private function parse(array $data, NodePath $nodePath, $parentId = null)
    {
        $parentId = $this->validateParentId($parentId);
        $csvFile = $this->createCsvFile($this->structure->getTypeFromNodePath($nodePath), $nodePath, $parentId);
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

            $csvRow = $this->parseRow($row, $nodePath, $parentCols);
            $csvFile->writeRow($csvRow->getRow());
        }
    }

    /**
     * Parse a single row
     * If the row contains an array, it's recursively parsed
     *
     * @param \stdClass $dataRow Input data
     * @param NodePath $nodePath
     * @param array $parentCols to inject parent columns, which aren't part of $this->struct
     * @param string $outerObjectHash Outer object hash to distinguish different parents in deep nested arrays
     * @return CsvRow
     */
    private function parseRow(
        \stdClass $dataRow,
        NodePath $nodePath,
        array $parentCols = [],
        $outerObjectHash = null
    ) {
        $headers2 = $this->getHeaderPath($nodePath, $parentCols);
        $csvRow = new CsvRow($headers2);

        // Generate parent ID for arrays
        $arrayParentId = $this->getPrimaryKeyValue(
            $dataRow,
            $nodePath,
            $outerObjectHash
        );

        $arr3 = $this->structure->getDefinitionsNodePath($nodePath);
        foreach (array_replace($arr3, $parentCols) as $column => $dataType) {
            $this->parseField($dataRow, $csvRow, $arrayParentId, $column, $dataType, $nodePath);
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
     * @param NodePath $nodePath
     */
    private function parseField(
        \stdClass $dataRow,
        CsvRow $csvRow,
        $arrayParentId,
        $column,
        $dataType,
        NodePath $nodePath
    ) {
        // A hack allowing access to numeric keys in object
        if (!isset($dataRow->{$column})
            && isset(json_decode(json_encode($dataRow), true)[$column])
        ) {
            $dataRow->{$column} = json_decode(json_encode($dataRow), true)[$column];
        }

        // skip empty objects & arrays to prevent creating empty tables or incomplete column names
        if (!isset($dataRow->{$column})
            || is_null($dataRow->{$column})
            || (empty($dataRow->{$column}) && !is_scalar($dataRow->{$column}))
        ) {
            // do not save empty objects to prevent creation of ["obj_name" => null]
            if ($dataType != 'object') {
                $safeColumn = $this->structure->getSingleValue($nodePath->addChild($column), 'headerNames');
                if ($safeColumn === null) {
                    $safeColumn = $this->structure->getSingleValue($nodePath, 'headerNames');
                }
                $csvRow->setValue($safeColumn, null);
            }

            return;
        }

        switch ($dataType) {
            case "array":
                if (!is_array($dataRow->{$column})) {
                    $dataRow->{$column} = [$dataRow->{$column}];
                }
                $sf = $this->structure->getSingleValue($nodePath->addChild($column), 'headerNames');
                $csvRow->setValue($sf, $arrayParentId);
                $this->parse($dataRow->{$column}, $nodePath->addChild($column)->addArrayChild(), $arrayParentId);
                break;
            case "object":
                $childRow = $this->parseRow($dataRow->{$column}, $nodePath->addChild($column), [], $arrayParentId);

                foreach ($childRow->getRow() as $key => $value) {
                    $csvRow->setValue($key, $value);
                }
                break;
            default:
                // If a column is an object/array while $struct expects a single column, log an error
                if (is_scalar($dataRow->{$column})) {
                    $sf = $this->structure->getSingleValue($nodePath->addChild($column), 'headerNames');
                    if ($sf === null) {
                        $sf = $this->structure->getSingleValue($nodePath, 'headerNames');
                    }
                    $csvRow->setValue($sf, $dataRow->{$column});
                } else {
                    $jsonColumn = json_encode($dataRow->{$column});

                    $this->analyzer->getLogger()->error(
                        "Data parse error in '{$column}' - unexpected '"
                            . gettype($dataRow->{$column}) . "' where '{$dataType}' was expected!",
                        [ "data" => $jsonColumn, "row" => json_encode($dataRow) ]
                    );
                    $sf = $this->structure->getSingleValue($nodePath->addChild($column), 'headerNames');
                    $csvRow->setValue($sf, $jsonColumn);
                }
                break;
        }
    }

    private function getHeaderPath(NodePath $nodePath, &$parent = false, $parentCheck = false)
    {
        $headers = [];
        $thisNodeName = $nodePath->getLast();
        $nodeData = $this->structure->getValue($nodePath);
        if ($nodeData['nodeType'] == 'scalar') {
            $headers[] = $nodeData['headerNames'];
        }
        if (is_array($parent) && !empty($parent)) {
            $nnodePath = $nodePath->popLast($thisNodeName);
            $nnodeData = $this->structure->getValue($nnodePath);
            $parentCheck = array_keys($parent);
            $trigChange = false;
            foreach ($parent as $key => $value) {
                if (!isset($nnodeData[$key]) && !isset($nodeData['[]'][$key]) && empty($nodeData[$key]['type'])
                    && empty($nodeData['[]'][$key]['type'])) {
                    // this is a WTF, but getHeaders is called for every row, so a header must be called only once
                    if ($nnodeData['nodeType'] == 'array') {
                        if (isset($nnodeData['[]'][$key])) {
                            // this means that there is a column with a same name as a column with parent name
                            $newColName = $key;
                            $i = 0;
                            while (isset($nnodeData['[]'][$newColName])) {
                                $newColName = $key . '_u' . $i;
                                $i++;
                            }
                            // rename the column in parent
                            $parent[$newColName] = $value;
                            unset($parent[$key]);
                            $key = $newColName;
                        }
                        $nnodeData['[]'][$key] = ['nodeType' => 'scalar', 'type' => 'parent'];
                    }
                    $trigChange = true;
                }
            }
            if ($trigChange) {
                $this->structure->saveNode($nnodePath, $nnodeData);
                $this->structure->getHeaderNames();
                $nodeData = $this->structure->getValue($nodePath);
            }
        }
        foreach ($nodeData as $nodeName => $data) {
            if (is_array($data) && ($data['nodeType'] == 'object')) {
                $pparent = false;
                $ch = $this->getHeaderPath($nodePath->addChild($nodeName), $pparent, $parentCheck);
                $headers = array_merge($headers, $ch);
            } else if (is_array($data)) {
                $headers[] = $data['headerNames'];
            }
        }
        return $headers;
    }

    /**
     * to allow saving a single type to different files
     *
     * @param string $type
     * @param NodePath $nodePath
     * @param $parentId
     * @return Table
     */
    private function createCsvFile($type, NodePath $nodePath, &$parentId)
    {
        if (empty($this->csvFiles[$type])) {
            $this->csvFiles[$type] = Table::create(
                $type,
                $this->getHeaderPath($nodePath, $parentId),
                $this->temp
            );
            $this->csvFiles[$type]->addAttributes(["fullDisplayName" => $type]);
            if (!empty($this->primaryKeys[$type])) {
                $this->csvFiles[$type]->setPrimaryKey($this->primaryKeys[$type]);
            }
        }

        return $this->csvFiles[$type];
    }

    /**
     * @param \stdClass $dataRow
     * @param NodePath $nodePath
     * @param string $outerObjectHash
     * @return string
     */
    private function getPrimaryKeyValue(\stdClass $dataRow, NodePath $nodePath, $outerObjectHash = null)
    {
        $safeColumn = $this->structure->getTypeFromNodePath($nodePath);
        if (!empty($this->primaryKeys[$safeColumn])) {
            $pk = $this->primaryKeys[$safeColumn];
            $pKeyCols = explode(',', $pk);
            $pKeyCols = array_map('trim', $pKeyCols);
            $values = [];
            foreach ($pKeyCols as $pKeyCol) {
                if (empty($dataRow->{$pKeyCol})) {
                    $values[] = md5(serialize($dataRow) . $outerObjectHash);
                    $this->analyzer->getLogger()->warning(
                        "Primary key for type '{$safeColumn}' was set to '{$pk}', but its column '{$pKeyCol}' does not exist! Using hash to link child objects instead.",
                        ['row' => $dataRow]
                    );
                } else {
                    $values[] = $dataRow->{$pKeyCol};
                }
            }

            return $nodePath->toCleanString() . "_" . join(";", $values);
        } else {
            return $nodePath->toCleanString() . '_' . md5(serialize($dataRow) . $outerObjectHash);
        }
    }

    /**
     * Ensure the parentId array is not multidimensional
     *
     * @param string|array $parentId
     * @return array
     * @throws JsonParserException
     */
    private function validateParentId($parentId)
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
        while ($batch = $this->cache->getNext()) {
            $this->parse($batch["data"], new NodePath([$batch['type'], '[]']), $batch["parentId"]);
        }
        return $this->csvFiles;
    }

    /**
     * @return Analyzer
     */
    public function getAnalyzer()
    {
        return $this->analyzer;
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
    public function setCacheMemoryLimit(int $limit)
    {
        $this->cache->setMemoryLimit($limit);
    }
}