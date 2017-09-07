<?php

namespace Keboola\Json;

use Keboola\Json\Exception\InconsistentValueException;
use Keboola\Json\Exception\JsonParserException;

class Structure
{
    /**
     * Column name for an array of scalars
     */
    const DATA_COLUMN = 'data';

    /**
     * Structure property storing node data type
     */
    const PROP_NODE_DATA_TYPE = 'nodeType';

    /**
     * Structure property storing whether a type of node ('normal' or 'parent')
     */
    const PROP_NODE_TYPE = 'type';

    /**
     * Structure property storing name of node in CSV file
     */
    const PROP_HEADER = 'headerNames';

    /**
     * Special name of node for array containers
     */
    const ARRAY_NAME = '[]';

    /**
     * Allowed data types
     * @var array
     */
    private static $nodeDataTypes = ['null', 'array', 'object', 'scalar', 'string', 'integer', 'double', 'boolean'];

    /**
     * Allowed node types
     * @var array
     */
    private static $nodeTypes = ['parent'];

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
     * List of parent columns and their aliases
     * @var array
     */
    private $parentAliases = [];

    /**
     * Structure constructor.
     * @param bool $autoUpgradeToArray Set to false to disable coercing scalar->array and object->array types.
     */
    public function __construct(bool $autoUpgradeToArray = true)
    {
        $this->autoUpgradeToArray = $autoUpgradeToArray;
    }

