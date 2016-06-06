<?php
namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;
use Keboola\Utils\Utils;
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

        $rowType = $this->getStruct()->getArrayType($type);
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
        if (is_scalar($row)) {
            $struct[Parser::DATA_COLUMN] = $this->getType($row);
        } elseif (is_object($row)) {
            // process each property of the object
            foreach($row as $key => $field) {
                $fieldType = $this->getType($field);

                if ($fieldType == "object") {
                    // Only assign the type if the object isn't empty
                    if (Utils::isEmptyObject($field)) {
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
            $rowType = $struct[Parser::DATA_COLUMN] = $this->strict ? 'string' : 'scalar';
        } elseif (is_null($row)) {
            // do nothing
        } else {
            throw new JsonParserException("Unsupported data row in '{$type}'!", ['row' => $row]);
        }

        $this->getStruct()->add($type, $struct);

        return $rowType;
    }

    /**
     * Returns data type of a variable based on 'strict' setting
     * @param mixed $var
     * @return string
     */
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
        return $this->getStruct()->hasDefinitions($type)
            && $this->analyzeRows != -1
            && !empty($this->rowsAnalyzed[$type])
            && $this->rowsAnalyzed[$type] >= $this->analyzeRows;
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
