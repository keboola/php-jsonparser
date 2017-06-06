<?php
namespace Keboola\Json\Exception;

use Keboola\Utils\Exception;

class JsonParserException extends Exception
{
    public function __construct($message = "", array $data = [], $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setData($data);
    }
}
