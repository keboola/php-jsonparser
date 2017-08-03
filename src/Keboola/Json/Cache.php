<?php

namespace Keboola\Json;

class Cache
{
    protected $data = [];

    /**
     * PHP temp://temp/
     * @var resource
     */
    protected $temp;

    protected $readPosition = 0;

    protected $memoryLimit = null;

    public function store($data)
    {
        // TODO ensure at least X MB is left free (X should be possible to change -> Parser::getCache()->setMemLimit(X))
        // either to stop using memory once X mem is used or once X is left from PHP limit
        if (ini_get('memory_limit') != "-1"
            && memory_get_usage() > (\Keboola\Utils\returnBytes(ini_get('memory_limit')) * 0.25)
            || ($this->memoryLimit !== null && memory_get_usage() > $this->memoryLimit)
        ) {
            // cache
            if (empty($this->temp)) {
                // TODO use /maxmemory ?
                $this->temp = fopen("php://temp/", 'w+');
            }

            fseek($this->temp, 0, SEEK_END);
            fputs($this->temp, base64_encode(serialize($data)) . PHP_EOL);
        } else {
            $this->data[] = $data;
        }
    }

    public function getNext()
    {
        if (!empty($this->temp) && !feof($this->temp)) {
            // keep the file position in case the file's been written to
            fseek($this->temp, $this->readPosition);
            $data = fgets($this->temp);
            $this->readPosition += strlen($data);

            return unserialize(base64_decode($data));
        } elseif (!empty($this->temp) && feof($this->temp)) {
            fclose($this->temp);
            unset($this->temp);
        }

        return array_shift($this->data);
    }

    /**
     * @param int $limit
     */
    public function setMemoryLimit(int $limit)
    {
        $this->memoryLimit = $limit;
    }
}
