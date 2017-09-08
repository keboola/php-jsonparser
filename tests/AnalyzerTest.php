<?php

namespace Keboola\Json\Tests;

use Keboola\Json\Analyzer;
use Keboola\Json\Structure;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AnalyzerTest extends TestCase
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
                'data' => [
                    '_root' => [
                        '[]' => [
                            '_id' => [
                                'nodeType' => 'scalar'
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ]
                            ],
                            '_obj' => [
                                'nodeType' => 'object',
                                '_str' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_double' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_scalar' => [
                                    'nodeType' => 'scalar'
                                ]
                            ],
                            'nodeType' => 'object'
                        ],
                        'nodeType' => 'array'
                    ]
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_root' => [
                        '[]' => [
                            '_id' => [
                                'nodeType' => 'scalar'
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ]
                            ],
                            '_obj' => [
                                'nodeType' => 'object',
                                '_str' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_double' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_scalar' => [
                                    'nodeType' => 'scalar'
                                ]
                            ],
                            'nodeType' => 'object'
                        ],
                        'nodeType' => 'array',
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_root' => [
                        '[]' => [
                            '_id' => [
                                'nodeType' => 'scalar'
                            ],
                            '_arr' => [
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ],
                                'nodeType' => 'array'
                            ],
                            '_obj' => [
                                'nodeType' => 'object',
                                '_str' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_double' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_arr2' => [
                                    '[]' => [
                                        'nodeType' => 'object',
                                        '_id' => [
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
                'parent_aliases' => []
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
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ]
                            ],
                            '_obj' => [
                                'nodeType' => 'object',
                                '_str' => [
                                    'nodeType' => 'scalar'
                                ],
                                '_obj2' => [
                                    'nodeType' => 'object',
                                    '_id' => [
                                        'nodeType' => 'scalar'
                                    ]
                                ]
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_id' => [
                                'nodeType' => 'integer',
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'integer'
                                ]
                            ],
                            '_obj' => [
                                'nodeType' => 'object',
                                '_str' => [
                                    'nodeType' => 'string'
                                ],
                                '_double' => [
                                    'nodeType' => 'double'
                                ],
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_id' => [
                                'nodeType' => 'scalar'
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar',
                                ],
                            ],
                            '_arrOfScalars' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ],
                            ],
                            '_arrOfObjects' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_innerId' => [
                                        'nodeType' => 'scalar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_k' => [
                                'nodeType' => 'scalar'
                            ],
                            '_field' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ]
                            ]
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_id' => [
                                'nodeType' => 'scalar',
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar',
                                ],
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_id' => [
                                'nodeType' => 'scalar'
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_val' => [
                                        'nodeType' => 'scalar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_id' => [
                                'nodeType' => 'scalar',
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_val' => [
                                        'nodeType' => 'scalar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_s2null' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_val' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar'
                                ],
                            ],
                            '_obj' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_key' => [
                                        'nodeType' => 'scalar'
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '_null2s' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_val' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar',
                                ],
                            ],
                            '_obj' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_key' => [
                                        'nodeType' => 'scalar'
                                    ]
                                ],
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => []
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
        self::assertEquals(
            [
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'string'
                        ]
                    ]
                ],
                'parent_aliases' => []
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
        self::assertEquals(
            [
                'data' => [
                    '_test' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'scalar'
                        ]
                    ]
                ],
                'parent_aliases' => []
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
                'data' => [
                    '_test' => [
                        '[]' => [
                            'nodeType' => 'object',
                            '_id' => [
                                'nodeType' => 'integer',
                            ],
                            '_arr' => [
                                'nodeType' => 'array',
                                '[]' => [
                                    'nodeType' => 'integer',
                                ],
                            ],
                            '_string' => [
                                'nodeType' => 'string',
                            ],
                            '_float' => [
                                'nodeType' => 'double',
                            ],
                            '_bool' => [
                                'nodeType' => 'boolean',
                            ],
                            '_null' => [
                                'nodeType' => 'null',
                            ],
                        ],
                        'nodeType' => 'array',
                    ],
                ],
                'parent_aliases' => []
            ],
            $analyzer->getStructure()->getData()
        );
    }
}
