<?php
namespace Keboola\Json\Exception;

use Keboola\Utils\Exception\Exception;

class JsonParserException extends Exception {
    public function __construct($message = "", array $data = [], $code = 0, $previous = NULL)
    {
        parent::__construct($message, $code, $previous);
        $this->setData($data);
    }
}
