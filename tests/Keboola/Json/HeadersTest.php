<?php

namespace Keboola\Json;

use Keboola\Json\Test\ParserTestCase;

class HeadersDataTest extends ParserTestCase
{
    /**
     * @expectedException \Keboola\Json\Exception\NoDataException
     * @expectedExceptionMessage Empty data set received for 'root'
     */
    public function testEmptyArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": []}'
        );
        $parser->process($testFile->components);
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Trying to retrieve unknown definitions for 'root'
     */
    public function testEmptyObject()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{}]}'
        );
        $parser->process($testFile->components);
        $parser->getCsvFiles()['root'];
    }

    public function testAlmostEmptyObject()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{"a": null}]}'
        );
        $parser->process($testFile->components);

        $result = "\"a\"\n\"\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testObject()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{"a": "b"}]}'
        );
        $parser->process($testFile->components);

        $result = "\"a\"\n\"b\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": ["a", "b"]}'
        );
        $parser->process($testFile->components);

        $result = "\"data\"\n\"a\"\n\"b\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testObjectNested()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
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
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
    }

    public function testObjectNestedArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [{
                    "first": {
                        "second": ["a", "b"]
                    }
                }]
            }'
        );
        $parser->process($testFile->components);
        $result = "\"first_second\"\n\"root.first_97360eb9d751f9ade2eac71d59bcb37d\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n\"a\",\"root.first_97360eb9d751f9ade2eac71d59bcb37d\"\n".
            "\"b\",\"root.first_97360eb9d751f9ade2eac71d59bcb37d\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedTypeRoot()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'root');

        $result = "\"first_second\",\"first_third_fourth\"" .
            "\n\"root.first_7f1140a136d14046e10820652d536598\",\"last\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_7f1140a136d14046e10820652d536598\"\n" .
            "\"b\",\"root.first_7f1140a136d14046e10820652d536598\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedTypeFake()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'fake');

        $result = "\"first_second\",\"first_third_fourth\"\n\"fake.first_818f78685c9312239f496bb0f4b8532d\",\"last\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['fake']));
    }

    public function testObjectArrayCombinedTypeComponents()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'components');

        $result = "\"first_second\",\"first_third_fourth\"\n" .
            "\"components.first_c7488f8cf51da89c1ea29d97dec04628\",\"last\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['components']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"components.first_c7488f8cf51da89c1ea29d97dec04628\"\n" .
            "\"b\",\"components.first_c7488f8cf51da89c1ea29d97dec04628\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['components_first_second']));
    }

    public function testObjectArrayCombinedTypeInner()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'first_second');

        $result = "\"first_second\",\"first_third_fourth\"\n" .
            "\"first_second.first_f907b0c59507357e04c8d96eae1acf5c\",\"last\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['first_second']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"first_second.first_f907b0c59507357e04c8d96eae1acf5c\"\n" .
            "\"b\",\"first_second.first_f907b0c59507357e04c8d96eae1acf5c\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['first_second_first_second']));
    }

    public function testObjectArrayCombinedParentId()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'root', 'someId');

        $result = "\"first_second\",\"first_third_fourth\",\"JSON_parentId\"\n" .
            "\"root.first_bc97f3634c664de7ad096699586b6644\",\"last\",\"someId\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_bc97f3634c664de7ad096699586b6644\"\n" .
            "\"b\",\"root.first_bc97f3634c664de7ad096699586b6644\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedParentIdArray()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'root', ['someId' => 'someValue']);

        $result = "\"first_second\",\"first_third_fourth\",\"someId\"\n" .
            "\"root.first_f34f2e09cb9d4f2c0bcf112d468239bf\",\"last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root.first_f34f2e09cb9d4f2c0bcf112d468239bf\"\n" .
            "\"b\",\"root.first_f34f2e09cb9d4f2c0bcf112d468239bf\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedTypeParentIdArray()
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
                    }
                }]
            }'
        );
        $parser->process($testFile->components, 'root_first_second', ['someId' => 'someValue']);

        $result = "\"first_second\",\"first_third_fourth\",\"someId\"\n" .
            "\"root_first_second.first_1c00277aca5b2395406ccaaabc24fbd7\",\"last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"root_first_second.first_1c00277aca5b2395406ccaaabc24fbd7\"\n" .
            "\"b\",\"root_first_second.first_1c00277aca5b2395406ccaaabc24fbd7\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second_first_second']));
    }

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
