<?php

declare(strict_types=1);

namespace Keboola\Json\Tests;

use Keboola\Json\Analyzer;
use Keboola\Json\Exception\NoDataException;
use Keboola\Json\Parser;
use Keboola\Json\Structure;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class HeadersTest extends TestCase
{
    public function testEmptyArray(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": []}'
        );

        $this->expectException(NoDataException::class);
        $this->expectExceptionMessage("Empty data set received for 'root'");
        $parser->process($testFile->components);
    }

    public function testEmptyObject(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{}]}'
        );
        $parser->process($testFile->components);
        self::assertEquals(['root'], array_keys($parser->getCsvFiles()));
        self::assertEquals("\n", file_get_contents($parser->getCsvFiles()['root']->getPathName()));
    }

    public function testAlmostEmptyObject(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{"a": null}]}'
        );
        $parser->process($testFile->components);

        $result = "\"a\"\n\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
    }

    public function testLongHeader(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{"AReallyTrulyIncrediblyHellishlyLongFromOuterSpaceAndAgePropertyName": null}]}'
        );
        $parser->process($testFile->components);

        $result = "\"9f656540f0cee93f2a26f4f9770bc70d\"\n\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
    }

    public function testObject(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{"a": "b"}]}'
        );
        $parser->process($testFile->components);

        $result = "\"a\"\n\"b\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
    }

    public function testArray(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": ["a", "b"]}'
        );
        $parser->process($testFile->components);

        $result = "\"data\"\n\"a\"\n\"b\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
    }

    public function testObjectNested(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": {
                            "third": "fourth"
                        }                        
                    }
                }]
            }'
        );
        $parser->process($testFile->components);

        $result = "\"first_second_third\"\n\"fourth\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
    }
}
