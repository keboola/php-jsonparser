<?php

namespace Keboola\Json;

class Header2
{
    private $parentId;
    /**
     * @var Struct
     */
    private $struct;

    /**
     * @var []
     */
    private $columns;
    private $columnIndex;
    private $columns2;

    public function __construct($parentId, Struct $struct)
    {
        $this->parentId = $parentId;
        $this->struct = $struct;
    }

    public function processHeaders($rootType)
    {
        $this->columns = $this->getColumn('root');
        $this->columns2[$rootType] = $this->getColumnProcess('root', []);
        var_export($this->columns, true);
       // foreach ($this->struct->getDefinitions($rootType) as $column => $type) {
       //     $this->setColumn($rootType, $column, [$rootType]);
        //}
    }

    public function getHeaders($type, $data = null) {
        if (empty($data)) {
            $data = $this->columns2;
        }
        if (isset($data[$type])) {
            $result = $this->getHeader($data[$type]);
        } else {
            $types = explode('.', $type);
            $baseType = array_shift($types);
            $result = $this->getHeaders(implode('.', $types), $data[$baseType]);
        }
        return $result;
    }

    public function getHeader($type)
    {
        $result = [];
        foreach ($type as $column) {
            if (is_array($column)) {
                $result = array_merge($result, $this->getHeader($column));
            } else {
                $result[] = $column;
            }
        }
        return $result;
    }

    public function setColumn($type, $columnName, $path)
    {
        foreach ($this->struct->getDefinitions($type) as $column => $columnType) {
            if ($columnType == 'scalar') {
                $this->columns[$path] = $columnType;
            } else {
                $this->setColumn($columnName, $column, array_push($path, $columnName));
            }
        }
    }


    public function getColumn($type) {
        $columns = [];
        foreach ($this->struct->getDefinitions($type) as $column => $columnType) {
            if ($columnType == 'scalar') {
                $columns[$column] = $columnType;
            } else {
                $columns[$column] = $this->getColumn($type . '.' . $column);
            }
        }
        return $columns;
    }

    public function findColumn($type, $column, $data = null) {
        if (empty($data)) {
            $data = $this->columns2;
        }
        if (isset($data[$type])) {
            $result = $this->doFind($data[$type], $column);
        } else {
            $types = explode('.', $type);
            $baseType = array_shift($types);
            $result = $this->findColumn(implode('.', $types), $column, $data[$baseType]);
        }
        return $result;
    }

    public function doFind($data, $column)
    {
        if (isset($data[$column])) {
            return $data[$column];
        } else {
            $types = explode('.', $column);
            $type = array_shift($types);
            return $this->doFind($data[$type], implode('.', $types));
        }
    }

    public function getColumnProcess($type, $path, $isArrayNode = false) {
        $columns = [];
        foreach ($this->struct->getDefinitions($type) as $column => $columnType) {
            if ($columnType == 'scalar') {
                if ($isArrayNode) {
                    // vyjimka pro pole, ktera je v struct v polozce 'data', ale netvori to nazev sloupce
                    // TODO: ovsem asi jen v pripade, ze to v korenu
                    $columnName = '';
                } else {
                    $columnName = $column;
                }
                if (empty($path)) {
                    $safeName = $this->getSafeName($columnName);
                } else {
                    $safeName = $this->getSafeName(implode('_', $path) . '.' . $columnName);
                }
                $safeName = $this->checkDuplicates($safeName);
                $columns[$column] = $safeName;
            } else {
                $columns[$column] = $this->getColumnProcess($type . '.' . $column, array_merge($path, [$column]), $columnType == 'arrayOfscalar');
            }
        }
        return $columns;
    }

    public function checkDuplicates($name)
    {
        if (isset($this->columnIndex[$name])) {
            $name .= '_1';
        }
        $this->columnIndex[$name] = 1;
        return $name;
    }

    public function getSafeName($name)
    {
        return str_replace('.', '_', $name);
    }

    public function processColumns()
    {
        $this->processColumn($this->columns, []);
    }

    public function processColumn($items, $path)
    {
        $columns = [];
        foreach ($items as $name => $content) {
            if (is_array($content)) {
                $this->processColumn($content, $path + [$name]);
            } else {
                $columns[$name] =2;
            }
        }
    }
}
