<?php

use Keboola\Json\Parser;
use Keboola\CsvTable\Table;
use Keboola\Utils\Utils;

class ParserTest extends ParserTestCase
{
    public function testZeroValues()
    {
        $json = json_decode('[
            {
                "hashtags": [
                    {
                        "text": "mtb",
                        "indices": [
                            0,
                            4,
                            null
                        ]
                    }
                ],
                "symbols": [

                ]
            }
        ]');
        $parser = $this->getParser();

        $parser->process($json, 'entities');
        // yo dawg
        self::assertEquals(
            "0",
            str_getcsv(
                file(
                    $parser->getCsvFiles()['entities_hashtags_indices']
                )[1] // 2nd row
            )[0] // 1st column
        );
    }

    public function testPrimaryKey()
    {
        $parser = $this->getParser();

        $parser->addPrimaryKeys(['root' => 'id,date']);

        $parser->process([
            (object) [
                'id' => 1,
                'date' => '2015-10-21',
                'data' => ['stuff']
            ]
        ]);

        self::assertEquals('id,date', $parser->getCsvFiles()['root']->getPrimaryKey());
        self::assertEquals(
            '"stuff","root_1;2015-10-21"' . PHP_EOL,
            file($parser->getCsvFiles()['root_data'])[1]
        );
    }

