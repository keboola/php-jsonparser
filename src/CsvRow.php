<?php

declare(strict_types=1);

namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;

class CsvRow
{
    /**
     * @var mixed[]
     */
    protected array $data = [];

    /**
     * @var mixed[] $columns
     */
    public function __construct(array $columns)
    {
        $this->data = array_fill_keys($columns, null);
    }

    /**
     * @throws JsonParserException
     */
    public function setValue(string $column, mixed $value): void
    {
        if (!array_key_exists($column, $this->data)) {
            throw new JsonParserException(
                "Error assigning '{$value}' to a non-existing column '{$column}'!",
                [
                    'columns' => array_keys($this->data),
                ],
            );
        }

        if (!is_scalar($value) && !is_null($value)) {
            throw new JsonParserException(
                "Error assigning value to '{$column}': The value's not scalar!",
                [
                    'type' => gettype($value),
                    'value' => json_encode($value),
                ],
            );
        }

        $this->data[$column] = $value;
    }

    /**
     * @return mixed[]
     */
    public function getRow(): array
    {
        return $this->data;
    }
}
