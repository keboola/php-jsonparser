<?php
namespace Keboola\Json;

use Keboola\Json\Exception\JsonParserException;
use Psr\Log\LoggerInterface;

class Analyzer
{
    /**
     * @var bool
     */
    protected $strict = false;

    /**
     * @var bool
     */
    protected $nestedArrayAsJson = false;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var Structure
     */
    private $structure;

    public function __construct(LoggerInterface $logger, Structure $structure = null, $analyzeRows = -1)
    {
        $this->log = $logger;
        $this->structure = $structure;
        if (empty($this->structure)) {
            $this->structure = new Structure();
        }
    }

    public function getStructure()
    {
        return $this->structure;
    }

    public function analyzeData(array $data, string $rootType)
    {
        if (empty($data)) {
            return;
        }
        $path = new NodePath([$rootType]);
        $this->analyzeArray($data, $path);
        $this->structure->addNode($path, 'nodeType', 'array');

    }

    private function analyzeItem($item, NodePath $nodePath)
    {
        if (is_scalar($item)) {
            if ($this->strict) {
                $nodeType = gettype($item);
            } else {
                $nodeType = 'scalar';
            }
        } elseif (is_object($item)) {
            $nodeType = 'object';
            if (\Keboola\Utils\isEmptyObject($item)) {
                // todo: is this condiion necessary?
                $nodeType = 'null';
            } else {
                $this->analyzeObject($item, $nodePath);
            }
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
        $this->structure->addNode($nodePath, 'nodeType', $nodeType);
        return $nodeType;
    }

    private function analyzeArray(array $array, NodePath $nodePath)
    {
        $oldType = null;
        $nodePath = $nodePath->addArrayChild();
         if (empty($array)) {
            $this->structure->addNode($nodePath, 'nodeType', 'null');
        }
        foreach ($array as $row) {
            $newType = $this->analyzeItem($row, $nodePath);
            $oldType = $this->checkType($oldType, $newType, $nodePath);
        }
    }

    private function analyzeObject($object, NodePath $nodePath)
    {
        foreach ($object as $key => $field) {
            $this->analyzeItem($field, $nodePath->addChild($key));
        }
    }

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
     * If enabled, nested arrays will be saved as JSON strings instead
     * @param bool $bool
     */
    public function setNestedArrayAsJson(bool $bool)
    {
        $this->nestedArrayAsJson = $bool;
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
    public function setStrict(bool $strict)
    {
        $this->strict = $strict;
    }
}
