<?php
namespace Keboola\Json\Test;

use Keboola\Json\Parser;
use Psr\Log\NullLogger;

class ParserTestCase extends \PHPUnit_Framework_TestCase
{
    protected function loadJson($fileName)
    {
        $testFilesPath = $this->getDataDir() . $fileName . ".json";
        $file = file_get_contents($testFilesPath);
        return \Keboola\Utils\jsonDecode($file);
    }

    protected function getDataDir()
    {
        return __DIR__ . "/../../../../tests/_data/";
    }
}
