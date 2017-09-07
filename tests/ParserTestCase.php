<?php

namespace Keboola\Json\Tests;

use PHPUnit\Framework\TestCase;

class ParserTestCase extends TestCase
{
    protected function loadJson($fileName)
    {
        $testFilesPath = $this->getDataDir() . $fileName . ".json";
        $file = file_get_contents($testFilesPath);
        return \Keboola\Utils\jsonDecode($file);
    }

    protected function getDataDir()
    {
        return __DIR__ . "/_data/";
    }
}
