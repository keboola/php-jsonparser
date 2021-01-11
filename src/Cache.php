<?php

declare(strict_types=1);

namespace Keboola\Json;

class Cache
{
    protected array $data = [];

    /**
     * PHP temp://temp/
     * @var resource
     */
    protected $temp;

    protected int $readPosition = 0;

    protected ?int $memoryLimit = null;

    public function store(array $data): void
    {
        // TODO ensure at least X MB is left free (X should be possible to change -> Parser::getCache()->setMemLimit(X))
        // either to stop using memory once X mem is used or once X is left from PHP limit
        if (ini_get('memory_limit') !== '-1'
            && memory_get_usage() > (\Keboola\Utils\returnBytes(ini_get('memory_limit')) * 0.25)
            || ($this->memoryLimit !== null && memory_get_usage() > $this->memoryLimit)
        ) {
            // cache
            if (empty($this->temp)) {
                // TODO use /maxmemory ?
                /** @var resource $temp */
                $temp = fopen('php://temp/', 'w+');
                $this->temp = $temp;
            }

            fseek($this->temp, 0, SEEK_END);
            fputs($this->temp, base64_encode(serialize($data)) . PHP_EOL);
        } else {
            $this->data[] = $data;
        }
    }

    public function getNext(): ?array
    {
        if (!empty($this->temp) && !feof($this->temp)) {
            // keep the file position in case the file's been written to
            fseek($this->temp, $this->readPosition);
            $data = fgets($this->temp);
            /** @var string $data */
            $this->readPosition += strlen($data);
            return unserialize(base64_decode($data));
        } elseif (!empty($this->temp) && feof($this->temp)) {
            fclose($this->temp);
            unset($this->temp);
        }

        return array_shift($this->data);
    }

    public function setMemoryLimit(int $limit): void
    {
        $this->memoryLimit = $limit;
    }
}
