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
        $result = "\"data\",\"JSON_parentId_u0\"\n" .
            "\"a\",\"root.first_cebcde73e3d46faaa92d77a7499dc9cf\"\n" .
            "\"b\",\"root.first_cebcde73e3d46faaa92d77a7499dc9cf\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['root_first_second']));
    }

    public function testObjectArrayCombinedConflictParentIdArray()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "someKey": "origin",
                        "first": {
                            "second": ["a", "b"],
                            "third": {
                                "fourth": "first"
                            }
                        }
                    },
                    {
                        "someKey": "origin2",
                        "first": {
                            "second": ["c", "d"],
                            "third": {
                                "fourth": "last"
                            }
                        }
                    },
                    {
                        "first": {
                            "second": ["e", "f"],
                            "third": {
                                "fourth": "really-last"
                            }
                        }
                    }                                        
                ]
            }'
        );
        $parser->process($testFile->components, 'boo', ['someKey' => 'someValue']);

        $result = "\"someKey\",\"first_second\",\"first_third_fourth\",\"someKey_u0\"\n" .
            "\"origin\",\"boo.first_7e79d183dd748759aa22be0fb6fc28cd\",\"first\",\"someValue\"\n" .
            "\"origin2\",\"boo.first_08498865105c0356b251f57e041fc7b5\",\"last\",\"someValue\"\n" .
            "\"\",\"boo.first_6444c4e0bd5ff7e054936b992fae8dd4\",\"really-last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"boo.first_7e79d183dd748759aa22be0fb6fc28cd\"\n" .
            "\"b\",\"boo.first_7e79d183dd748759aa22be0fb6fc28cd\"\n" .
            "\"c\",\"boo.first_08498865105c0356b251f57e041fc7b5\"\n" .
            "\"d\",\"boo.first_08498865105c0356b251f57e041fc7b5\"\n" .
            "\"e\",\"boo.first_6444c4e0bd5ff7e054936b992fae8dd4\"\n" .
            "\"f\",\"boo.first_6444c4e0bd5ff7e054936b992fae8dd4\"\n";
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

    public function testObjectArrayCombinedConflictParentIdArrayMultiBatch()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "someKey": "origin",
                        "first": {
                            "second": ["a", "b"],
                            "third": {
                                "fourth": "first"
                            }
                        }
                    }                                    
                ]
            }'
        );
        $parser->process($testFile->components, 'boo', ['someKey' => 'someValue']);

        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "someKey": "origin2",
                        "first": {
                            "second": ["c", "d"],
                            "third": {
                                "fourth": "last"
                            }
                        }
                    },
                    {
                        "first": {
                            "second": ["e", "f"],
                            "third": {
                                "fourth": "really-last"
                            }
                        }
                    }                                        
                ]
            }'
        );
        $parser->process($testFile->components, 'boo', ['someKey' => 'someValue']);

        $result = "\"someKey\",\"first_second\",\"first_third_fourth\",\"someKey_u0\"\n" .
            "\"origin\",\"boo.first_7e79d183dd748759aa22be0fb6fc28cd\",\"first\",\"someValue\"\n" .
            "\"origin2\",\"boo.first_08498865105c0356b251f57e041fc7b5\",\"last\",\"someValue\"\n" .
            "\"\",\"boo.first_6444c4e0bd5ff7e054936b992fae8dd4\",\"really-last\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo']));
        $result = "\"data\",\"JSON_parentId\"\n" .
            "\"a\",\"boo.first_7e79d183dd748759aa22be0fb6fc28cd\"\n" .
            "\"b\",\"boo.first_7e79d183dd748759aa22be0fb6fc28cd\"\n" .
            "\"c\",\"boo.first_08498865105c0356b251f57e041fc7b5\"\n" .
            "\"d\",\"boo.first_08498865105c0356b251f57e041fc7b5\"\n" .
            "\"e\",\"boo.first_6444c4e0bd5ff7e054936b992fae8dd4\"\n" .
            "\"f\",\"boo.first_6444c4e0bd5ff7e054936b992fae8dd4\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo_first_second']));
    }

    public function testObjectArrayNestedConflictParentIdArrayMultiBatch()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "id": "1",
                        "first": [
                            {
                                "JSON_parentId": 2,
                                "third": {
                                    "fourth": "first"
                                }
                            }
                        ]
                    }                                    
                ]
            }'
        );
        $parser->process($testFile->components, 'boo', ['someKey' => 'someValue']);

        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "id": 3,
                        "first": [
                            {
                                "JSON_parentId": 4,
                                "third": {
                                    "fourth": "second"
                                }
                            }   
                        ]
                    },
                    {
                        "id": 5,
                        "first": [
                            {
                                "JSON_parentId": 6,
                                "third": {
                                    "fourth": "last"
                                }
                            }
                        ]
                    }                                        
                ]
            }'
        );
        $parser->process($testFile->components, 'boo', ['someKey' => 'someValue']);
        self::assertEquals(['boo', 'boo_first'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"first\",\"someKey\"\n" .
            "\"1\",\"boo_9cfc728a40e47a7d15e9cbca7150a589\",\"someValue\"\n" .
            "\"3\",\"boo_6bd48be59b1924bf9e08c6836d332cd5\",\"someValue\"\n" .
            "\"5\",\"boo_b1f5855828a92efc42e75adb30a5933a\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo']));
        $result = "\"JSON_parentId\",\"third_fourth\",\"JSON_parentId_u0\"\n" .
            "\"2\",\"first\",\"boo_9cfc728a40e47a7d15e9cbca7150a589\"\n" .
            "\"4\",\"second\",\"boo_6bd48be59b1924bf9e08c6836d332cd5\"\n" .
            "\"6\",\"last\",\"boo_b1f5855828a92efc42e75adb30a5933a\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['boo_first']));
    }

    public function testObjectArrayCombinedConflictParentIdMetadata()
    {
        $metadata = [
            '_components' => [
                '[]' => [
                    'nodeType' => 'object',
                    '_id' => [
                        'nodeType' => 'scalar',
                        'headerNames' => 'id',
                    ],
                    '_column' => [
                        'nodeType' => 'scalar',
                        'headerNames' => 'column'
                    ],
                    'headerNames' => 'data',
                    '_column_u0' => [
                        'nodeType' => 'scalar',
                        'type' => 'parent',
                        'headerNames' => 'column_u0'
                    ]
                ],
                'nodeType' => 'array'
            ]
        ];
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()), $metadata);
        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "id": 3,
                        "column": "test"
                    }                                    
                ]
            }'
        );
        $parser->process($testFile->components, 'components', ['column' => 'someValue']);

        $testFile = \Keboola\Utils\jsonDecode(
            '{
                "components": [
                    {
                        "id": 4,
                        "column": "test2"
                    }                                    
                ]
            }'
        );
        $parser->process($testFile->components, 'components', ['column' => 'someValue']);
        self::assertEquals(['components'], array_keys($parser->getCsvFiles()));
        $result = "\"id\",\"column\",\"column_u0\"\n" .
            "\"3\",\"test\",\"someValue\"\n" .
            "\"4\",\"test2\",\"someValue\"\n";
        self::assertEquals($result, file_get_contents($parser->getCsvFiles()['components']));
    }
}
