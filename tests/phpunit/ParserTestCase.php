<?php

declare(strict_types=1);

namespace Keboola\Json\Tests;

use PHPUnit\Framework\TestCase;
use function Keboola\Utils\jsonDecode;

class ParserTestCase extends TestCase
{
    protected function loadJson(string $fileName): mixed
    {
        $testFilesPath = $this->getDataDir() . $fileName . '.json';
        $file = (string) file_get_contents($testFilesPath);
        return jsonDecode($file);
    }

    protected function getDataDir(): string
    {
        return __DIR__ . '/_data/';
    }
}
