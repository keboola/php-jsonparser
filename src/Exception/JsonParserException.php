<?php

declare(strict_types=1);

namespace Keboola\Json\Exception;

use Keboola\Utils\Exception;
use Throwable;

class JsonParserException extends Exception
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(string $message = '', array $data = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setData($data);
    }
}
