<?php

namespace Keboola\Json;

class Header
{
    private $columns;

    private $parentId;
    /**
     * @var Struct
     */
    private $struct;

    public function __construct(array $columns, $parentId, Struct $struct)
    {
        $this->columns = $columns;
        $this->parentId = $parentId;
        $this->struct = $struct;
    }

    public function getColumn($id)
    {
        return $this->columns[$id];
    }

    public function processHeaders()
    {
        foreach ($this->struct->getData() as $type => $definition) {
            $this->processTypeHeaders($type, $definition);
        }
        var_export($this->columns, true);
    }

    private function processTypeHeaders($type, array $columns)
    {
        $baseType = explode('.', $type)[0];
        $typeElements = explode('.', $type);
        array_shift($typeElements);
        $specificType = implode('.', $typeElements);
        foreach ($this->struct->getDefinitions($type) as $colName => $colType) {
            if ($colType != 'object') {
                $this->columns[$type . '.' . $colName] = $this->createSafeName($specificType . '_' . $colName);
            }
        }
        if ($type == $baseType) {
            if ($this->parentId) {
                if (is_array($this->parentId)) {
                    foreach ($this->parentId as $name) {
                        $this->columns[$name] = $this->createSafeName($name);
                    }
                    //$header = array_merge($header, array_keys($parent));
                } else {
                    $header['JSON_parentId'] = $this->createSafeName('JSON_parentId');
                }
            }
        }
        $this->getHeader($type, $this->parentId);
    }

    protected function getHeader($type, $parent = false)
    {
        $header = [];

        foreach ($this->struct->getDefinitions($type) as $column => $dataType) {
            if ($dataType == "object") {
                foreach ($this->getHeader($type . "." . $column) as $val) {
                    // FIXME this is awkward, the createSafeName shouldn't need to be used twice
                    // (here and in validateHeader again)
                    // Is used to trim multiple "_" in column name before appending
                    $header[] = $this->createSafeName($column) . "_" . $val;
                }
            } else {
                $header[] = $column;
            }
        }

        if ($parent) {
            if (is_array($parent)) {
                $header = array_merge($header, array_keys($parent));
            } else {
                $header[] = "JSON_parentId";
            }
        }

        // TODO set $this->headerNames[$type] = array_combine($validatedHeader, $header);
        // & add a getHeaderNames fn()
        return $this->validateHeader($header);
    }

    protected function createSafeName($name)
    {
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

        $newName = preg_replace('/[^A-Za-z0-9-]/', '_', $newName);
        return trim($newName, "_");
    }

    protected function validateHeader(array $header)
    {
        $newHeader = [];
        foreach ($header as $key => $colName) {
            $newName = $this->createSafeName($colName);

            // prevent duplicates
            if (in_array($newName, $newHeader)) {
                $newHeader[$key] = md5($colName);
            } else {
                $newHeader[$key] = $newName;
            }
        }
        return $newHeader;
    }

}