    /**
     * Save a single property value for a given node.
     * @param NodePath $nodePath Node Path.
     * @param string $property Property name (e.g. 'nodeType')
     * @param mixed $value Scalar value.
     * @throws InconsistentValueException
     */
    public function saveNodeValue(NodePath $nodePath, string $property, $value)
    {
        try {
            $this->data = $this->storeValue($nodePath, $this->data, $property, $value);
        } catch (InconsistentValueException $e) {
            if ($property == 'nodeType') {
                $node = $this->getNode($nodePath);
                $this->handleUpgrade($node, $nodePath, $value);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Encode real JSON node name into the one stored in structure
     * @param $nodeName
     * @return string
     */
    public function encodeNodeName($nodeName)
    {
        if ($nodeName == self::ARRAY_NAME) {
            return $nodeName;
        } else {
            return '_' . $nodeName;
        }
    }

    /**
     * Decode node name into real node name found in JSON
     * @param $nodeName
     * @return string
     */
    public function decodeNodeName($nodeName)
    {
        if ($nodeName == self::ARRAY_NAME) {
            return $nodeName;
        } else {
            return substr($nodeName, 1);
        }
    }

    /**
     * Store a particular value of a particular property for a given node.
     * @param NodePath $nodePath Node Path
     * @param array $data Structure data.
     * @param string $property Name of the property (e.g. 'nodeType')
     * @param mixed $value Scalar value of the property.
     * @return array Structure data
     * @throws InconsistentValueException In case the values is already set and not same.
     */
    private function storeValue(NodePath $nodePath, array $data, string $property, $value) : array
    {
        $nodePath = $nodePath->popFirst($nodeName);
        $nodeName = $this->encodeNodeName($nodeName);
        if (!isset($data[$nodeName])) {
            $data[$nodeName] = [];
        }
        if ($nodePath->isEmpty()) {
            // we arrived at the target, check if the value is not set already
            if (!empty($data[$nodeName][$property]) && ($data[$nodeName][$property] != $value)) {
                throw new InconsistentValueException("Attempting to overwrite '$property' value '"
                    . $data[$nodeName][$property] . "' with '$value'.");
            }
            $data[$nodeName][$property] = $value;
        } else {
            $data[$nodeName] = $this->storeValue($nodePath, $data[$nodeName], $property, $value);
        }
        return $data;
    }

    /**
     * Upgrade a node to array
     * @param array $node
     * @param NodePath $nodePath
     * @param string $newType
     * @throws JsonParserException
     */
    private function handleUpgrade(array $node, NodePath $nodePath, string $newType)
    {
        if ((($node['nodeType'] == 'array') || ($newType == 'array')) && $this->autoUpgradeToArray) {
            $this->checkArrayUpgrade($node, $nodePath, $newType);
            // copy all properties to the array
            if (!empty($node[self::ARRAY_NAME])) {
                foreach ($node[self::ARRAY_NAME] as $key => $value) {
                    $newNode[self::ARRAY_NAME][$key] = $value;
                }
            }
            foreach ($node as $key => $value) {
                if (is_array($value) && ($key != self::ARRAY_NAME)) {
                    $newNode[self::ARRAY_NAME][$key] = $value;
                }
            }
            if ($newType != 'array') {
                $newNode[self::ARRAY_NAME]['nodeType'] = $newType;
            } else {
                $newNode[self::ARRAY_NAME]['nodeType'] = $node['nodeType'];
            }
            $newNode['nodeType'] = 'array';
            if (!empty($node['headerNames'])) {
                $newNode[self::ARRAY_NAME]['headerNames'] = self::DATA_COLUMN;
                $newNode['headerNames'] = $node['headerNames'];
            }
            $this->data = $this->storeNode($nodePath, $this->data, $newNode);
        } elseif (($node['nodeType'] != 'null') && ($newType == 'null')) {
            // do nothing, old type is fine
        } elseif (($node['nodeType'] == 'null') && ($newType != 'null')) {
            $newNode = $this->getNode($nodePath);
            $newNode['nodeType'] = $newType;
            $this->data = $this->storeNode($nodePath, $this->data, $newNode);
        } else {
            throw new JsonParserException(
                'Unhandled nodeType change from "' . $node['nodeType'] .
                '" to "' . $newType . '" in "' . $nodePath->__toString() . '"'
            );
        }
    }

    private function checkArrayUpgrade(array $node, NodePath $nodePath, string $newType)
    {
        if ((($node['nodeType'] == 'array') || ($newType == 'array')) && $this->autoUpgradeToArray) {
            // if one of the two different types is array, we may consider upgrade
            // at this moment, the array items should already be set
            if (empty($node[self::ARRAY_NAME]['nodeType'])) {
                throw new JsonParserException("Array contents are unknown");
            }
            // now get the non array type
            if ($node['nodeType'] == 'array') {
                $nonArray = $newType;
            } else {
                $nonArray = $node['nodeType'];
            }
            // now verify if array contents match the non-array type
            if (($node[self::ARRAY_NAME]['nodeType'] != $nonArray) && ($nonArray != 'null') &&
                $node[self::ARRAY_NAME]['nodeType'] != 'null') {
                throw new JsonParserException("Data array in '" . $nodePath->__toString() .
                    "' contains incompatible types '" . $node[self::ARRAY_NAME]['nodeType'] . "' and '" .
                    $nonArray . "'");
            }
        } else {
            throw new JsonParserException("Data array in '" . $nodePath->__toString() .
                "' contains incompatible types '" . $node[self::ARRAY_NAME]['nodeType'] . "' and '" .
                $newType . "'");
        }
    }

    /**
     * Store a complete node data in the structure.
     * @param NodePath $nodePath Node path.
     * @param array $node Node data.
     */
    public function saveNode(NodePath $nodePath, array $node)
    {
        $this->data = $this->storeNode($nodePath, $this->data, $node);
    }

    /**
     * Store a complete node data in the structure.
     * @param NodePath $nodePath Node path.
     * @param array $data Structure data.
     * @param array $node Node data.
     * @return array Structure data.
     * @throws JsonParserException In case the node path is not valid.
     */
    private function storeNode(NodePath $nodePath, array $data, array $node) : array
    {
        $nodePath = $nodePath->popFirst($nodeName);
        $nodeName = $this->encodeNodeName($nodeName);
        if ($nodePath->isEmpty()) {
            $data[$nodeName] = $node;
        } else {
            if (isset($data[$nodeName])) {
                $data[$nodeName] = $this->storeNode($nodePath, $data[$nodeName], $node);
            } else {
                throw new JsonParserException("Node path " . $nodePath->__toString() . " does not exist.");
            }
        }
        return $data;
    }

    /**
     * Return complete structure.
     * @return array
     */
    public function getData()
    {
        foreach ($this->data as $value) {
            $this->validateDefinitions($value);
        }
        return ['data' => $this->data, 'parent_aliases' => $this->parentAliases];
    }

    /**
     * Get structure of a particular node.
     * @param NodePath $nodePath Node path.
     * @param array $data Optional structure data (for recursive call).
     * @return array|null Null in case the node path does not exist.
     */
    public function getNode(NodePath $nodePath, array $data = null)
    {
        if (empty($data)) {
            $data = $this->data;
        }
        $nodePath = $nodePath->popFirst($nodeName);
        $nodeName = $this->encodeNodeName($nodeName);
        if (!isset($data[$nodeName])) {
            return null;
        }
        if ($nodePath->isEmpty()) {
            return $data[$nodeName];
        } else {
            return $this->getNode($nodePath, $data[$nodeName]);
        }
    }

    /**
     * Return a particular property of the node.
     * @param NodePath $nodePath Node path
     * @param string $property Property name (e.g. 'nodeType')
     * @return mixed Property value or null if the node or property does not exist.
     */
    public function getNodeProperty(NodePath $nodePath, string $property)
    {
        $data = $this->getNode($nodePath);
        if (isset($data[$property])) {
            return $data[$property];
        } else {
            return null;
        }
    }

    /**
     * Return a particular property of a the children of the node.
     * @param NodePath $nodePath Node path
     * @param string $property Property name (e.g. 'nodeType').
     * @return array
     */
    private function getNodeChildrenProperties(NodePath $nodePath, string $property) : array
    {
        $nodeData = $this->getNode($nodePath);
        $result = [];
        if (!empty($nodeData)) {
            if ($nodeData['nodeType'] == 'object') {
                foreach ($nodeData as $itemName => $value) {
                    if (is_array($value)) {
                        $itemName = $this->decodeNodeName($itemName);
                        // it is a child node
                        if (isset($value[$property])) {
                            $result[$itemName] = $value[$property];
                        } else {
                            $result[$itemName] = null;
                        }
                    }
                }
            } elseif ($nodeData['nodeType'] == 'scalar') {
                foreach ($nodeData as $itemName => $value) {
                    if ($itemName == $property) {
                        $result[$nodePath->getLast()] = $value;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get columns from a node and return their data types.
     * @param NodePath $nodePath
     * @return array Index is column name, value is data type.
     */
    public function getColumnTypes(NodePath $nodePath)
    {
        $values = $this->getNodeChildrenProperties($nodePath, 'nodeType');
        $result = [];
        foreach ($values as $key => $value) {
            if ($key === self::ARRAY_NAME) {
                $result[self::DATA_COLUMN] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Generate header names for the whole structure.
     */
    public function generateHeaderNames()
    {
        foreach ($this->data as $baseType => &$baseArray) {
            foreach ($baseArray as $nodeName => &$nodeData) {
                if (is_array($nodeData)) {
                    $nodeName = $this->decodeNodeName($nodeName);
                    $this->generateHeaderName(
                        $nodeData,
                        new NodePath([$baseType, $nodeName]),
                        self::ARRAY_NAME, // # root is always array
                        $baseType
                    );
                }
            }
        }
    }

    /**
     * Get new name of a parent column
     * @param $name
     * @return mixed
     */
    public function getParentTargetName(string $name)
    {
        if (empty($this->parentAliases[$name])) {
            return $name;
        }
        return $this->parentAliases[$name];
    }

    /**
     * Set new name (used in CSV file) of a parent column.
     * @param string $name Original name.
     * @param string $target New Name.
     */
    public function setParentTargetName(string $name, string $target)
    {
        $this->parentAliases[$name] = $target;
    }

    /**
     * Get structure version for compatibility.
     * @return int
     */
    public function getVersion()
    {
        return 3;
    }

    /**
     * Return a legacy type for the given node.
     * @param NodePath $nodePath
     * @return string
     */
    public function getTypeFromNodePath(NodePath $nodePath)
    {
        return $this->getSafeName($nodePath->toCleanString());
    }

    /**
     * If necessary, change the name to a safe to store in database.
     * @param string $name
     * @return string
     */
    private function getSafeName(string $name) : string
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

    /**
     * If necessary change the name to a unique one.
     * @param string $baseType
     * @param string $headerName
     * @return string
     */
    private function getUniqueName(string $baseType, string $headerName) : string
    {
        if (isset($this->headerIndex[$baseType][$headerName])) {
            $newName = $headerName;
            $i = 0;
            while (isset($this->headerIndex[$baseType][$newName])) {
                $newName = $headerName . '_u' . $i;
                $i++;
            }
            $headerName = $newName;
        }
        $this->headerIndex[$baseType][$headerName] = 1;
        return $headerName;
    }

    /**
     * Generate header name for a node and sub-nodes.
     * @param array $data Node data
     * @param NodePath $nodePath
     * @param string $parentName
     * @param string $baseType
     */
    private function generateHeaderName(array &$data, NodePath $nodePath, string $parentName, string $baseType)
    {
        if (empty($data['headerNames'])) {
            // write only once, because generateHeaderName may be called repeatedly
            if ($parentName != self::ARRAY_NAME) {
                // do not generate headers for arrays
                $headerName = $this->getSafeName($parentName);
                $headerName = $this->getUniqueName($baseType, $headerName);
                $data['headerNames'] = $headerName;
            } else {
                $data['headerNames'] = self::DATA_COLUMN;
            }
        }
        if ($data['nodeType'] == 'array') {
            // array node creates a new type and does not nest deeper
            $baseType = $baseType . '.' . $parentName;
            $parentName = '';
        }
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $key = $this->decodeNodeName($key);
                if (!$parentName || (!empty($data[$key]['type']) && $data[$key]['type'] == 'parent')) {
                    // skip nesting if there is nowhere to nest (array or parent-type child)
                    $childName = $key;
                } else {
                    $childName = $parentName . '.' . $key;
                }
                $this->generateHeaderName($value, $nodePath->addChild($key), $childName, $baseType);
            }
        }
    }

    /**
     * Load structure data.
     * @param array $definitions
     */
    public function load(array $definitions)
    {
        $this->data = $definitions['data'] ?? [];
        $this->parentAliases = $definitions['parent_aliases'] ?? [];
        foreach ($this->data as $value) {
            $this->validateDefinitions($value);
        }
    }

    private function validateDefinitions(array $definitions)
    {
        $knownProps = [self::PROP_HEADER, self::PROP_NODE_DATA_TYPE, self::PROP_NODE_TYPE];
        if (!isset($definitions[self::PROP_NODE_DATA_TYPE])) {
            throw new JsonParserException("Node data type is not set.", $definitions);
        }
        if (($definitions[self::PROP_NODE_DATA_TYPE] == 'array') && empty($definitions[self::ARRAY_NAME])) {
            throw new JsonParserException("Array node does not have array.", $definitions);
        }
        foreach ($definitions as $key => $value) {
            if (is_array($value)) {
                // it's a json property
                if (in_array($key, $knownProps)) {
                    throw new JsonParserException("Conflict property $key", $definitions);
                }
                $this->validateDefinitions($value);
                if ($key == self::ARRAY_NAME) {
                    if ($definitions[self::PROP_NODE_DATA_TYPE] != 'array') {
                        throw new JsonParserException("Array $key is not an array.", $definitions);
                    }
                }
            } else {
                // it's our property
                if (!in_array($key, $knownProps)) {
                    throw new JsonParserException("Undefined property $key", $definitions);
                }
                if ($key == self::PROP_NODE_DATA_TYPE) {
                    if (!in_array($value, self::$nodeDataTypes)) {
                        throw new JsonParserException("Undefined data type $value", $definitions);
                    }
                } elseif ($key == self::PROP_NODE_TYPE) {
                    if (!in_array($value, self::$nodeTypes)) {
                        throw new JsonParserException("Undefined node type $value", $definitions);
                    }
                }
            }
        }
    }
}
