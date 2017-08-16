<?php

namespace Keboola\Json;

use Psr\Log\NullLogger;

class AnalyzerTest extends \PHPUnit_Framework_TestCase
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
        $analyzer = new Analyzer(new NullLogger(), new Structure());
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
                    ],
                    'nodeType' => 'array'
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
        $analyzer = new Analyzer(new NullLogger(), new Structure());
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
                    ],
                    'nodeType' => 'array',
                ],
            ],
            $analyzer->getStructure()->getData()
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
        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'root');

        self::assertEquals(
            [
                'root' => [
                    '[]' => [
                        'id' => [
                            'nodeType' => 'scalar'
                        ],
                        'arr' => [
                            '[]' => [
                                'nodeType' => 'scalar'
                            ],
                            'nodeType' => 'array'
                        ],
                        'obj' => [
                            'nodeType' => 'object',
                            'str' => [
                                'nodeType' => 'scalar'
                            ],
                            'double' => [
                                'nodeType' => 'scalar'
                            ],
                            'arr2' => [
                                '[]' => [
                                    'nodeType' => 'object',
                                    'id' => [
                                        'nodeType' => 'scalar'
                                    ],
                                ],
                                'nodeType' => 'array'
                            ]
                        ],
                        'nodeType' => 'object'
                    ],
                    'nodeType' => 'array',
                ],
            ],
            $analyzer->getStructure()->getData()
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
        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'root');

        self::assertEquals(
            [
                'root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
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
                            'obj2' => [
                                'nodeType' => 'object',
                                'id' => [
                                    'nodeType' => 'scalar'
                                ]
                            ]
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
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
        $analyzer = new Analyzer(new NullLogger(), new Structure(), false, true);
        $analyzer->analyzeData($data, 'root');

        self::assertEquals(
            [
                'root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'id' => [
                            'nodeType' => 'integer',
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'integer'
                            ]
                        ],
                        'obj' => [
                            'nodeType' => 'object',
                            'str' => [
                                'nodeType' => 'string'
                            ],
                            'double' => [
                                'nodeType' => 'double'
                            ],
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "integer" to "double" in "root.[].id"
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
        $analyzer = new Analyzer(new NullLogger(), new Structure(), false, true);
        $analyzer->analyzeData($data, 'root');
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

        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'root');

        self::assertEquals(
            [
                'root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'id' => [
                            'nodeType' => 'scalar'
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                        'arrOfScalars' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar'
                            ],
                        ],
                        'arrOfObjects' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'object',
                                'innerId' => [
                                    'nodeType' => 'scalar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Data array in 'root.[].arrOfScalars' contains incompatible types 'object' and 'scalar'
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

        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'root');
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "scalar" to "object" in "root.[].arr.[]"
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

        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'root');
    }

    public function testAnalyzeEmpty()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData([new \stdClass], 'test');

        self::assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'null',
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    public function testAnalyzeRowEmpty()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData([
            new \stdClass,
            (object) [
                'k' => 'v',
                'field' => [
                    1, 2
                ]
            ]
        ], 'test');

        self::assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'k' => [
                            'nodeType' => 'scalar'
                        ],
                        'field' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar'
                            ]
                        ]
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    public function testAnalyzeKnownArray()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure());
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

        $analyzer->analyzeData($data1, 'test');
        $analyzer->analyzeData($data2, 'test');

        self::assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'id' => [
                            'nodeType' => 'scalar',
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Data array in 'test.[].arr' contains incompatible types 'scalar' and 'object'
     */
    public function testAnalyzeKnownArrayMismatch()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure());
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

        $analyzer->analyzeData($data1, 'test');
        $analyzer->analyzeData($data2, 'test');
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "scalar" to "object" in "test.[].arr.[]"
     */
    public function testAnalyzeKnownArrayMismatch2()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure());
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

        $analyzer->analyzeData($data1, 'test');
        $analyzer->analyzeData($data2, 'test');

        self::assertEquals(
            [
                'test.arr' => ['data' => 'scalar'],
                'test' => [
                    'id' => 'scalar',
                    'arr' => 'arrayOfscalar'
                ]
            ],
            $analyzer->getStructure()->getData()
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "integer" to "string" in "test.[].arr.[]"
     */
    public function testAnalyzeKnownArrayMismatchStrict()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure(false), false, true);
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

        $analyzer->analyzeData($data1, 'test');
        $analyzer->analyzeData($data2, 'test');
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

        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'test');

        self::assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'id' => [
                            'nodeType' => 'scalar'
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'object',
                                'val' => [
                                    'nodeType' => 'scalar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
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

        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData($data, 'test');

        self::assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'id' => [
                            'nodeType' => 'scalar',
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'object',
                                'val' => [
                                    'nodeType' => 'scalar',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    public function testArrayOfNull()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure());
        $analyzer->analyzeData(
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

        $analyzer->analyzeData(
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
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'val' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar'
                            ],
                        ],
                        'obj' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'object',
                                'key' => [
                                    'nodeType' => 'scalar'
                                ],
                            ],
                        ],
                    ],
                ],
                'null2s' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'val' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                        'obj' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'object',
                                'key' => [
                                    'nodeType' => 'scalar'
                                ]
                            ],
                        ],
                    ],
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

    public function testUnsupportedNestingStrict()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure(), true, true);
        $analyzer->analyzeData(
            [
                [1,2,3,[7,8]],
                [4,5,6]
            ],
            'test'
        );
        $this->assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'string'
                    ]
                ]
            ],
            $analyzer->getStructure()->getData()
        );
    }

    public function testUnsupportedNestingNoStrict()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure(), true, false);
        $analyzer->analyzeData(
            [
                [1,2,3,[7,8]],
                [4,5,6]
            ],
            'test'
        );
        $this->assertEquals(
            [
                'test' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'scalar'
                    ]
                ]
            ],
            $analyzer->getStructure()->getData()
        );
    }

    public function testAnalyzeTypesStrict()
    {
        $analyzer = new Analyzer(new NullLogger(), new Structure(), false, true);
        $data1 = [
            (object) [
                'id' => 1,
                'arr' => [1,2],
                'string' => 'text',
                'float' => 2.4,
                'bool' => false,
                'null' => null
            ]
        ];
        $analyzer->analyzeData($data1, 'test');
        self::assertEquals(
            [

                'test' => [
                    '[]' => [
                        'nodeType' => 'object',
                        'id' => [
                            'nodeType' => 'integer',
                        ],
                        'arr' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'integer',
                            ],
                        ],
                        'string' => [
                            'nodeType' => 'string',
                        ],
                        'float' => [
                            'nodeType' => 'double',
                        ],
                        'bool' => [
                            'nodeType' => 'boolean',
                        ],
                        'null' => [
                            'nodeType' => 'null',
                        ],
                    ],
                    'nodeType' => 'array',
                ],
            ],
            $analyzer->getStructure()->getData()
        );
    }

}
