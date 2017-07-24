<?php
namespace Keboola\Json;

use Keboola\Json\Test\ParserTestCase;
use Psr\Log\NullLogger;

class AnalyzerTest extends ParserTestCase
{
    public function testAnalyzeExperimental()
    {
        $data = [
            (object) [
                "id" => 1,
                "arr" => [1,2],
                "obj" => (object) [
                    "str" => "string",
                    "double" => 1.1,
                    "scalar" => "str"
                ]
            ],
            (object) [
                "id" => 2,
                "arr" => [2,3],
                "obj" => (object) [
                    "str" => "another string",
                    "double" => 2.1,
                    "scalar" => 1
                ]
            ]
        ];
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->analyzeData($data, 'root');

        self::assertEquals(
            [
                'root' => [
                    '[]' => [
                        'id' => [
                            'nodeType' => 'scalar'
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar'
                            ]
                        ],
                        'obj' => [
                            'nodeType' => 'object',
                            'str' => [
                                'nodeType' => 'scalar'
                            ],
                            'double' => [
                                'nodeType' => 'scalar'
                            ],
                            'scalar' => [
                                'nodeType' => 'scalar'
                            ]
                        ],
                        'nodeType' => 'object'
                    ]
                ]
            ],
            $analyzer->getStructure()->getData()
        );
    }


    public function testAnalyze()
    {
        $data = [
            (object) [
                "id" => 1,
                "arr" => [1,2],
                "obj" => (object) [
                    "str" => "string",
                    "double" => 1.1,
                    "scalar" => "str"
                ]
            ],
            (object) [
                "id" => 2,
                "arr" => [2,3],
                "obj" => (object) [
                    "str" => "another string",
                    "double" => 2.1,
                    "scalar" => 1
                ]
            ]
        ];
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->analyze($data, 'root');

        self::assertEquals(
            [
                'root.arr' => ['data' => 'scalar'],
                'root.obj' => [
                    'str' => 'scalar',
                    'double' => 'scalar',
                    'scalar' => 'scalar',
                ],
                'root' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar',
                    'obj' => 'object',
                ],
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeComplex()
    {
        $data = [
            (object) [
                "id" => 1,
                "arr" => [1,2],
                "obj" => (object) [
                    "str" => "string",
                    "double" => 1.1,
                    "arr2" => [
                        (object) ["id" => 1],
                        (object) ["id" => 2]
                    ]
                ]
            ],
            (object) [
                "id" => 2,
                "arr" => [2,3],
                "obj" => (object) [
                    "str" => "another string",
                    "double" => 2.1,
                    "arr2" => [
                        (object) ["id" => 3],
                        (object) ["id" => 4]
                    ]
                ]
            ]
        ];
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->analyze($data, 'root');

        self::assertEquals(
            [
                'root.arr' => ['data' => 'scalar'],
                'root.obj.arr2' => [
                    'id' => 'scalar'
                ],
                'root.obj' => [
                    'str' => 'scalar',
                    'double' => 'scalar',
                    'arr2' => 'arrayOfobject',
                ],
                'root' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar',
                    'obj' => 'object',
                ],
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeConflict()
    {
        $data = [
            (object) [
                "arr" => [1,2],
                "obj" => (object) [
                    "str" => "string",
                    "obj2" => (object) [
                        "id" => 1
                    ]
                ]
            ],
            (object) [
                "arr" => [2,3],
                "obj" => (object) [
                    "str" => "another string",
                    "obj2" => (object) [
                        "id" => 1
                    ]
                ]
            ]
        ];
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->analyze($data, 'root');

        self::assertEquals(
            [
                'root.arr' => ['data' => 'scalar'],
                'root.obj' => [
                    'str' => 'scalar',
                    'obj2' => 'object'
                ],
                'root' => [
                    'arr' => 'arrayOfscalar',
                    'obj' => 'object',
                ],
                'root.obj.obj2' => [
                    'id' => 'scalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeStrict()
    {
        $data = [
            (object) [
                "id" => 1,
                "arr" => [1,2],
                "obj" => (object) [
                    "str" => "string",
                    "double" => 1.1
                ]
            ],
            (object) [
                "id" => 2,
                "arr" => [2,3],
                "obj" => (object) [
                    "str" => "another string",
                    "double" => 2.1
                ]
            ]
        ];
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->setStrict(true);
        $analyzer->analyze($data, 'root');

        self::assertEquals(
            [
                'root.arr' => ['data' => 'integer'],
                'root.obj' => [
                    'str' => 'string',
                    'double' => 'double'
                ],
                'root' => [
                    'id' => 'integer',
                    'arr' => 'arrayOfinteger',
                    'obj' => 'object',
                ],
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "integer" to "double" in 'root.id'
     */
    public function testAnalyzeStrictError()
    {
        $data = [
            (object) [
                "id" => 1
            ],
            (object) [
                "id" => 2.2
            ]
        ];
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->setStrict(true);
        $analyzer->analyze($data, 'root');

        self::assertEquals(
            [
                'root.arr' => ['data' => 'integer'],
                'root.obj' => [
                    'str' => 'string',
                    'double' => 'double'
                ],
                'root' => [
                    'id' => 'integer',
                    'arr' => 'array',
                    'obj' => 'object',
                ],
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeAutoArrays()
    {
        $data = [
            (object) [
                'id' => 1,
                'arrOfScalars' => 1,
                'arrOfObjects' => [
                    (object) ['innerId' => 1.1]
                ],
                'arr' => ["a","b"]
            ],
            (object) [
                'id' => 2,
                'arrOfScalars' => [2,3],
                'arrOfObjects' => (object) ['innerId' => 2.1],
                'arr' => ["c","d"]
            ]
        ];

        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);
        $analyzer->analyze($data, 'root');

        self::assertEquals(
            [
                'root.arrOfObjects' => ['innerId' => 'scalar'],
                'root.arr' => ['data' => 'scalar'],
                'root' => [
                    'id' => 'scalar',
                    'arrOfScalars' => 'arrayOfscalar',
                    'arrOfObjects' => 'arrayOfobject',
                    'arr' => 'arrayOfscalar'
                ],
                'root.arrOfScalars' => ['data' => 'scalar'],
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Data array in 'root.arrOfScalars' contains incompatible data types 'scalar' and 'object'!
     */
    public function testAnalyzeAutoArraysError()
    {
        $data = [
            (object) [
                'id' => 1,
                'arrOfScalars' => 1
            ],
            (object) [
                'id' => 2,
                'arrOfScalars' => [
                    (object) [
                        'certainly' => 'not',
                        'a' => 'scalar'
                    ]
                ]
            ],
            (object) [
                'id' => 3,
                'arrOfScalars' => 3
            ]
        ];

        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);
        $analyzer->analyze($data, 'root');
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Data array in 'root.arr' contains incompatible data types 'scalar' and 'object'!
     */
    public function testAnalyzeBadData()
    {
        $data = [
            (object) [
                'id' => 1,
                'arr' => [
                    1,
                    (object) ['two' => 'dva']
                ]
            ]
        ];

        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);
        $analyzer->analyze($data, 'root');
    }

    public function testIsAnalyzed()
    {
        $analyzer = new Analyzer(new NullLogger());

        $data = [
            (object) [
                'id' => 1,
                'str' => "hi"
            ]
        ];

        $analyzer->analyze($data, 'test');
        self::assertFalse($analyzer->isAnalyzed('test'));

        $analyzer = new Analyzer(new NullLogger(), null, null,1);
        self::assertFalse($analyzer->isAnalyzed('test'));
        $analyzer->analyze($data, 'test');
        self::assertTrue($analyzer->isAnalyzed('test'));
    }

    public function testAnalyzeRow()
    {
        $analyzer = new Analyzer(new NullLogger());

        $this->callMethod($analyzer, 'analyzeRow', [new \stdClass, 'empty']);
        self::assertEquals(['empty' => []], $analyzer->getStruct()->getStruct());

        $this->callMethod($analyzer, 'analyzeRow', [(object) [
            'k' => 'v',
            'field' => [
                1, 2
            ]
        ], 'test']);

        self::assertEquals(
            [
                'empty' => [],
                'test.field' => [
                    'data' => 'scalar'
                ],
                'test' => [
                    'k' => 'scalar',
                    'field' => 'arrayOfscalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeKnownArray()
    {
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);

        $data1 = [
            (object) [
                'id' => 1,
                'arr' => [1,2]
            ]
        ];

        $data2 = [
            (object) [
                'id' => 2,
                'arr' => 3
            ]
        ];

        $analyzer->analyze($data1, 'test');
        $analyzer->analyze($data2, 'test');

        self::assertEquals(
            [
                'test.arr' => ['data' => 'scalar'],
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "arrayOfscalar" to "object" in 'test.arr'
     */
    public function testAnalyzeKnownArrayMismatch()
    {
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);

        $data1 = [
            (object) [
                'id' => 1,
                'arr' => [1,2]
            ]
        ];

        $data2 = [
            (object) [
                'id' => 2,
                'arr' => (object) [
                    'innerId' => 2.1
                ]
            ]
        ];

        $analyzer->analyze($data1, 'test');
        $analyzer->analyze($data2, 'test');

        self::assertEquals(
            [
                'test.arr' => ['data' => 'scalar'],
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Data array in 'test.arr' contains incompatible data types 'scalar' and 'object'!
     */
    public function testAnalyzeKnownArrayMismatch2()
    {
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);

        $data1 = [
            (object) [
                'id' => 1,
                'arr' => [1,2]
            ]
        ];

        $data2 = [
            (object) [
                'id' => 2,
                'arr' => [
                    (object) [
                        'innerId' => 2.1
                    ]
                ]
            ]
        ];

        $analyzer->analyze($data1, 'test');
        $analyzer->analyze($data2, 'test');

        self::assertEquals(
            [
                'test.arr' => ['data' => 'scalar'],
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "integer" to "string" in 'test.arr.data'
     */
    public function testAnalyzeKnownArrayMismatchStrict()
    {
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->setStrict(true);

        $data1 = [
            (object) [
                'id' => 1,
                'arr' => [1,2]
            ]
        ];

        $data2 = [
            (object) [
                'id' => 2,
                'arr' => ["a","b"]
            ]
        ];

        $analyzer->analyze($data1, 'test');
        $analyzer->analyze($data2, 'test');

        self::assertEquals(
            [
                'test.arr' => ['data' => 'scalar'],
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeEmptyArrayOfObject()
    {
        $data = [
            (object) [
                'id' => 1,
                'arr' => []
            ],
            (object) [
                'id' => 2,
                'arr' => [
                    (object) ['val' => 'value']
                ]
            ]
        ];

        $analyzer = new Analyzer(new NullLogger());

        $analyzer->analyze($data, 'test');

        self::assertEquals(
            [
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfobject'
                ],
                'test.arr' => [
                    'val' => 'scalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testAnalyzeEmptyArrayOfObjectAutoUpgrade()
    {
        $data = [
            (object) [
                'id' => 1,
                'arr' => []
            ],
            (object) [
                'id' => 2,
                'arr' => (object) ['val' => 'value']
            ],
            (object) [
                'id' => 3,
                'arr' => [
                    (object) ['val' => 'value2'],
                    (object) ['val' => 'value3']
                ]
            ],
            (object) [
                'id' => 4,
                'arr' => (object) ['val' => 'value4']
            ]
        ];

        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);

        $analyzer->analyze($data, 'test');

        self::assertEquals(
            [
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfobject'
                ],
                'test.arr' => [
                    'val' => 'scalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    public function testArrayOfNull()
    {
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->getStruct()->setAutoUpgradeToArray(true);

        $analyzer->analyze(
            [
                (object) [
                    'val' => ['stringArr'],
                    'obj' => [(object) ['key' => 'objValue']]
                ],
                (object) [
                    'val' => [null],
                    'obj' => [null]
                ]
            ],
            's2null'
        );

        $analyzer->analyze(
            [
                (object) [
                    'val' => ['stringArr'],
                    'obj' => [(object) ['key' => 'objValue']]
                ],
                (object) [
                    'val' => [null],
                    'obj' => [null]
                ]
            ],
            'null2s'
        );

        self::assertEquals(
            [
                's2null' => [
                    'val' => 'arrayOfscalar',
                    'obj' => 'arrayOfobject'
                ],
                's2null.val' => [
                    'data' => 'scalar'
                ],
                'null2s' => [
                    'val' => 'arrayOfscalar',
                    'obj' => 'arrayOfobject'
                ],
                'null2s.val' => [
                    'data' => 'scalar'
                ],
                's2null.obj' => [
                    'key' => 'scalar'
                ],
                'null2s.obj' => [
                    'key' => 'scalar'
                ]
            ],
            $analyzer->getStruct()->getStruct()
        );
    }

    /**
     * @dataProvider unsupportedNestingProvider
     */
    public function testUnsupportedNesting($strict, $expectedType)
    {
        $analyzer = new Analyzer(new NullLogger());
        $analyzer->setNestedArrayAsJson(true);
        $analyzer->setStrict($strict);

        $data = [
            [1,2,3,[7,8]],
            [4,5,6]
        ];

        $type = $this->callMethod($analyzer, 'analyzeRow', [$data, 'nest']);
        $this->assertEquals($expectedType, $type);
    }

    public function unsupportedNestingProvider()
    {
        return [
            [true, 'string'],
            [false, 'scalar']
        ];
    }
}
