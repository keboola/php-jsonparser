<?php

namespace Keboola\Json;

use Keboola\Json\Test\ParserTestCase;

class HeadersConflictsTest extends ParserTestCase
{
    public function testObjectArrayCombinedConflictObject()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
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

        $result = "\"first_third_fourth\",\"first_second\",\"8d3f89981c1fbff97539f2425921e12c\"\n" .
            "\"last\",\"root.first_44fdc1ad4311801c0a6f586c0c1d113d\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_44fdc1ad4311801c0a6f586c0c1d113d\"\n" .
            "\"b\",\"root.first_44fdc1ad4311801c0a6f586c0c1d113d\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
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

        $result = "\"first_second\",\"first_third_fourth\",\"e0a2f892b0567b007e275a6da91b477c\"\n" .
            "\"origin\",\"last\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_77ad9e5b9cf69f800a67c071287a675e\"\n" .
            "\"b\",\"root.first_77ad9e5b9cf69f800a67c071287a675e\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictParentId()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
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

        $result = "\"JSON_parentId\",\"first_second\",\"first_third_fourth\",\"f961a5805cd1f5622f50f0cae62e9fb0\"\n" .
            "\"someValue\",\"root.first_b6e134e60ec774a85a58431f6c25f5fb\",\"last\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_b6e134e60ec774a85a58431f6c25f5fb\"\n" .
            "\"b\",\"root.first_b6e134e60ec774a85a58431f6c25f5fb\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictParentIdArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
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

        $result = "\"someKey\",\"first_second\",\"first_third_fourth\",\"d8142cbd78c688ae7b47e150472c50c8\"\n" .
            "\"someValue\",\"boo.first_b624c0b4706f52c917cab371be23de78\",\"last\",\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"boo.first_b624c0b4706f52c917cab371be23de78\"\n" .
            "\"b\",\"boo.first_b624c0b4706f52c917cab371be23de78\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo_first_second']));
    }
}
