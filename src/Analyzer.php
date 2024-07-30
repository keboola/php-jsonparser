<?php

declare(strict_types=1);

namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;
use Psr\Log\LoggerInterface;

class Analyzer
{
    protected bool $strict;

    protected bool $nestedArrayAsJson;

    protected LoggerInterface $log;

    private Structure $structure;

    public function __construct(
        LoggerInterface $logger,
        Structure $structure,
        bool $nestedArraysAsJson = false,
        bool $strict = false,
    ) {
        $this->nestedArrayAsJson = $nestedArraysAsJson;
        $this->strict = $strict;
        $this->log = $logger;
        $this->structure = $structure;
    }

    public function getStructure(): Structure
    {
        return $this->structure;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->log;
    }

    /**
     * @param mixed[] $data
     */
    public function analyzeData(array $data, string $rootType): void
    {
        if (empty($data)) {
            return;
        }
        $path = new NodePath([$rootType]);
        $this->analyzeArray($data, $path);
        $this->structure->saveNodeValue($path, 'nodeType', 'array');
    }

    private function analyzeItem(mixed $item, NodePath $nodePath): string
    {
        if (is_scalar($item)) {
            if ($this->strict) {
                $nodeType = gettype($item);
            } else {
                $nodeType = 'scalar';
            }
        } elseif (is_object($item)) {
            $nodeType = 'object';
            $this->analyzeObject($item, $nodePath);
        } elseif (is_null($item)) {
            $nodeType = 'null';
        } elseif (is_array($item)) {
            if ($nodePath->isArray()) {
                if ($this->nestedArrayAsJson) {
                    $this->log->warning("Converting nested array '$nodePath' to JSON string.", ['item' => $item]);
                    $nodeType = $this->strict ? 'string' : 'scalar';
                } else {
                    throw new JsonParserException("Unsupported data in '$nodePath'.", ['item' => $item]);
                }
            } else {
                $nodeType = 'array';
                $this->analyzeArray($item, $nodePath);
            }
        } else {
            // this is probably only resource, which should not be here anyway
            throw new JsonParserException("Unsupported data in '$nodePath'.", ['item' => $item]);
        }
        $this->structure->saveNodeValue($nodePath, 'nodeType', $nodeType);
        return $nodeType;
    }

    /**
     * @param mixed[] $array
     */
    private function analyzeArray(array $array, NodePath $nodePath): void
    {
        $oldType = null;
        $nodePath = $nodePath->addChild(Structure::ARRAY_NAME);
        if (empty($array)) {
            $this->structure->saveNodeValue($nodePath, 'nodeType', 'null');
        }
        foreach ($array as $row) {
            $newType = $this->analyzeItem($row, $nodePath);
            // verify that the items in the array are of same (or compatible) type
            $oldType = $this->checkType($oldType, $newType, $nodePath);
        }
    }

    private function analyzeObject(object $object, NodePath $nodePath): void
    {
        foreach (get_object_vars($object) as $key => $field) {
            $this->analyzeItem($field, $nodePath->addChild((string) $key));
        }
    }

    /**
     * Check that two types same or compatible.
     * @throws JsonParserException
     */
    private function checkType(?string $oldType, string $newType, NodePath $nodePath): string
    {
        if (!is_null($oldType) && ($newType !== $oldType) && ($newType !== 'null') && ($oldType !== 'null')) {
            throw new JsonParserException(
                "Data in '$nodePath' contains incompatible data types '$oldType' and '$newType'.",
            );
        }
        return $newType;
    }

    public function getNestedArrayAsJson(): bool
    {
        return $this->nestedArrayAsJson;
    }
}
