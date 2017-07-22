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

    public function fromString($pathStr) {
        $path = explode('.', $pathStr);

    }

    public function __toString()
    {
        return implode('.', $this->path);
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
}