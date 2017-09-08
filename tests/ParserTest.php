<?php

namespace Keboola\Json\Tests;

use Keboola\Json\Analyzer;
use Keboola\Json\Parser;
use Keboola\Json\Structure;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\NullLogger;

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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $parser->process($json, 'entities');
        self:self::assertEquals(
            "\"data\",\"JSON_parentId\"\n" .
            "\"0\",\"entities.hashtags_7166de1f0241156ee048591b4492bc56\"\n" .
            "\"4\",\"entities.hashtags_7166de1f0241156ee048591b4492bc56\"\n" .
            "\"\",\"entities.hashtags_7166de1f0241156ee048591b4492bc56\"\n",
            file_get_contents($parser->getCsvFiles()['entities_hashtags_indices']->getPathname())
        );
    }

    public function testPrimaryKey()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
            '"stuff","root_1;2015-10-21"' . "\n",
            file($parser->getCsvFiles()['root_data'])[1]
        );
    }

    public function testParentIdPrimaryKey()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        foreach ($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "PrimaryKeyTest/{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    public function testParentIdPrimaryKeyMultiLevel()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $data = $this->loadJson('multilevel');
        $parser->addPrimaryKeys([
            'outer' => "pk",
            'outer_inner' => "pkey"
        ]);
        $parser->process($data, 'outer');
        foreach ($parser->getCsvFiles() as $type => $file) {
            self::assertEquals(
                file_get_contents($this->getDataDir() . "PrimaryKeyTest/{$type}.csv"),
                file_get_contents($file->getPathname())
            );
        }
    }

    public function testParentIdHash()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        foreach ($parser->getCsvFiles() as $type => $file) {
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        foreach ($data as $json) {
            $parser->process([$json], 'nested_hash');
        }

        foreach ($parser->getCsvFiles() as $type => $file) {
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        foreach ($parser->getCsvFiles() as $type => $file) {
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
        $this->timeDiffCompare($parser = new Parser(new Analyzer(new NullLogger(), new Structure())));
    }

    public function testParentIdPrimaryKeyTimeDiff()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $parser->addPrimaryKeys([
            'hash' => 'pk, time',
            'later' => 'pk, time'
        ]);
        $this->timeDiffCompare($parser);
    }

    protected function timeDiffCompare(Parser $parser)
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
        foreach (['hash' => 'later', 'hash_arr' => 'later_arr'] as $file => $later) {
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
            foreach ($old as $key => $row) {
                $oldRow = str_getcsv($row);
                $newRow = str_getcsv($new[$key]);
                self::assertEquals($oldRow[0], $newRow[0]);
                self::assertNotEquals($oldRow[1], $newRow[1]);
            }
        }
    }

    public function testNoStrictScalarChange()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $data = \Keboola\Utils\jsonDecode('[
            {"field": 128},
            {"field": "string"},
            {"field": true}
        ]');

        $parser->process($data, 'threepack');
        self::assertEquals(
            [
                '"field"' . "\n",
                '"128"' . "\n",
                '"string"' . "\n",
                '"1"' . "\n" // true gets converted to "1"! should be documented!
            ],
            file($parser->getCsvFiles()['threepack']->getPathname())
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "integer" to "string" in "root.[].field"
     */
    public function testStrictScalarChange()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(false), false, true));
        $data = json_decode('[
            {"field": 128},
            {"field": "string"},
            {"field": true}
        ]');

        $parser->process($data);
    }

    public function testProcessEmptyObjects()
    {
        $json = $this->loadJson('Json_zendesk_comments_empty_objects');
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $parser->process($json->data);
        self::assertEquals(['root'], array_keys($parser->getCsvFiles()));
        self::assertEquals(
            "\"id\",\"type\",\"author_id\",\"body\",\"html_body\",\"public\",\"attachments\",\"via_channel\","
            . "\"via_source_rel\",\"created_at\"\n\"16565200977\",\"Comment\",\"457400607\",\"This is the "
            . "first comment. Feel free to delete this sample ticket.\",\"<p>This is the first comment. Feel free "
            . "to delete this sample ticket.</p>\",\"1\",\"\",\"web\",\"\",\"2013-09-01T20:22:29Z\"\n\"16565201277\","
            . "\"Comment\",\"457400607\",\"This is a private comment (visible to agents only) that you added. You "
            . "also changed the ticket priority to High. You can view a ticket's complete history by selecting the "
            . "Events link in the ticket.\",\"<p>This is a private comment (visible to agents only) that you added. "
            . "You also changed the ticket priority to High. You can view a ticket&#39;s complete history by "
            . "selecting the Events link in the ticket.</p>\",\"\",\"\",\"web\",\"\",\"2013-09-01T20:22:29Z\"\n"
            . "\"16565201397\",\"Comment\",\"457400607\",\"This is the latest comment for this ticket. You also "
            . "changed the ticket status to Pending.\",\"<p>This is the latest comment for this ticket. You also "
            . "changed the ticket status to Pending.</p>\",\"1\",\"\",\"web\",\"\",\"2013-09-01T20:22:29Z\"\n",
            file_get_contents($parser->getCsvFiles()['root']->getPathname())
        );
    }

    public function testArrayParentId()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $data = json_decode('[
            {"field": 128},
            {"field": "string"},
            {"field": true}
        ]');

        $parser->process(
            $data,
            'test',
            [
                'first_parent' => 1,
                'second_parent' => "two"
            ]
        );
        self::assertEquals(
            file_get_contents($this->getDataDir() . 'ParentIdsTest.csv'),
            file_get_contents($parser->getCsvFiles()['test'])
        );
    }

    public function testProcessSimpleArray()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $parser->process(json_decode('["a","b"]'));
        self::assertEquals(
            [
                '"data"' . "\n",
                '"a"' . "\n",
                '"b"' . "\n",
            ],
            file($parser->getCsvFiles()['root']->getPathname())
        );
    }

    public function testInputDataIntegrity()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
     * @expectedExceptionMessage Unsupported data in 'root.[]'.
     */
    public function testNestedArraysDisabledError()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
        $logHandler = new TestHandler();
        $parser = new Parser(new Analyzer(new Logger('test', [$logHandler]), new Structure(), true));
        $data = [
            [1,2,3,[7,8]],
            [4,5,6]
        ];

        $parser->process($data);
        self::assertEquals(
            true,
            $logHandler->hasWarning("Converting nested array 'root.[]' to JSON string."),
            "Warning should have been logged"
        );
        self::assertEquals(
            file_get_contents($this->getDataDir() . 'NestedArraysJson.csv'),
            file_get_contents($parser->getCsvFiles()['root'])
        );
    }

    public function testHeaderSpecialChars()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
            '"id","KeywordRanking_attributes_date","KeywordRanking_stuff_I_ARE_POTAT"' .
            ',"KeywordRanking_stuff_kek_ser_ou_ly"' . "\n" .
            '"123456","2015-03-20","aaa$@!","now"' . "\n",
            file_get_contents($parser->getCsvFiles()['root'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "scalar" to "array" in "root.[].strArr"
     */
    public function testStringArrayMixFail()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(false)));
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
     * @expectedExceptionMessage Unhandled nodeType change from "array" to "scalar" in "root.[].strArr"
     */
    public function testStringArrayMixFailOppo()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(false)));
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

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
        self::assertEquals(
            '"key"' . "\n" .
            '"root_eae48f50d1159c41f633f876d6c66411"' . "\n" .
            '"root_83cb9491934903381f6808ac79842022"' . "\n" .
            '"root_6d231f9592a4e259452229e2be31f42e"' . "\n",
            file_get_contents($parser->getCsvFiles()['root'])
        );

        self::assertEquals(
            '"subKey1","subKey2","JSON_parentId"' . "\n" .
            '"val1.1","val1.2","root_eae48f50d1159c41f633f876d6c66411"' . "\n" .
            '"val2.1.1","val2.1.2","root_83cb9491934903381f6808ac79842022"' . "\n" .
            '"val2.2.1","","root_83cb9491934903381f6808ac79842022"' . "\n" .
            '"val3.1","val3.2","root_6d231f9592a4e259452229e2be31f42e"' . "\n",
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
            '"key"' . "\n" .
            '"arr_d03523e758a12366bd7062ee727c4939"' . "\n" .
            '"arr_6d231f9592a4e259452229e2be31f42e"' . "\n",
            file_get_contents($parser->getCsvFiles()['arr'])
        );

        self::assertEquals(
            '"subKey1","subKey2","JSON_parentId"' . "\n" .
            '"val2.1.1","val2.1.2","arr_d03523e758a12366bd7062ee727c4939"' . "\n" .
            '"val2.2.1","val2.2.2","arr_d03523e758a12366bd7062ee727c4939"' . "\n" .
            '"val3.1","val3.2","arr_6d231f9592a4e259452229e2be31f42e"' . "\n",
            file_get_contents($parser->getCsvFiles()['arr_key'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Unhandled nodeType change from "integer" to "string" in "root.[].scalars.[]"
     */
    public function testAutoUpgradeToArrayStrict()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(false), false, true));
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
     * @expectedExceptionMessage Unhandled nodeType change from "array" to "object" in "root.[].key"
     */
    public function testAutoUpgradeToArrayMismatch()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(false)));
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
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));

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
            '"key"' . "\n" .
            '"root_0c616a2609bd2e8d88574f3f856170c5"' . "\n" .
            '"root_3cc17a87c69e64707ac357e84e5a9eb8"' . "\n" .
            '"root_af523454cc66582ad5dcec3f171b35ed"' . "\n",
            file_get_contents($parser->getCsvFiles()['root'])
        );

        self::assertEquals(
            '"data","JSON_parentId"' . "\n" .
            '"str1","root_0c616a2609bd2e8d88574f3f856170c5"' . "\n" .
            '"str2.1","root_3cc17a87c69e64707ac357e84e5a9eb8"' . "\n" .
            '"str2.2","root_3cc17a87c69e64707ac357e84e5a9eb8"' . "\n" .
            '"str3","root_af523454cc66582ad5dcec3f171b35ed"' . "\n",
            file_get_contents($parser->getCsvFiles()['root_key'])
        );
    }

    public function testIncompleteData()
    {
        $definitions = [
            'data' => [
                '_root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        '_id' => [
                            'nodeType' => 'scalar',
                        ],
                        '_value' => [
                            'nodeType' => 'scalar',
                        ],
                    ],
                ],
            ],
        ];
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()), $definitions);
        $parser->process([(object) ['id' => 1]]);

        self::assertEquals(
            '"id","value"' . "\n" . '"1",""' . "\n",
            file_get_contents($parser->getCsvFiles()['root'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\NoDataException
     * @expectedExceptionMessage Empty data set received for 'root'
     */
    public function testEmptyData()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $parser->process([]);
    }

    /**
     * @expectedException \Keboola\Json\Exception\NoDataException
     * @expectedExceptionMessage Empty data set received for 'root'
     */
    public function testNullData()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $parser->process([null]);
    }

    public function testArrayOfNull()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
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
            '"val","obj"' . "\n" .
            '"s2null_eb89917794221aeda822735efbab9069","s2null_eb89917794221aeda822735efbab9069"' . "\n" .
            '"s2null_77cca534224f13ec1fa45c6c0c98557d","s2null_77cca534224f13ec1fa45c6c0c98557d"' . "\n" .
            '',
            file_get_contents($parser->getCsvFiles()['s2null'])
        );

        self::assertEquals(
            '"data","JSON_parentId"' . "\n" .
            '"stringArr","s2null_eb89917794221aeda822735efbab9069"' . "\n" .
            '"","s2null_77cca534224f13ec1fa45c6c0c98557d"' . "\n" .
            '',
            file_get_contents($parser->getCsvFiles()['s2null_val'])
        );

        self::assertEquals(
            '"key","JSON_parentId"' . "\n" .
            '"objValue","s2null_eb89917794221aeda822735efbab9069"' . "\n" .
            '"","s2null_77cca534224f13ec1fa45c6c0c98557d"' . "\n" .
            '',
            file_get_contents($parser->getCsvFiles()['s2null_obj'])
        );

        self::assertEquals(
            '"val","obj"' . "\n" .
            '"null2s_eb89917794221aeda822735efbab9069","null2s_eb89917794221aeda822735efbab9069"' . "\n" .
            '"null2s_77cca534224f13ec1fa45c6c0c98557d","null2s_77cca534224f13ec1fa45c6c0c98557d"' . "\n".
            '',
            file_get_contents($parser->getCsvFiles()['null2s'])
        );

        self::assertEquals(
            '"data","JSON_parentId"' . "\n" .
            '"stringArr","null2s_eb89917794221aeda822735efbab9069"' . "\n" .
            '"","null2s_77cca534224f13ec1fa45c6c0c98557d"' . "\n" .
            '',
            file_get_contents($parser->getCsvFiles()['null2s_val'])
        );

        self::assertEquals(
            '"key","JSON_parentId"' . "\n" .
            '"objValue","null2s_eb89917794221aeda822735efbab9069"' . "\n" .
            '"","null2s_77cca534224f13ec1fa45c6c0c98557d"' . "\n" .
            '',
            file_get_contents($parser->getCsvFiles()['null2s_obj'])
        );
    }

    public function testParseNumericKeys()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(), true));
        $testFile = \Keboola\Utils\jsonDecode(
            '{"data": [{"1": "one", "2": "two"}]}'
        );
        $parser->process($testFile->data, 'someType');
        self::assertEquals(['someType'], array_keys($parser->getCsvFiles()));
        self::assertEquals(
            "\"1\",\"2\"\n\"one\",\"two\"\n",
            file_get_contents($parser->getCsvFiles()['someType'])
        );
    }

    public function testParseNestedArrayEnabled()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(), true));
        $testFile = \Keboola\Utils\jsonDecode(
            '{"a": [["c", "d"], ["e", "f"]]}'
        );
        $parser->process([$testFile], 'someType');
        self::assertEquals(['someType', 'someType_a'], array_keys($parser->getCsvFiles()));
        self::assertEquals(
            "\"a\"\n\"someType_0a3f2bc488aa446db98866f181f43dbb\"\n",
            file_get_contents($parser->getCsvFiles()['someType'])
        );
        self::assertEquals(
            "\"data\",\"JSON_parentId\"\n" .
            "\"[\"\"c\"\",\"\"d\"\"]\",\"someType_0a3f2bc488aa446db98866f181f43dbb\"\n" .
            "\"[\"\"e\"\",\"\"f\"\"]\",\"someType_0a3f2bc488aa446db98866f181f43dbb\"\n",
            file_get_contents($parser->getCsvFiles()['someType_a'])
        );
    }

    public function testParseNullInconsistency()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(), true));
        $testFile = \Keboola\Utils\jsonDecode(
            '{"data": [null, "a"]}'
        );
        $parser->process($testFile->data, 'someType');
        self::assertEquals(['someType'], array_keys($parser->getCsvFiles()));
        self::assertEquals(
            "\"data\"\n\"\"\n\"a\"\n",
            file_get_contents($parser->getCsvFiles()['someType'])
        );
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Error assigning parentId to a CSV file! $parentId array cannot be multidimensional
     */
    public function testParseInvalidParentId()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure()));
        $testFile = \Keboola\Utils\jsonDecode(
            '{"data": ["a", "b"]}'
        );

        $parser->process($testFile->data, 'someType', ['someColumn' => ['this' => 'is wrong']]);
        self::assertEquals(['someType'], array_keys($parser->getCsvFiles()));
    }

    public function testParseInvalidPrimaryKey()
    {
        $parser = new Parser(new Analyzer(new NullLogger(), new Structure(), true));
        $testFile = \Keboola\Utils\jsonDecode(
            '{"data": [{"id": "a", "val": ["a"]}, {"id": "b", "val": ["b"]}]}'
        );
        $parser->addPrimaryKeys(["someType_val" => "id"]);
        $parser->process($testFile->data, 'someType');
        self::assertEquals(['someType', 'someType_val'], array_keys($parser->getCsvFiles()));
        self::assertEquals(
            "\"id\",\"val\"\n\"a\",\"someType_ee9689ff88c83c395a3ffd9a0e747920\"\n".
            "\"b\",\"someType_37fb9eda31010642e996aa72bc998558\"\n",
            file_get_contents($parser->getCsvFiles()['someType'])
        );
        self::assertEquals(
            "\"data\",\"JSON_parentId\"\n\"a\",\"someType_ee9689ff88c83c395a3ffd9a0e747920\"\n" .
            "\"b\",\"someType_37fb9eda31010642e996aa72bc998558\"\n",
            file_get_contents($parser->getCsvFiles()['someType_val'])
        );
    }
}