    public function testParentIdPrimaryKey()
    {
        $parser = $this->getParser();

        $data = json_decode('[
            {
                "pk": 1,
                "arr": [1,2,3]
            },
            {
                "pk": 2,
                "arr": ["a","b","c"]
            }
        ]');

        $parser->addPrimaryKeys(['test' => "pk"]);
        $parser->process($data, 'test');
        foreach($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "PrimaryKeyTest/{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    public function testParentIdPrimaryKeyMultiLevel()
    {
        $parser = $this->getParser();

        $data = $this->loadJson('multilevel');

        $parser->addPrimaryKeys([
            'outer' => "pk",
            'outer_inner' => "pkey"
        ]);
        $parser->process($data, 'outer');
        foreach($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "PrimaryKeyTest/{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    public function testParentIdHash()
    {
        $parser = $this->getParser();

        $data = json_decode('[
            {
                "pk": 1,
                "arr": [1,2,3]
            },
            {
                "pk": 2,
                "arr": ["a","b","c"]
            }
        ]');

        $parser->process($data, 'hash');
        foreach($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "PrimaryKeyTest/{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    /**
     * Linkdex keywords usecase
     */
    public function testParentIdHashSameValues()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(1);

        $data = [];
        $data[] = json_decode('{
            "uri": "firstStuff",
            "createdAt": "30/05/13",
            "expectedPage": "",
            "keyphrase": "i fokin bash ur hed in",
            "keyword": "i fokin bash ur hed in",
            "tags": {
                "tag": {
                    "@value": "parking@$$press"
                }
            }
        }');
        $data[] = json_decode('{
            "uri": "secondStuff",
            "createdAt": "30/05/13",
            "expectedPage": "",
            "keyphrase": "il rek u m8",
            "keyword": "il rek u m8",
            "tags": {
                "tag": {
                    "@value": "parking@$$press"
                }
            }
        }');
        $data[] = json_decode('{
            "uri": "thirdStuff",
            "createdAt": "24/05/13",
            "expectedPage": "",
            "keyphrase": "i sware on me mum",
            "keyword": "i sware on me mum",
            "tags": {
                "tag": [
                    {
                        "@value": "I ARE"
                    },
                    {
                        "@value": "POTATO"
                    }
                ]
            }
        }');

        // Shouldn't be any different to parsing just the array..just replicating an use case
        foreach($data as $json) {
            $parser->process([$json], 'nested_hash');
        }

        foreach($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    /**
     * Linkdex keywords usecase
     */
    public function testParentIdHashSameValuesDeepNesting()
    {
        $parser = $this->getParser();

        $data = [
            json_decode('{
                "uri": "firstStuff",
                "createdAt": "30/05/13",
                "expectedPage": "",
                "keyphrase": "i fokin bash ur hed in",
                "keyword": "i fokin bash ur hed in",
                "tags": {
                    "a": { "b": { "tag": [
                        {
                            "@value": "parking@$$press"
                        }
                    ] }}
                }
            }'),
            json_decode('{
                "uri": "secondStuff",
                "createdAt": "30/05/13",
                "expectedPage": "",
                "keyphrase": "il rek u m8",
                "keyword": "il rek u m8",
                "tags": {
                    "a": { "b": { "tag": [
                        {
                            "@value": "parking@$$press"
                        }
                    ]}}
                }
            }'),
            json_decode('{
                "uri": "thirdStuff",
                "createdAt": "24/05/13",
                "expectedPage": "",
                "keyphrase": "i sware on me mum",
                "keyword": "i sware on me mum",
                "tags": {
                    "a": { "b": { "tag": [
                        {
                            "@value": "I ARE"
                        },
                        {
                            "@value": "POTATO"
                        }
                    ]}}
                }
            }')
        ];

        $parser->process($data, 'nested_hash_deep');

        foreach($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    /**
     * Process the same dataset with different parentId
     */
    public function testParentIdHashTimeDiff()
    {
        $this->timeDiffCompare($this->getParser());
    }

    public function testParentIdPrimaryKeyTimeDiff()
    {
        $parser = $this->getParser();
        $parser->addPrimaryKeys([
            'hash' => 'pk, time',
            'later' => 'pk, time'
        ]);
        $this->timeDiffCompare($parser);
    }

    protected function timeDiffCompare($parser)
    {
        $data = json_decode('[
            {
                "pk": 1,
                "arr": [1,2,3]
            },
            {
                "pk": 2,
                "arr": ["a","b","c"]
            }
        ]');

        $parser->process($data, 'hash', ['time' =>time()]);
        sleep(1);
        $parser->process($data, 'later', ['time' =>time()]);
        $files = $parser->getCsvFiles();
        foreach(['hash' => 'later', 'hash_arr' => 'later_arr'] as $file => $later) {
            $old = file($files[$file]->getPathname());
            $new = file($files[$later]->getPathname());
            self::assertNotEquals(
                $old,
                $new
            );

            // ditch headers
            $old = array_slice($old, 1);
            $new = array_slice($new, 1);
            // compare first field (this $data has no more anywhere!)
            foreach($old as $key => $row) {
                $oldRow = str_getcsv($row);
                $newRow = str_getcsv($new[$key]);
                self::assertEquals($oldRow[0], $newRow[0]);
                self::assertNotEquals($oldRow[1], $newRow[1]);
            }
        }
    }

    public function testNoStrictScalarChange()
    {
        $parser = $this->getParser();

        $data = Utils::json_decode('[
            {"field": 128},
            {"field": "string"},
            {"field": true}
        ]');

        $parser->process($data, 'threepack');
        self::assertEquals(
            [
                '"field"' . PHP_EOL,
                '"128"' . PHP_EOL,
                '"string"' . PHP_EOL,
                '"1"' . PHP_EOL // true gets converted to "1"! should be documented!
            ],
            file($parser->getCsvFiles()['threepack']->getPathname())
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "integer" to "string" in 'root.field'
     */
    public function testStrictScalarChange()
    {
        $parser = $this->getParser();
        $parser->getAnalyzer()->setStrict(true);

        $data = json_decode('[
            {"field": 128},
            {"field": "string"},
            {"field": true}
        ]');

        $parser->process($data);
    }

    /**
     * @todo Purpose of this?
     */
    public function testProcessEmptyObjects()
    {
        $json = $this->loadJson('Json_zendesk_comments_empty_objects');
        $parser = $this->getParser();
        $parser->process($json->data);
        $files = $parser->getCsvFiles();

//         foreach($files as $k => $file) {
//             var_dump(file_get_contents($file));
//         }
    }

    public function testArrayParentId()
    {
        $parser = $this->getParser();

        $data = json_decode('[
            {"field": 128},
            {"field": "string"},
            {"field": true}
        ]');

        $parser->process($data, 'test', [
            'first_parent' => 1,
            'second_parent' => "two"]
        );
        self::assertEquals(
            file_get_contents($this->getDataDir() . 'ParentIdsTest.csv'),
            file_get_contents($parser->getCsvFiles()['test'])
        );
    }

    public function testProcessSimpleArray()
    {
        $parser = $this->getParser();
        $parser->process(json_decode('["a","b"]'));
        self::assertEquals(
            [
                '"data"' . PHP_EOL,
                '"a"' . PHP_EOL,
                '"b"' . PHP_EOL,
            ],
            file($parser->getCsvFiles()['root']->getPathname())
        );
    }

    public function testInputDataIntegrity()
    {
        $parser = $this->getParser();

        $inputData = $this->loadJson('Json_tweets_pinkbike');
        $originalData = $this->loadJson('Json_tweets_pinkbike');

        $parser->process($inputData);
        $parser->getCsvFiles();

        self::assertEquals($originalData, $inputData);
        self::assertEquals(serialize($originalData), serialize($inputData), "The object does not match original.");
    }

    /**
     * There's no current use case for this.
     * It should, however, be supported as it is a valid JSON string
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unsupported data row in 'root'!
     */
    public function testNestedArraysDisabledError()
    {
        $parser = $this->getParser();
        $data = json_decode('
            [
                [1,2,3],
                [4,5,6]
            ]
        ');

        $parser->process($data);
    }

    public function testNestedArrays()
    {
        $logHandler = new \Monolog\Handler\TestHandler();
        $parser = Parser::create(new \Monolog\Logger('test', [$logHandler]));
        $parser->getAnalyzer()->setNestedArrayAsJson(true);

        $data = [
            [1,2,3,[7,8]],
            [4,5,6]
        ];

        $parser->process($data);
        self::assertEquals(
            true,
            $logHandler->hasWarning("Unsupported array nesting in 'root'! Converting to JSON string."),
            "Warning should have been logged"
        );
        self::assertEquals(
            file_get_contents($this->getDataDir() . 'NestedArraysJson.csv'),
            file_get_contents($parser->getCsvFiles()['root'])
        );
    }

    public function testHeaderSpecialChars()
    {
        $parser = $this->getParser();
        $data = json_decode('[
            {
                "_id": 123456,
                "KeywordRanking": {
                    "@attributes": {
                        "date": "2015-03-20"
                    },
                    "~~stuff°°": {
                        "I ARE POTAT()": "aaa$@!",
                        "!@#$%^&*kek": { "ser!ou$ly": "now"}
                    }
                }
            }
        ]');

        $parser->process($data);

        self::assertEquals(
            '"id","KeywordRanking_attributes_date","KeywordRanking_stuff_I_ARE_POTAT","KeywordRanking_stuff_kek_ser_ou_ly"' . PHP_EOL .
            '"123456","2015-03-20","aaa$@!","now"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['root'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "scalar" to "arrayOfscalar" in 'root.strArr'
     */
    public function testStringArrayMixFail()
    {
        $parser = $this->getParser();

        $data = [
            (object) [
                "id" => 1,
                "strArr" => "string"
            ],
            (object) [
                "id" => 2,
                "strArr" => ["ar", "ra", "y"]
            ]
        ];

        $parser->process($data);
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "arrayOfscalar" to "scalar" in 'root.strArr'
     */
    public function testStringArrayMixFailOppo()
    {
        $parser = $this->getParser();

        $data = [
            (object) [
                "id" => 1,
                "strArr" => ["ar", "ra", "y"]
            ],
            (object) [
                "id" => 2,
                "strArr" => "string"
            ]
        ];

        $parser->process($data);
    }

    public function testAutoUpgradeToArray()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);

        // Test with object > array > object
        $data = [
            (object) [
                'key' => (object) [
                    'subKey1' => 'val1.1',
                    'subKey2' => 'val1.2'
                ]
            ],
            (object) [
                'key' => [
                    (object) [
                        'subKey1' => 'val2.1.1',
                        'subKey2' => 'val2.1.2'
                    ],
                    (object) [
                        'subKey1' => 'val2.2.1',
                    ]
                ]
            ],
            (object) [
                'key' => (object) [
                    'subKey1' => 'val3.1',
                    'subKey2' => 'val3.2'
                ]
            ]
        ];

        $parser->process($data);

        // TODO guess this could be in files..
        self::assertEquals(
            '"key"' . PHP_EOL .
            '"root_eae48f50d1159c41f633f876d6c66411"' . PHP_EOL .
            '"root_83cb9491934903381f6808ac79842022"' . PHP_EOL .
            '"root_6d231f9592a4e259452229e2be31f42e"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['root'])
        );

        self::assertEquals(
            '"subKey1","subKey2","JSON_parentId"' . PHP_EOL .
            '"val1.1","val1.2","root_eae48f50d1159c41f633f876d6c66411"' . PHP_EOL .
            '"val2.1.1","val2.1.2","root_83cb9491934903381f6808ac79842022"' . PHP_EOL .
            '"val2.2.1","","root_83cb9491934903381f6808ac79842022"' . PHP_EOL .
            '"val3.1","val3.2","root_6d231f9592a4e259452229e2be31f42e"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['root_key'])
        );

        // Test with array first
        $data2 = [
            (object) [
                'key' => [
                    (object) [
                        'subKey1' => 'val2.1.1',
                        'subKey2' => 'val2.1.2'
                    ],
                    (object) [
                        'subKey1' => 'val2.2.1',
                        'subKey2' => 'val2.2.2'
                    ]
                ]
            ],
            (object) [
                'key' => (object) [
                    'subKey1' => 'val3.1',
                    'subKey2' => 'val3.2'
                ]
            ]
        ];

        $parser->process($data2, 'arr');

        self::assertEquals(
            '"key"' . PHP_EOL .
            '"arr_d03523e758a12366bd7062ee727c4939"' . PHP_EOL .
            '"arr_6d231f9592a4e259452229e2be31f42e"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['arr'])
        );

        self::assertEquals(
            '"subKey1","subKey2","JSON_parentId"' . PHP_EOL .
            '"val2.1.1","val2.1.2","arr_d03523e758a12366bd7062ee727c4939"' . PHP_EOL .
            '"val2.2.1","val2.2.2","arr_d03523e758a12366bd7062ee727c4939"' . PHP_EOL .
            '"val3.1","val3.2","arr_6d231f9592a4e259452229e2be31f42e"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['arr_key'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "integer" to "string" in 'root.scalars.data'
     */
    public function testAutoUpgradeToArrayStrict()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);
        $parser->getAnalyzer()->setStrict(true);

        $data = [
            (object) [
                'id' => 1,
                'objects' => [
                    (object) [
                        'data' => 'firstInArr'
                    ],
                    (object) [
                        'data' => 'secondInArr'
                    ]
                ],
                'scalars' => [
                    1,
                    'second'
                ]
            ],
            (object) [
                'id' => 'two',
                'objects' => (object) [
                    'data' => 'singleObject'
                ],
                'scalars' => 2.1
            ]
        ];
        $parser->process($data);

        $parser->getCsvFiles();
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled type change from "arrayOfobject" to "scalar" in 'root.key'
     */
    public function testAutoUpgradeToArrayMismatch()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);

        $data = [
            (object) [
                'key' => [
                    (object) [
                        'subKey1' => 'val2.1.1',
                        'subKey2' => 'val2.1.2'
                    ],
                    (object) [
                        'subKey1' => 'val2.2.1',
                        'subKey2' => 'val2.2.2'
                    ]
                ]
            ],
            (object) [
                'key' => (object) [
                    'subKey1' => 'val3.1',
                    'subKey2' => 'val3.2'
                ]
            ],
            (object) [
                'key' => 'asdf'
            ],
        ];
        $parser->process($data);
    }

    /**
     * Test with string
     */
    public function testAutoUpgradeToArrayString()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);

        // Test with object > array > object
        $data = [
            (object) ['key' => 'str1'],
            (object) ['key' => [
                'str2.1',
                'str2.2'
            ]],
            (object) ['key' => 'str3']
        ];

        $parser->process($data);

        self::assertEquals(
            '"key"' . PHP_EOL .
            '"root_0c616a2609bd2e8d88574f3f856170c5"' . PHP_EOL .
            '"root_3cc17a87c69e64707ac357e84e5a9eb8"' . PHP_EOL .
            '"root_af523454cc66582ad5dcec3f171b35ed"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['root'])
        );

        self::assertEquals(
            '"data","JSON_parentId"' . PHP_EOL .
            '"str1","root_0c616a2609bd2e8d88574f3f856170c5"' . PHP_EOL .
            '"str2.1","root_3cc17a87c69e64707ac357e84e5a9eb8"' . PHP_EOL .
            '"str2.2","root_3cc17a87c69e64707ac357e84e5a9eb8"' . PHP_EOL .
            '"str3","root_af523454cc66582ad5dcec3f171b35ed"' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['root_key'])
        );
    }

    public function testIncompleteData()
    {
        $parser = $this->getParser();

        $parser->getStruct()->load([
            'root' => [
                'id' => 'scalar',
                'value' => 'scalar'
            ]
        ]);

        $parser->process([(object) ['id' => 1]]);

        self::assertEquals(
            '"id","value"' . PHP_EOL .
            '"1",""' . PHP_EOL,
            file_get_contents($parser->getCsvFiles()['root'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\NoDataException
     * @expectedExceptionMessage Empty data set received for root
     */
    public function testEmptyData()
    {
        $parser = $this->getParser();

        $parser->process([]);
    }

    /**
     * @expectedException \Keboola\Json\Exception\NoDataException
     * @expectedExceptionMessage Empty data set received for root
     */
    public function testNullData()
    {
        $parser = $this->getParser();

        $parser->process([null]);
    }

    public function testArrayOfNull()
    {
        $parser = $this->getParser();
        $parser->getStruct()->setAutoUpgradeToArray(true);

        $parser->process(
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

        $parser->process(
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
            '"val","obj"' . PHP_EOL .
            '"s2null_eb89917794221aeda822735efbab9069","s2null_eb89917794221aeda822735efbab9069"' . PHP_EOL .
            '"s2null_77cca534224f13ec1fa45c6c0c98557d","s2null_77cca534224f13ec1fa45c6c0c98557d"' . PHP_EOL .
            '',
            file_get_contents($parser->getCsvFiles()['s2null'])
        );

        self::assertEquals('"data","JSON_parentId"' . PHP_EOL .
            '"stringArr","s2null_eb89917794221aeda822735efbab9069"' . PHP_EOL .
            '"","s2null_77cca534224f13ec1fa45c6c0c98557d"' . PHP_EOL .
            '',
            file_get_contents($parser->getCsvFiles()['s2null_val'])
        );

        self::assertEquals('"key","JSON_parentId"' . PHP_EOL .
            '"objValue","s2null_eb89917794221aeda822735efbab9069"' . PHP_EOL .
            '"","s2null_77cca534224f13ec1fa45c6c0c98557d"' . PHP_EOL .
            '',
            file_get_contents($parser->getCsvFiles()['s2null_obj'])
        );

        self::assertEquals('"val","obj"' . PHP_EOL .
            '"null2s_eb89917794221aeda822735efbab9069","null2s_eb89917794221aeda822735efbab9069"' . PHP_EOL .
            '"null2s_77cca534224f13ec1fa45c6c0c98557d","null2s_77cca534224f13ec1fa45c6c0c98557d"' . PHP_EOL .
            '',
            file_get_contents($parser->getCsvFiles()['null2s'])
        );

        self::assertEquals('"data","JSON_parentId"' . PHP_EOL .
            '"stringArr","null2s_eb89917794221aeda822735efbab9069"' . PHP_EOL .
            '"","null2s_77cca534224f13ec1fa45c6c0c98557d"' . PHP_EOL .
            '',
            file_get_contents($parser->getCsvFiles()['null2s_val'])
        );

        self::assertEquals('"key","JSON_parentId"' . PHP_EOL .
            '"objValue","null2s_eb89917794221aeda822735efbab9069"' . PHP_EOL .
            '"","null2s_77cca534224f13ec1fa45c6c0c98557d"' . PHP_EOL .
            '',
            file_get_contents($parser->getCsvFiles()['null2s_obj'])
        );
    }
}
