<?php

declare(strict_types=1);

namespace Keboola\Json\Tests;

use Keboola\Json\Analyzer;
use Keboola\Json\Parser;
use Keboola\Json\Structure;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use function Keboola\Utils\jsonDecode;

class HeadersParentTest extends TestCase
{
    public function testObjectNestedArray(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"]
                    }
                }]
            }',
        );
        $parser->process($testFile->components);
        $result = "\"first_second\"\n\"root.first_97360eb9d751f9ade2eac71d59bcb37d\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
        $result = "\"data\",\"JSON_parentId\"\n\"a\",\"root.first_97360eb9d751f9ade2eac71d59bcb37d\"\n".
            "\"b\",\"root.first_97360eb9d751f9ade2eac71d59bcb37d\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']->getPathName()));
    }

    public function testObjectArrayCombinedParentId(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }',
        );
        $parser->process($testFile->components, 'root', 'someId');

        $result = "\"first_second\",\"first_third_fourth\",\"JSON_parentId\"\n" .
            "\"root.first_bc97f3634c664de7ad096699586b6644\",\"last\",\"someId\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_bc97f3634c664de7ad096699586b6644\"\n" .
            "\"b\",\"root.first_bc97f3634c664de7ad096699586b6644\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']->getPathName()));
    }

    public function testObjectArrayCombinedParentIdArray(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }',
        );
        $parser->process($testFile->components, 'root', ['someId' => 'someValue']);

        $result = "\"first_second\",\"first_third_fourth\",\"someId\"\n" .
            "\"root.first_f34f2e09cb9d4f2c0bcf112d468239bf\",\"last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']->getPathName()));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_f34f2e09cb9d4f2c0bcf112d468239bf\"\n" .
            "\"b\",\"root.first_f34f2e09cb9d4f2c0bcf112d468239bf\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']->getPathName()));
    }

    public function testObjectArrayCombinedTypeParentIdArray(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }',
        );
        $parser->process($testFile->components, 'root_first_second', ['someId' => 'someValue']);

        $result = "\"first_second\",\"first_third_fourth\",\"someId\"\n" .
            "\"root_first_second.first_1c00277aca5b2395406ccaaabc24fbd7\",\"last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']->getPathName()));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root_first_second.first_1c00277aca5b2395406ccaaabc24fbd7\"\n" .
            "\"b\",\"root_first_second.first_1c00277aca5b2395406ccaaabc24fbd7\"\n";
        self::assertEquals(
            $result,
            file_get_contents($parser->getCsvFiles()['root_first_second_first_second']->getPathName()),
        );
    }

    public function testObjectArrayCombinedTypeInner(): void
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

        /** @var \stdClass $testFile */
        $testFile = jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }',
        );
        $parser->process($testFile->components, 'first_second');

        $result = "\"first_second\",\"first_third_fourth\"\n" .
            "\"first_second.first_f907b0c59507357e04c8d96eae1acf5c\",\"last\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['first_second']->getPathName()));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"first_second.first_f907b0c59507357e04c8d96eae1acf5c\"\n" .
            "\"b\",\"first_second.first_f907b0c59507357e04c8d96eae1acf5c\"\n";
        self::assertEquals(
            $result,
            file_get_contents($parser->getCsvFiles()['first_second_first_second']->getPathName()),
        );
    }
}
