<?php

declare(strict_types=1);

namespace Keboola\Json\Tests;

use PHPUnit\Framework\TestCase;

class ParserTestCase extends TestCase
{
    /**
     * @return mixed
     */
    protected function loadJson(string $fileName)
    {
        $testFilesPath = $this->getDataDir() . $fileName . '.json';
        $file = (string) file_get_contents($testFilesPath);
        return \Keboola\Utils\jsonDecode($file);
    }

    protected function getDataDir(): string
    {
        return __DIR__ . '/_data/';
    }
}
