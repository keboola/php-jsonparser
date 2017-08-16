<?php

namespace Keboola\Json;

class NodePath
{
    const ARRAY_NAME = '[]';
    /**
     * @var array
     */
    private $path;

    public function __construct(array $path)
    {
        $this->path = $path;
    }

    public function __toString()
    {
        return implode('.', $this->path);
    }

    public function toCleanString()
    {
        $path = array_filter($this->path, function ($val) { return $val != '[]';});
        return implode('.', $path);
    }

    public function addArrayChild()
    {
        $path = $this->path;
        $path[] = self::ARRAY_NAME;
        return new NodePath($path);
    }

    public function addChild($key)
    {
        $path = $this->path;
        $path[] = $key;
        return new NodePath($path);
    }

    public function isArray()
    {
        return end($this->path) == self::ARRAY_NAME;
    }

    public function popFirst(&$first)
    {
        $path = $this->path;
        $first = array_shift($path);
        return new NodePath($path);
    }

    public function isEmpty()
    {
        return count($this->path) === 0;
    }

    public function getLast()
    {
        return end($this->path);
    }

    public function popLast() {
        $path = $this->path;
        array_pop($path);
        return new NodePath($path);
    }
}