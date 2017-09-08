<?php

namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;
use Psr\Log\LoggerInterface;

class Analyzer
{
    /**
     * @var bool
     */
    protected $strict;

    /**
     * @var bool
     */
    protected $nestedArrayAsJson;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var Structure
     */
    private $structure;

    /**
     * Analyzer constructor.
     * @param LoggerInterface $logger
     * @param Structure $structure
     * @param bool $nestedArraysAsJson
     * @param bool $strict
     */
    public function __construct(
        LoggerInterface $logger,
        Structure $structure,
        bool $nestedArraysAsJson = false,
        bool $strict = false
    ) {
        $this->nestedArrayAsJson = $nestedArraysAsJson;
        $this->strict = $strict;
        $this->log = $logger;
        $this->structure = $structure;
    }

    /**
     * @return Structure
     */
    public function getStructure()
    {
        return $this->structure;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->log;
    }

    /**
     * @param array $data
     * @param string $rootType
     */
    public function analyzeData(array $data, string $rootType)
    {
        if (empty($data)) {
            return;
        }
        $path = new NodePath([$rootType]);
        $this->analyzeArray($data, $path);
        $this->structure->saveNodeValue($path, 'nodeType', 'array');
    }

    /**
     * @param $item
     * @param NodePath $nodePath
     * @return string
     * @throws JsonParserException
     */
    private function analyzeItem($item, NodePath $nodePath) : string
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
     * @param array $array
     * @param NodePath $nodePath
     */
    private function analyzeArray(array $array, NodePath $nodePath)
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

    /**
     * @param $object
     * @param NodePath $nodePath
     */
    private function analyzeObject($object, NodePath $nodePath)
    {
        foreach ($object as $key => $field) {
            $this->analyzeItem($field, $nodePath->addChild($key));
        }
    }

    /**
     * Check that two types same or compatible.
     * @param $oldType
     * @param $newType
     * @param NodePath $nodePath
     * @return string
     * @throws JsonParserException
     */
    private function checkType($oldType, $newType, NodePath $nodePath) : string
    {
        if (!is_null($oldType) && ($newType !== $oldType) && ($newType !== 'null') && ($oldType !== 'null')) {
            throw new JsonParserException(
                "Data in '$nodePath' contains incompatible data types '$oldType' and '$newType'."
            );
        }
        return $newType;
    }

    /**
     * @return bool
     */
    public function getNestedArrayAsJson() : bool
    {
        return $this->nestedArrayAsJson;
    }
}
