<?php

use Keboola\Json\Analyzer,
    Keboola\Json\Struct;

require_once 'tests/ParserTestCase.php';


class AnalyzerTest extends ParserTestCase
{
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true));
        $analyzer->analyze($data, 'root');

        $this->assertEquals(
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true));
        $analyzer->setStrict(true);
        $analyzer->analyze($data, 'root');

        $this->assertEquals(
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true));
        $analyzer->setStrict(true);
        $analyzer->analyze($data, 'root');

        $this->assertEquals(
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

        $analyzer = new Analyzer($this->getLogger('analyzer', true));
        $analyzer->getStruct()->setAutoUpgradeToArray(true);
        $analyzer->analyze($data, 'root');

        $this->assertEquals(
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

        $analyzer = new Analyzer($this->getLogger('analyzer', true));
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

        $analyzer = new Analyzer($this->getLogger('analyzer', true));
        $analyzer->getStruct()->setAutoUpgradeToArray(true);
        $analyzer->analyze($data, 'root');
    }

    public function testIsAnalyzed()
    {
        $analyzer = new Analyzer($this->getLogger('analyzer', true));

        $data = [
            (object) [
                'id' => 1,
                'str' => "hi"
            ]
        ];

        $analyzer->analyze($data, 'test');
        $this->assertFalse($analyzer->isAnalyzed('test'));

        $analyzer = new Analyzer($this->getLogger('analyzer', true), null, 1);
        $this->assertFalse($analyzer->isAnalyzed('test'));
        $analyzer->analyze($data, 'test');
        $this->assertTrue($analyzer->isAnalyzed('test'));
    }

    public function testAnalyzeRow()
    {
        $analyzer = new Analyzer($this->getLogger('analyzer', true));

        $this->callMethod($analyzer, 'analyzeRow', [new \stdClass, 'empty']);
        $this->assertEquals(['empty' => []], $analyzer->getStruct()->getStruct());

        $this->callMethod($analyzer, 'analyzeRow', [(object) [
            'k' => 'v',
            'field' => [
                1, 2
            ]
        ], 'test']);

        $this->assertEquals(
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true)/*, $struct*/);
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

        $this->assertEquals(
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true)/*, $struct*/);
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

        $this->assertEquals(
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true)/*, $struct*/);
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

        $this->assertEquals(
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
    public function testAnalyzeKnownArrayMismatch3()
    {
        $analyzer = new Analyzer($this->getLogger('analyzer', true));
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

        $this->assertEquals(
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

        $analyzer = new Analyzer($this->getLogger('analyzer', true));

        $analyzer->analyze($data, 'test');

        $this->assertEquals(
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

        $analyzer = new Analyzer($this->getLogger('analyzer', true));
        $analyzer->getStruct()->setAutoUpgradeToArray(true);

        $analyzer->analyze($data, 'test');

        $this->assertEquals(
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
        $analyzer = new Analyzer($this->getLogger('analyzer', true));
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

        $this->assertEquals(
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
}
