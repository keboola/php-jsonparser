<?php

namespace Keboola\Json;

class NodePath
{
    /**
     * @var array
     */
    private $path;

    /**
     * NodePath constructor.
     * @param array $path
     */
    public function __construct(array $path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return implode('.', $this->path);
    }

    /**
     * Convert path to user-display string.
     * @return string
     */
    public function toCleanString() : string
    {
        $path = array_filter($this->path, function ($val) {
            return $val != Structure::ARRAY_NAME;
        });
        return implode('.', $path);
    }

    /**
     * Return new path with an added child.
     * @param string $key
     * @return NodePath
     */
    public function addChild(string $key) : NodePath
    {
        $path = $this->path;
        $path[] = $key;
        return new NodePath($path);
    }

    /**
     * Return true if the path points to array.
     * @return bool
     */
    public function isArray() : bool
    {
        return end($this->path) == Structure::ARRAY_NAME;
    }

    /**
     * Remove the first item from path and return new path
     * @param string $first
     * @return NodePath
     */
    public function popFirst(&$first) : NodePath
    {
        $path = $this->path;
        $first = array_shift($path);
        return new NodePath($path);
    }

    /**
     * @return bool
     */
    public function isEmpty() : bool
    {
        return count($this->path) === 0;
    }

    /**
     * Return last item of the path
     * @return string
     */
    public function getLast() : string
    {
        return end($this->path);
    }

    /**
     * Remove last item of the path and return new path.
     * @return NodePath
     */
    public function popLast() : NodePath
    {
        $path = $this->path;
        array_pop($path);
        return new NodePath($path);
    }
}
