<?php

namespace Keboola\Json;

use Keboola\Json\Exception\InconsistentValueException;
use Keboola\Json\Exception\JsonParserException;
use Symfony\Component\Console\Exception\LogicException;

class Structure
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    private $headerIndex = [];

    /**
     * @var bool
     */
    private $autoUpgradeToArray;

    /**
     * Structure constructor.
     * @param bool $autoUpgradeToArray
     */
    public function __construct(bool $autoUpgradeToArray = true)
    {
        $this->autoUpgradeToArray = $autoUpgradeToArray;
    }

    private function mergearray($array1, $array2)
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array1[$key])) {
                    $array1[$key] = [];
                }
                $value = $this->mergearray($array1[$key], $array2[$key]);
                $array1[$key] = $value;
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    public function addNode(NodePath $nodePath, $key, $value)
    {
        try {
            $this->data = $this->storeValue($nodePath, $this->data, $key, $value);
        } catch (InconsistentValueException $e) {
            if ($e->getKey() == 'nodeType') {
                if (((($e->getPreviousValue() != 'array') && ($e->getNew() == 'array')) ||
                        (($e->getPreviousValue() == 'array') && ($e->getNew() != 'array'))) &&
                        $this->autoUpgradeToArray) {
                    $node = $this->getValue($nodePath);
                    if (($node['nodeType'] != 'array') && ($node['nodeType'] != $node['[]']['nodeType']) && ($node['[]']['nodeType'] != 'array')) {
                        throw new JsonParserException("Data array in '" . $nodePath->__toString() .
                            "' contains incompatible types '" . $node['nodeType'] . "' and '" .
                            $node['[]']['nodeType'] . "'");
                    }
                    if (($node['nodeType'] != 'null') && ($node['nodeType'] != 'array') && ($node['nodeType'] != $value) && ($value != 'array') && ($value != 'null')) {
                        throw new JsonParserException("Data array in '" . $nodePath->__toString() .
                            "' contains incompatible types '" . $node['nodeType'] . "' and '" .
                            $value . "'");
                    }
                    if (($node['[]']['nodeType'] != 'null') && ($node['[]']['nodeType'] != 'array') && ($node['[]']['nodeType'] != $value) && ($value != 'array') && ($value != 'null')) {
                        throw new JsonParserException("Data array in '" . $nodePath->__toString() .
                            "' contains incompatible types '" . $value . "' and '" .
                            $node['[]']['nodeType'] . "'");
                    }
                    $nodeRoot = $node;
                    unset($nodeRoot['[]']);
                    // todo tohle zmergovat rucne a overit, ze hodnoty jsou stejne
                    $newNode['[]'] = $this->mergearray($node['[]'], $nodeRoot);
                    //unset($newNode['[]']);
                    if ($newNode['[]']['nodeType'] == 'array') {
                        $newNode['[]']['nodeType'] = $node['[]']['nodeType'];
                    }
                    $newNode['nodeType'] = 'array';
                    if (isset($newNode['[]']['headerNames'])) {
                        $newNode['headerNames'] = $newNode['[]']['headerNames'];
                        $newNode['[]']['headerNames'] = 'data';
                    }
                    //$newNode['invalidateHeaderNames'] = 1;
                    //$this->getHeaderNames();
                    $this->data = $this->storeNode($nodePath, $this->data, $newNode);
                } elseif ($e->getPreviousValue() != 'null' && ($e->getNew() == 'null')) {
                    // do nothing
                } elseif ($e->getPreviousValue() == 'null' && ($e->getNew()) != 'null') {
                    $newNode = $this->getValue($nodePath);
                    $newNode[$key] = $value;
                    $this->data = $this->storeNode($nodePath, $this->data, $newNode);
                } else {
                    throw new JsonParserException(
                        'Unhandled ' . $key . ' change from "' . $e->getPreviousValue() .
                        '" to "' . $e->getNew() . '" in "' . $nodePath->__toString() . '"'
                    );
                }
            } else {
                throw new LogicException($e->getMessage());
            }
        }
    }

    public function getTypeFromNodePath(NodePath $nodePath)
    {
        return $this->createSafeName($nodePath->toCleanString());
    }

    public function saveNode(NodePath $nodePath, $newNode)
    {
        $this->data = $this->storeNode($nodePath, $this->data, $newNode);
    }

    private function storeNode(NodePath $nodePath, array $data, $newNode)
    {
        $nodePath = $nodePath->popFirst($node);
        if ($nodePath->isEmpty()) {
            $data[$node] = $newNode;
        } else {
            $data[$node] = $this->storeNode($nodePath, $data[$node], $newNode);
        }
        return $data;
    }

    private function storeValue(NodePath $nodePath, array $data, $key, $value)
    {
        $nodePath = $nodePath->popFirst($node);
        if (!isset($data[$node])) {
            $data[$node] = [];
        }
        if ($nodePath->isEmpty()) {
            if (!empty($data[$node][$key]) && ($data[$node][$key] != $value)) {
                throw new InconsistentValueException($data[$node][$key], $value, $key);
            }
            $data[$node][$key] = $value;
        } else {
            $data[$node] = $this->storeValue($nodePath, $data[$node], $key, $value);
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    public function getValue(NodePath $nodePath, $data = null)
    {
        if (empty($data)) {
            $data = $this->data;
        }
        $nodePath = $nodePath->popFirst($node);
        if (!isset($data[$node])) {
            return null;
        }
        if ($nodePath->isEmpty()) {
            return $data[$node];
        } else {
            return $this->getValue($nodePath, $data[$node]);
        }
    }

    public function getSingleValue(NodePath $nodePath, $key)
    {
        $data = $this->getValue($nodePath);
        if (isset($data[$key])) {
            return $data[$key];
        } else {
            return null;
        }
    }

    private function getValues(NodePath $nodePath, $key)
    {
        $nodeData = $this->getValue($nodePath);
        $result = [];
        if (is_array($nodeData)) {
            if ($nodeData['nodeType'] == 'object') {
                foreach ($nodeData as $itemName => $value) {
                    if (is_array($value)) {
                        if (isset($value[$key])) {
                            $result[$itemName] = $value[$key];
                        } else {
                            $result[$itemName] = null;
                        }
                    }
                }
            } elseif ($nodeData['nodeType'] == 'scalar') {
                foreach ($nodeData as $itemName => $value) {
                    if ($itemName == $key) {
                        $result[$nodePath->getLast()] = $value;
                    }
                }
            }
        }
        return $result;
    }

    public function getDefinitionsNodePath(NodePath $nodePath)
    {
        $values = $this->getValues($nodePath, 'nodeType');
        // todo - this is compatibility fix
        $result = [];
        if (empty($values)) {
            return [];
        }

        foreach ($values as $key => $value) {
            if ($key === '[]') {
                $result['data'] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function getHeaderNames()
    {
        foreach ($this->data as $baseType => &$baseArray) {
            foreach ($baseArray as $nodeName => &$nodeData) {
                $this->getHeaders($nodeData, new NodePath([$baseType, $nodeName]), '[]', $baseType);
            }
        }
    }

    protected function createSafeName($name)
    {
        $name = preg_replace('/[^A-Za-z0-9-]/', '_', $name);
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

        $newName = preg_replace('/[^A-Za-z0-9-]+/', '_', $newName);
        $newName = trim($newName, "_");
        return $newName;
    }


    private function getUniqueName($baseName, $headerName)
    {
        if (isset($this->headerIndex[$baseName][$headerName])) {
            $newName = $headerName;
            $i = 0;
            while (isset($this->headerIndex[$baseName][$newName])) {
                $newName = $headerName . '_u' . $i;
                $i++;
            }
            $headerName = $newName;
        }
        $this->headerIndex[$baseName][$headerName] = 1;
        return $headerName;
    }


    private function getHeaders(&$data, NodePath $nodePath, $parentName, $baseType)
    {
        if (is_array($data)) {
            if ((empty($data['headerNames']) || !empty($data['invalidateHeaderNames'])) && ($parentName != '[]')) { // write only once and arrays are unnamed
                $headerName = $this->createSafeName($parentName);
                $headerName = $this->getUniqueName($baseType, $headerName);
                $data['headerNames'] = $headerName;
            } elseif ($parentName == '[]') {
                $data['headerNames'] = 'data';
            } // else already set
            if ($data['nodeType'] == 'array') {
                $baseType = $baseType . '.' . $parentName;
                $parentName = '';
            }
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    if ($key == 'JSON_parentId') {
                        // BWD compat hack
                        $childName = $key;
                    } else {
                        if ($parentName) {
                            $childName = $parentName . '.' . $key;
                        } else {
                            $childName = $key;
                        }
                    }
                    $this->getHeaders($value, $nodePath->addChild($key), $childName, $baseType);
                }
            }
        }
    }

    /**
     * @param array $definitions
     */
    public function load(array $definitions)
    {
        $this->data = $definitions;
    }
}
