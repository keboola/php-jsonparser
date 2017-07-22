<?php

namespace Keboola\Json;

use Symfony\Component\Console\Exception\LogicException;

class Structure
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var string
     */
    private $baseType;

    public function __construct(string $baseType)
    {
        $this->baseType = $baseType;
    }

    public function addNode(NodePath $nodePath, $key, $value)
    {
        $this->data = $this->storeValue($nodePath, $this->data, $key, $value);
        var_export($this->data, true);
    }

    private function storeValue(NodePath $nodePath, $data, $key, $value)

    {
        if (!is_array($data)) {
            throw new LogicException("wtf");
        }
        $nodePath = $nodePath->popFirst($node);
        if (!isset($data[$node])) {
            $data[$node] = [];
        }
        if ($nodePath->isEmpty()) {
            $data[$node][$key] = $value;
        } else {
            $data[$node] = $this->storeValue($nodePath, $data[$node], $key, $value);
        }
        return $data;
    }

    public function getData()
    {
        return [$this->baseType => $this->data];
    }
}
