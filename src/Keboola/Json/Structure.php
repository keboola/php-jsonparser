<?php

namespace Keboola\Json;

use Guzzle\Service\Exception\InconsistentClientTransferException;
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
     * @var string
     */
    private $baseType;
    private $headerIndex = [];

    public function __construct(/*string $baseType*/)
    {
//        $this->baseType = $baseType;
    }

    // TODO: this originaly defaulted to false
    protected $autoUpgradeToArray = true;

    public function setAutoUpgradeToArray($enable)
    {
      //  $this->log->debug("Using automatic conversion of single values to arrays where required.");

        $this->autoUpgradeToArray = (bool) $enable;
    }

    public function mergearray($array1, $array2)
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
            // TODO catch only correct exception
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
        //var_export($this->data, true);
    }

    public function getTypeFromNodePath(NodePath $nodePath)
    {
        return $this->createSafeName($nodePath->toCleanString());
    }

    public function saveNode(NodePath $nodePath, $newNode)
    {
        $this->data = $this->storeNode($nodePath, $this->data, $newNode);
    }

    public function storeNode(NodePath $nodePath, $data, $newNode)
    {
        if (!is_array($data)) {
            throw new LogicException("wtf");
        }
        $nodePath = $nodePath->popFirst($node);
        if (!isset($data[$node])) {
            $data[$node] = [];
        }
        if ($nodePath->isEmpty()) {
            $data[$node] = $newNode;
        } else {
            $data[$node] = $this->storeNode($nodePath, $data[$node], $newNode);
        }
        return $data;
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
            if (!empty($data[$node][$key]) && ($data[$node][$key] != $value)) {
                throw new InconsistentValueException($data[$node][$key], $value, $key);
            //    throw new LogicException("Inconsistent values " . $data[$node][$key] . " and " . $value . " for " . $nodePath . " " . $key);
            }
            $data[$node][$key] = $value;
        } else {
            $data[$node] = $this->storeValue($nodePath, $data[$node], $key, $value);
        }
        return $data;
    }

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

    public function getValues(NodePath $nodePath, $key)
    {
        $nodeData = $this->getValue($nodePath);
        $result = [];
        if (is_array($nodeData)) {
            // TODO: bwd compat fuckup
            /*
            if (isset($nodeData['[]'])) {
                $elevate = false;
                foreach ($nodeData['[]'] as $kkey => $value) {
                    if (is_array($value)) {
                        $elevate = true;
                    }
                }
                if ($elevate) {
                    $nodeData = $nodeData['[]'];
                }
            }
*/
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
            } elseif ($nodeData['nodeType'] == 'array') {
                if ($nodeData['[]']['nodeType'] == 'scalar') {
                    foreach ($nodeData['[]'] as $itemName => $value) {
                        if ($itemName == $key) {
                            $result['[]'] = $value;
                        } else {
                            $result['[]'] = null;
                        }
                    }
                } else {
                    foreach ($nodeData['[]'] as $itemName => $value) {
                        if (is_array($value)) {
                            if (isset($value[$key])) {
                                $result[$itemName] = $value[$key];
                            } else {
                                $result[$itemName] = null;
                            }
                        }
                    }
                }
            } elseif ($nodeData['nodeType'] == 'scalar') {
                foreach ($nodeData as $itemName => $value) {
                    if ($itemName == $key) {
                        $result[$nodePath->getLast()] = $value;
                    } else {
                     //   $result[$nodePath->getLast()] = null;
                    }
                }
            }
        }
        return $result;
    }

    public function getDefinitions($type)
    {
        $pathNew = $this->buildNodePathFromString($type);
       // $path = explode('.', $type);
     //   $rootType = array_shift($path);
        //if (!($rootType == $this->baseType)) {
            //assert($rootType == $this->baseType);
        //}
        //array_unshift($path, '[]');
        //$nodePath = new NodePath($path);
        $nodePath = new NodePath($pathNew);
        $values = $this->getValues($nodePath, 'nodeType');
//        var_export($path);
        // todo - this is compatibility fix
        $result = [];
        if (empty($values)) {
            return [];
        }
//        if ($values['nodeType'] == 'object') {
            foreach ($values as $key => $value) {
                if ($key === '[]') {
                    $result['data'] = $value;
                } else {
                    $result[$key] = $value;
                }
            }
  //      } elseif ($values['nodeType'] == 'scalar') {
    //        $result['data'] = 'scalar';
      //  }
        return $result;
    }

    public function getDefinitionsNodePath(NodePath $nodePath)
    {
        $values = $this->getValues($nodePath, 'nodeType');
//        var_export($path);
        // todo - this is compatibility fix
        $result = [];
        if (empty($values)) {
            return [];
        }
//        if ($values['nodeType'] == 'object') {
        foreach ($values as $key => $value) {
            if ($key === '[]') {
                $result['data'] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        //      } elseif ($values['nodeType'] == 'scalar') {
        //        $result['data'] = 'scalar';
        //  }
        return $result;
    }

    private function buildNodePathFromString($path) {
        ////if (substr($path, 0, strlen($this->baseType)) != $this->baseType) {
            //throw new LogicException("Basetype mysmac");
        //}
        foreach ($this->data as $key => $value) {
            // todo overit partial match
            if (substr($path, 0, strlen($key)) == $key) {
                $subPath = substr($path, strlen($key) + 1);
                if (empty($subPath)) {
                    $subPath = '';
                }
                // in root there is always '[]'
                $npath = $this->findNodePath($subPath, $value['[]']);
                array_unshift($npath, '[]');
                array_unshift($npath, $key);
                return $npath;
            }
        }
//        $subPath = substr($path, strlen($this->baseType) + 1);
  //      if (empty($subPath)) {
    //        $subPath = '';
      //  }
        //$npath = $this->findNodePath($subPath, $this->data['[]']);
        //array_unshift($npath, '[]');
      //  var_export($npath, true);
        //return $npath;
        throw new LogicException('path not found');
    }

    private function findNodePath($path, $data)
    {
        if (empty($path)) {
            return [];
        } else {
            // todo prochazet od nejdelsiho klice?
            $keys = array_keys($data);
            usort($keys, function ($a, $b) { return strlen($a) - strlen($b);});
            foreach ($keys as $key) {
  //              if (((substr($path, 0, strlen($key)) === $key) && (
//                            (strlen($path) == strlen($key)) || ($path[(strlen($key))] == '.'))) || (($path == 'data') && ($key = '[]'))) {
                if (((substr($path, 0, strlen($key)) === $key) && (
                            (strlen($path) == strlen($key)) || ($path[(strlen($key))] == '.')))
                    /*|| (($path == 'data') && ($key == '[]')*/) {
                    //if (($path == 'data') && ($key == '[]')) {
                    if (/*($path == 'data') && */($key == '[]')) {
                        return [];
                    }
                    $subPath = substr($path, strlen($key) + 1);
                    if (empty($subPath)) {
                        $subPath = '';
                    }
                    $arrPath = $this->findNodePath($subPath, $data[$key]);
                    array_unshift($arrPath, $key);
                    return $arrPath;
                }
            }
            if (isset($data['[]'])) {
                $data = $data['[]'];
                $keys = array_keys($data);
                usort($keys, function ($a, $b) { return strlen($a) - strlen($b);});
                // todo prochazet od nejdelsiho klice?
                foreach ($keys as $key) {
                    if (((substr($path, 0, strlen($key)) === $key) && (
                            (strlen($path) == strlen($key)) || ($path[(strlen($key))] == '.'))) || (($path == 'data') && ($key = '[]'))) {
                        if (($path == 'data') && ($key = '[]')) {
                            return [];
                        }
                        $subPath = substr($path, strlen($key) + 1);
                        if (empty($subPath)) {
                            $subPath = '';
                        }
                        $arrPath = $this->findNodePath($subPath, $data[$key]);
                        array_unshift($arrPath, $key);
                        array_unshift($arrPath, '[]');
                        return $arrPath;
                    }
                }
            }
        }
        throw new LogicException("Should not happen");
    }

    public function getHeaderNames()
    {
      //  $this->headerIndex = [];
        foreach ($this->data as $baseType => &$baseArray) {
            foreach ($baseArray as $nodeName => &$nodeData) {
                $this->getHeaders($nodeData, new NodePath([$baseType, $nodeName]), '[]', $baseType);
            }
        }
        //var_export($this->data);
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
                //if (isset($this->headerIndex[$baseType][$headerName]) && empty($data['invalidateHeaderNames'])) {
                 //   $this->headerIndex[$baseType][$headerName]++;
                  //  $headerName .= md5($headerName) .'_' . $this->headerIndex[$baseType][$headerName];
                //}
                //$/this->headerIndex[$baseType][$headerName] = 1;
                ///$data['headerNames'] = $headerName;
            } elseif ($parentName == '[]') {
                $data['headerNames'] = 'data';
            } // else already set
            if ($data['nodeType'] == 'array') {
                $baseType = $baseType . '.' . $parentName;
                $parentName = '';
            }
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                   // if ($parentName == '[]') {
                 //       $this->getHeaders($value, $nodePath->addArrayChild(), $parentName, $baseType);
                    //} else {
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
                    //}
                }
            }
        }
    }
}
