<?php

namespace Keboola\Json;

use Keboola\Json\Test\ParserTestCase;

class HeadersTest extends ParserTestCase
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

    public function testEmptyObject()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{}]}'
        );
        $parser->process($testFile->components);
        self::assertEquals(['root'], array_keys($parser->getCsvFiles()));
        self::assertEquals("\n", file_get_contents($parser->getCsvFiles()['root']->getPathname()));
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

    public function testLongHeader()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $testFile = \Keboola\Utils\jsonDecode(
            '{"components": [{"AReallyTrulyIncrediblyHellishlyLongFromOuterSpaceAndAgePropertyName": null}]}'
        );
        $parser->process($testFile->components);

        $result = "\"9f656540f0cee93f2a26f4f9770bc70d\"\n\"\"\n";
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
}
