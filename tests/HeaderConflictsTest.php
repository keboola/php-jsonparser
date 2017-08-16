<?php

namespace Keboola\Json\Tests;

use Keboola\Json\Analyzer;
use Keboola\Json\Parser;
use Keboola\Json\Structure;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class HeaderConflictsTest extends TestCase
{
    public function testObjectArrayCombinedConflictObject()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "first_third_fourth": "origin",
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }'
        );
        $parser->process($testFile->components);

        $result = "\"first_third_fourth\",\"first_second\",\"first_third_fourth_u0\"\n" .
            "\"origin\",\"root.first_44fdc1ad4311801c0a6f586c0c1d113d\",\"last\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_44fdc1ad4311801c0a6f586c0c1d113d\"\n" .
            "\"b\",\"root.first_44fdc1ad4311801c0a6f586c0c1d113d\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictArray()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    },
                    "first_second": "origin"
                }]
            }'
        );
        $parser->process($testFile->components);

        $result = "\"first_second\",\"first_third_fourth\",\"first_second_u0\"\n" .
            "\"root.first_77ad9e5b9cf69f800a67c071287a675e\",\"last\",\"origin\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_77ad9e5b9cf69f800a67c071287a675e\"\n" .
            "\"b\",\"root.first_77ad9e5b9cf69f800a67c071287a675e\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictParentId()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "JSON_parentId": "origin",
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'root', 'someValue');

        $result = "\"JSON_parentId\",\"first_second\",\"first_third_fourth\",\"JSON_parentId_u0\"\n" .
            "\"origin\",\"root.first_cebcde73e3d46faaa92d77a7499dc9cf\",\"last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_cebcde73e3d46faaa92d77a7499dc9cf\"\n" .
            "\"b\",\"root.first_cebcde73e3d46faaa92d77a7499dc9cf\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictParentIdArray()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "someKey": "origin",
                    "first": {
                        "second": ["a", "b"],
                        "third": {
                            "fourth": "last"
                        }
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'boo', ['someKey' => 'someValue']);

        $result = "\"someKey\",\"first_second\",\"first_third_fourth\",\"someKey_u0\"\n" .
            "\"origin\",\"boo.first_e9223139b42fd7d2c1ea16aac27af9c2\",\"last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"boo.first_e9223139b42fd7d2c1ea16aac27af9c2\"\n" .
            "\"b\",\"boo.first_e9223139b42fd7d2c1ea16aac27af9c2\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo_first_second']));
    }

    public function testObjectArrayCombinedMultiConflict()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "first_second": "origin",
                    "first": {
                        "second": ["a", "b"]
                    },
                    "first.second": "origin2"
                }]
            }'
        );
        $parser->process($testFile->components);

        $result = "\"first_second\",\"first_second_u0\",\"first_second_u1\"\n" .
            "\"origin\",\"root.first_06f40a9e874ef5271aaf5fb696c5d428\",\"origin2\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_06f40a9e874ef5271aaf5fb696c5d428\"\n" .
            "\"b\",\"root.first_06f40a9e874ef5271aaf5fb696c5d428\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

}
