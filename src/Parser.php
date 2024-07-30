<?php

declare(strict_types=1);

namespace Keboola\Json;

use Keboola\CsvTable\Table;
use Keboola\Json\Exception\JsonParserException;
use Keboola\Json\Exception\NoDataException;
use Keboola\Temp\Temp;
use stdClass;

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
     * @var Table[]
     */
    private array $csvFiles = [];

    private Cache $cache;

    private Temp $temp;

    /** @var string[]  */
    private array $primaryKeys = [];

    private Analyzer $analyzer;

    private Structure $structure;

    /**
     * @param array $definitions should contain an array with previously
     *         cached results from analyze() calls (called automatically by process())
     */
    public function __construct(Analyzer $analyzer, array $definitions = [])
    {
        ini_set('serialize_precision', '17');
        $this->analyzer = $analyzer;
        $analyzer->getStructure()->load($definitions);
        $this->structure = $analyzer->getStructure();
        $this->temp = new Temp('json-parser');
        $this->cache = new Cache();
    }

    public function __destruct()
    {
        $this->temp->remove();
    }

    /**
     * Analyze and store an array of data for parsing.
     * The analysis is done immediately, based on the analyzer settings,
     * then the data is stored using \Keboola\Json\Cache and parsed
     * upon retrieval using getCsvFiles().
     *
     * @param string $type is used for naming the resulting table(s)
     * @param string|array|null $parentId may be either a string,
     *         which will be saved in a JSON_parentId column,
     *         or an array with "column_name" => "value",
     *         which will name the column(s) by array key provided
     * @throws NoDataException
     */
    public function process(array $data, string $type = 'root', string|array|null $parentId = null): void
    {
        if (empty($data) || $data === [null]) {
            throw new NoDataException("Empty data set received for '{$type}'", [
                'data' => $data,
                'type' => $type,
                'parentId' => $parentId,
            ]);
        }

        $this->analyzer->analyzeData($data, $type);
        $this->structure->generateHeaderNames();
        $this->cache->store(['data' => $data, 'type' => $type, 'parentId' => $parentId]);
    }

    /**
     * Parse data of known type
     */
    private function parse(array $data, NodePath $nodePath, string|array|null $parentId = null): void
    {
        $parentId = $this->validateParentId($parentId);
        $csvFile = $this->createCsvFile($this->structure->getTypeFromNodePath($nodePath), $nodePath, $parentId);
        $parentCols = array_fill_keys(array_keys($parentId), 'string');

        foreach ($data as $row) {
            // in case of non-associative array of strings
            // prepare {"data": $value} objects for each row
            if (is_scalar($row) || is_null($row)) {
                $row = (object) [Structure::DATA_COLUMN => $row];
            } elseif ($this->analyzer->getNestedArrayAsJson() && is_array($row)) {
                $row = (object) [Structure::DATA_COLUMN => json_encode($row)];
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
     * @param array $parentCols to inject parent columns, which aren't part of $this->struct
     * @param string $outerObjectHash Outer object hash to distinguish different parents in deep nested arrays
     */
    private function parseRow(
        stdClass $dataRow,
        NodePath $nodePath,
        array $parentCols = [],
        ?string $outerObjectHash = null,
    ): CsvRow {
        $csvRow = new CsvRow($this->getHeaders($nodePath, $parentCols));
        // Generate parent ID for arrays
        $arrayParentId = $this->getPrimaryKeyValue($dataRow, $nodePath, $outerObjectHash);
        $columns = $this->structure->getColumnTypes($nodePath);
        foreach (array_replace($columns, $parentCols) as $column => $dataType) {
            $this->parseField($dataRow, $csvRow, $arrayParentId, (string) $column, $dataType, $nodePath);
        }

        return $csvRow;
    }

    /**
     * Handle the actual write to CsvRow
     */
    private function parseField(
        stdClass $dataRow,
        CsvRow $csvRow,
        string $arrayParentId,
        string $column,
        string $dataType,
        NodePath $nodePath,
    ): void {
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
            if ($dataType !== 'object') {
                $safeColumn = $this->structure->getNodeProperty($nodePath->addChild($column), 'headerNames');
                if ($safeColumn === null) {
                    $safeColumn = $this->structure->getNodeProperty($nodePath, 'headerNames');
                }
                $csvRow->setValue($safeColumn, null);
            }

            return;
        }

        switch ($dataType) {
            case 'array':
                if (!is_array($dataRow->{$column})) {
                    $dataRow->{$column} = [$dataRow->{$column}];
                }
                $sf = $this->structure->getNodeProperty($nodePath->addChild($column), 'headerNames');
                $csvRow->setValue($sf, $arrayParentId);
                $this->parse(
                    $dataRow->{$column},
                    $nodePath->addChild($column)->addChild(Structure::ARRAY_NAME),
                    $arrayParentId,
                );
                break;
            case 'object':
                $childRow = $this->parseRow($dataRow->{$column}, $nodePath->addChild($column), [], $arrayParentId);

                foreach ($childRow->getRow() as $key => $value) {
                    $csvRow->setValue($key, $value);
                }
                break;
            default:
                // If a column is an object/array while $struct expects a single column, log an error
                if (is_scalar($dataRow->{$column})) {
                    $sf = $this->structure->getNodeProperty($nodePath->addChild($column), 'headerNames');
                    if ($sf === null) {
                        $sf = $this->structure->getNodeProperty($nodePath, 'headerNames');
                    }
                    $csvRow->setValue($sf, $dataRow->{$column});
                } else {
                    $jsonColumn = json_encode($dataRow->{$column});

                    $this->analyzer->getLogger()->error(
                        "Data parse error in '{$column}' - unexpected '"
                            . gettype($dataRow->{$column}) . "' where '{$dataType}' was expected!",
                        [ 'data' => $jsonColumn, 'row' => json_encode($dataRow) ],
                    );
                    $sf = $this->structure->getNodeProperty($nodePath->addChild($column), 'headerNames');
                    $csvRow->setValue($sf, $jsonColumn);
                }
                break;
        }
    }

    /**
     * Get column names for a particular node path
     * @param array|null $parent Parent column, may be renamed in case a conflict occurs
     */
    private function getHeaders(NodePath $nodePath, ?array &$parent = null): array
    {
        $headers = [];
        $nodeData = $this->structure->getNode($nodePath) ?? [];
        $nodeType = $nodeData['nodeType'] ?? [];

        if ($nodeType === 'scalar') {
            $headers[] = $nodeData['headerNames'];
        }

        if (is_array($parent) && !empty($parent)) {
            foreach ($parent as $key => $value) {
                // check all parent columns
                $previousPath = $nodePath->popLast();
                $previousNode = $this->structure->getNode($previousPath);
                /* this is a slight WTF, but getHeaders is called for every row, so the below code must
                     not be called if the parent was already generated. */
                $actualKey = $this->structure->encodeNodeName($key);
                if (!isset($previousNode[$key]) && !isset($nodeData[Structure::ARRAY_NAME][$actualKey]) &&
                    empty($nodeData[$actualKey]['type']) &&
                    empty($nodeData[Structure::ARRAY_NAME][$actualKey]['type'])
                ) {
                    // check that there is a column with a same name as a column with parent name
                    if (isset($previousNode[Structure::ARRAY_NAME][$actualKey])) {
                        // generate new column name
                        $newColName = $key;
                        $i = 0;
                        while (isset($previousNode[Structure::ARRAY_NAME]
                            [$this->structure->encodeNodeName($newColName)])
                        ) {
                            $newColName = $key . '_u' . $i;
                            $i++;
                        }
                        // rename the column in parent
                        $parent[$newColName] = $value;
                        unset($parent[$key]);
                        $this->structure->setParentTargetName($key, $newColName);
                        $key = $newColName;
                    }
                    // either way we need to store the parent column in structure
                    $previousNode[Structure::ARRAY_NAME][$this->structure->encodeNodeName($key)] =
                        ['nodeType' => 'scalar', 'type' => 'parent'];
                    $this->structure->saveNode($previousPath, $previousNode);
                    $this->structure->generateHeaderNames();
                    $nodeData = $this->structure->getNode($nodePath) ?? [];
                }
            }
        }

        foreach ($nodeData as $nodeName => $data) {
            if (is_array($data) && ($data['nodeType'] === 'object')) {
                $pparent = null;
                $nodeName = $this->structure->decodeNodeName($nodeName);
                $ch = $this->getHeaders($nodePath->addChild($nodeName), $pparent);
                $headers = array_merge($headers, $ch);
            } elseif (is_array($data)) {
                $headers[] = $data['headerNames'];
            }
        }
        return $headers;
    }

    /**
     * to allow saving a single type to different files
     */
    private function createCsvFile(string $type, NodePath $nodePath, array &$parentId): Table
    {
        if (empty($this->csvFiles[$type])) {
            $this->csvFiles[$type] = new Table(
                $type,
                $this->getHeaders($nodePath, $parentId),
                true,
                $this->temp,
            );
            $this->csvFiles[$type]->addAttributes(['fullDisplayName' => $type]);
            if (!empty($this->primaryKeys[$type])) {
                $this->csvFiles[$type]->setPrimaryKey($this->primaryKeys[$type]);
            }
        }

        return $this->csvFiles[$type];
    }

    private function getPrimaryKeyValue(stdClass $dataRow, NodePath $nodePath, ?string $outerObjectHash = null): string
    {
        $column = $this->structure->getTypeFromNodePath($nodePath);
        if (!empty($this->primaryKeys[$column])) {
            $pKeyCols = explode(',', $this->primaryKeys[$column]);
            $pKeyCols = array_map('trim', $pKeyCols);
            $values = [];
            foreach ($pKeyCols as $pKeyCol) {
                if (empty($dataRow->{$pKeyCol})) {
                    $values[] = md5(serialize($dataRow) . $outerObjectHash);
                    $this->analyzer->getLogger()->warning(
                        "Primary key for type '{$column}' was set to '". $this->primaryKeys[$column] .
                        "', but its column '{$pKeyCol}' does not exist! Using hash to link child objects instead.",
                        ['row' => $dataRow],
                    );
                } else {
                    $values[] = $dataRow->{$pKeyCol};
                }
            }
            // this awkward format is because of backward compatibility
            return $nodePath->toCleanString() . '_' . join(';', $values);
        } else {
            // this awkward format is because of backward compatibility
            return $nodePath->toCleanString() . '_' . md5(serialize($dataRow) . $outerObjectHash);
        }
    }

    /**
     * Ensure the parentId array is not multidimensional
     *
     * @throws JsonParserException
     */
    private function validateParentId(string|array|null $parentId): array
    {
        if (!empty($parentId)) {
            if (is_array($parentId)) {
                if (count($parentId) !== count($parentId, COUNT_RECURSIVE)) {
                    throw new JsonParserException(
                        'Error assigning parentId to a CSV file! $parentId array cannot be multidimensional.',
                        [
                            'parentId' => $parentId,
                        ],
                    );
                }
            } else {
                $parentId = ['JSON_parentId' => $parentId];
            }
        } else {
            $parentId = [];
        }
        $result = [];
        foreach ($parentId as $key => $value) {
            $result[$this->structure->getParentTargetName($key)] = $value;
        }
        return $result;
    }

    /**
     * Returns an array of CSV files containing results
     * @return Table[]
     */
    public function getCsvFiles(): array
    {
        // parse what's in cache before returning results
        while ($batch = $this->cache->getNext()) {
            // root node is always array
            $this->parse($batch['data'], new NodePath([$batch['type'], Structure::ARRAY_NAME]), $batch['parentId']);
        }
        return $this->csvFiles;
    }

    public function getAnalyzer(): Analyzer
    {
        return $this->analyzer;
    }

    public function addPrimaryKeys(array $pks): void
    {
        if (!empty($this->csvFiles)) {
            throw new JsonParserException('"addPrimaryKeys" must be used before any data is parsed');
        }

        $this->primaryKeys += $pks;
    }

    /**
     * Set maximum memory used before Cache starts using php://temp
     */
    public function setCacheMemoryLimit(string|int $limit): void
    {
        $this->cache->setMemoryLimit((int) $limit);
    }
}
