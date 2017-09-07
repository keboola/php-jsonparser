<?php

namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;

class CsvRow
{
    /**
     * @var array
     */
    protected $data = [];

    public function __construct(array $columns)
    {
        $this->data = array_fill_keys($columns, null);
    }

    public function setValue($column, $value)
    {
        if (!array_key_exists($column, $this->data)) {
            throw new JsonParserException(
                "Error assigning '{$value}' to a non-existing column '{$column}'!",
                [
                    'columns' => array_keys($this->data)
                ]
            );
        }

        if (!is_scalar($value) && !is_null($value)) {
            throw new JsonParserException(
                "Error assigning value to '{$column}': The value's not scalar!",
                [
                    'type' => gettype($value),
                    'value' => json_encode($value)
                ]
            );
        }

        $this->data[$column] = $value;
    }

    /**
     * @return array
     */
    public function getRow()
    {
        return $this->data;
    }
}
