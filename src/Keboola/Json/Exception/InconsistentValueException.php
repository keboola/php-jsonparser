<?php

namespace Keboola\Json\Exception;

class InconsistentValueException extends \Exception
{
    private $previous;
    private $new;
    private $key;

    public function __construct($previous, $new, $key)
    {
        parent::__construct("Iconsisten value");
        $this->previous = $previous;
        $this->new = $new;
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getNew()
    {
        return $this->new;
    }

    public function getPreviousValue()
    {
        return $this->previous;
    }
}
