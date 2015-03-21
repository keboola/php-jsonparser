<?php

use Keboola\Json\Parser;
use Keboola\CsvTable\Table;
use Keboola\Utils\Utils;

class ParserTest extends \PHPUnit_Framework_TestCase {

	public function testProcess()
	{
		$parser = $this->getParser();

		$testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

		$data = $this->loadJson('Json_tweets_pinkbike');

		$parser->process($data);

		foreach($parser->getCsvFiles() as $name => $table) {
			// compare result files
			$this->assertEquals(
				file_get_contents("{$testFilesPath}/{$name}.csv"),
				file_get_contents($table->getPathname())
			);

			// compare column counts
			$parsedFile = file($table->getPathname());
			foreach($parsedFile as $row) {
				if (empty($headerCount)) {
					$headerCount = count($row);
				} else {
					$this->assertEquals($headerCount, count($row));
				}
			}
		}

		// make sure all the files are present
		$dir = scandir($testFilesPath);
		array_walk($dir, function (&$val) {
				$val = str_replace(".csv", "", $val);
			}
		);
		$this->assertEquals(array(".",".."), array_diff($dir, array_keys($parser->getCsvFiles())));
		$this->assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
	}

	public function testTypeCharacters()
	{
		$parser = $this->getParser();

		$testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

		$data = $this->loadJson('Json_tweets_pinkbike');

		$parser->process($data, 'a/b.c&d@e$f');

		$this->assertEquals(
			[
				'a_b_c_d_e_f',
				'a_b_c_d_e_f_statuses',
				'a_b_c_d_e_f_statuses_user_entities_url_urls',
				'a_b_c_d_e_f_statuses_user_entities_url_urls_indices',
				'a_b_c_d_e_f_statuses_entities_hashtags',
				'a_b_c_d_e_f_statuses_entities_hashtags_indices',
				'a_b_c_d_e_f_statuses_entities_urls',
				'a_b_c_d_e_f_statuses_entities_urls_indices',
				'a_b_c_d_e_f_statuses_entities_user_mentions',
				'a_b_c_d_e_f_statuses_entities_user_mentions_indices',
				'a_b_c_d_e_f_statuses_retweeted_status_user_entities_url_urls',
				'abcdefsrueuui__status_user_entities_url_urls_indices',
				'a_b_c_d_e_f_statuses_retweeted_status_entities_urls',
				'a_b_c_d_e_f_statuses_retweeted_status_entities_urls_indices',
				'a_b_c_d_e_f_statuses_retweeted_status_entities_user_mentions',
				'abcdefsreui__status_entities_user_mentions_indices',
				'a_b_c_d_e_f_statuses_user_entities_description_urls',
				'a_b_c_d_e_f_statuses_user_entities_description_urls_indices',
				'a_b_c_d_e_f_statuses_entities_media',
				'a_b_c_d_e_f_statuses_entities_media_indices',
				'a_b_c_d_e_f_statuses_retweeted_status_entities_media',
				'a_b_c_d_e_f_statuses_retweeted_status_entities_media_indices',
			],
			array_keys($parser->getCsvFiles())
		);
	}

	public function testRowCount()
	{
		$parser = $this->getParser();

		$data = $this->loadJson('Json_tweets_pinkbike');

		$parser->process($data);

		// -1 offset to compensate for header
		$rows = -1;
		$handle = fopen($parser->getCsvFiles()['root_statuses'], 'r');
		while($row = fgetcsv($handle)) {
			$rows++;
		}
		$this->assertEquals(count($data[0]->statuses), $rows);
	}

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
		$this->assertEquals("0", str_getcsv(file($parser->getCsvFiles()['entities_hashtags_indices']->getPathname())[1])[0]);
	}

	public function testValidateHeader()
	{
		$parser = $this->getParser();

		$header = array(
			"KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Click-through Conversions",
			"KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: View-through Conversions",
			"KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Total Conversions",
			"KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Click-through Revenue",
			"KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: View-through Revenue",
			"KIND_Baseline SEM_Conversions : KIND_Baseline SEM_Conversions: Total Revenue",
			"KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Click-through Conversions",
			"KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: View-through Conversions",
			"KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Total Conversions",
			"KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Click-through Revenue",
			"KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: View-through Revenue",
			"KIND_Strong_Pledges : KIND_Strong_Conversions_Pledges: Total Revenue",
			"KIND_Projects Retargeting : KINDProjects_Retargeting: Click-through Conversions",
			"KIND_Projects Retargeting : KINDProjects_Retargeting: View-through Conversions",
			"KIND_Projects Retargeting : KINDProjects_Retargeting: Total Conversions",
			"KIND_Projects Retargeting : KINDProjects_Retargeting: Click-through Revenue",
			"KIND_Projects Retargeting : KINDProjects_Retargeting: View-through Revenue",
			"KIND_Projects Retargeting : KIND_Projects_Retargeting: Total Revenue",
			"KIND_Conversions : KIND_Projects_Conversions_Votes: Click-through Conversions",
			"KIND_Conversions : KIND_Projects_Conversions_Votes: View-through Conversions",
			"KIND_Conversions : KIND_Projects_Conversions_Votes: Total Conversions",
			"KIND_Conversions : KIND_Projects_Conversions_Votes: Click-through Revenue",
			"KIND_Conversions : KIND_Projects_Conversions_Votes: View-through Revenue",
			"KIND_Conversions : KIND_Projects_Conversions_Votes: Total Revenue",
			"KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Click-through Conversions",
			"KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: View-through Conversions",
			"KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Total Conversions",
			"KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Click-through Revenue",
			"KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: View-through Revenue",
			"KIND_Conversions_Submissions : KIND_Projects_Conversions_Submissions: Total Revenue");

		$validHeader = self::callMethod($parser, 'validateHeader', array($header));

		$expectedHeader = array(
			"KSKSCtC__SEM_Conversions__Click-through_Conversions",
			"KSKSVtC__KIND_Baseline_SEM_Conversions__View-through_Conversions",
			"KSKSTC____KIND_Baseline_SEM_Conversions__Total_Conversions",
			"KSKSCtR____KIND_Baseline_SEM_Conversions__Click-through_Revenue",
			"KSKSVtR____KIND_Baseline_SEM_Conversions__View-through_Revenue",
			"KSKSTR____KIND_Baseline_SEM_Conversions__Total_Revenue",
			"KKCtC__Click-through_Conversions",
			"KKVtC__KIND_Strong_Conversions_Pledges__View-through_Conversions",
			"KKTC____KIND_Strong_Conversions_Pledges__Total_Conversions",
			"KKCtR____KIND_Strong_Conversions_Pledges__Click-through_Revenue",
			"KKVtR____KIND_Strong_Conversions_Pledges__View-through_Revenue",
			"KKTR____KIND_Strong_Conversions_Pledges__Total_Revenue",
			"KRKCtC____KINDProjects_Retargeting__Click-through_Conversions",
			"KRKVtC____KINDProjects_Retargeting__View-through_Conversions",
			"KRKTC__Retargeting___KINDProjects_Retargeting__Total_Conversions",
			"KRKCtR____KINDProjects_Retargeting__Click-through_Revenue",
			"KRKVtR____KINDProjects_Retargeting__View-through_Revenue",
			"KRKTR__Retargeting___KIND_Projects_Retargeting__Total_Revenue",
			"2282172e8d22d91520151a6df2413dd6",
			"KKVtC__KIND_Projects_Conversions_Votes__View-through_Conversions",
			"KKTC____KIND_Projects_Conversions_Votes__Total_Conversions",
			"KKCtR____KIND_Projects_Conversions_Votes__Click-through_Revenue",
			"KKVtR____KIND_Projects_Conversions_Votes__View-through_Revenue",
			"KKTR____KIND_Projects_Conversions_Votes__Total_Revenue",
			"08dcf2d087429e430b5b060f138472c6",
			"KKVtC__View-through_Conversions",
			"KKTC____KIND_Projects_Conversions_Submissions__Total_Conversions",
			"KKCtR__Click-through_Revenue",
			"KKVtR__View-through_Revenue",
			"KKTR____KIND_Projects_Conversions_Submissions__Total_Revenue"
		);

		$this->assertEquals($expectedHeader, $validHeader);
	}

	public function testPrimaryKeys()
	{
		$parser = $this->getParser();

		$data = $this->loadJson('Json_tweets_pinkbike');

		$pks = [
			'root_statuses' => 'id',
			'root_statuses_entities_urls' => 'url,JSON_parentId'
		];
		$parser->process($data);
		$parser->addPrimaryKeys($pks);

		$files = $parser->getCsvFiles();
		foreach($pks as $table => $pk) {
			$this->assertEquals($pk, $files[$table]->getPrimaryKey());
		}
		$this->assertEquals(null, $files['root']->getPrimaryKey());
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
			$this->assertEquals(
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
			$this->assertEquals(
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
			$this->assertEquals(
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
		$parser->setAutoUpgradeToArray(1);

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
			$this->assertEquals(
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
			$this->assertNotEquals(
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
				$this->assertEquals($oldRow[0], $newRow[0]);
				$this->assertNotEquals($oldRow[1], $newRow[1]);
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
		$this->assertEquals(
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
		$parser->setStrict(true);

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
		$parser = $this->getParser();
		$parser->process($json->data);
		$parser->getCsvFiles();
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
		$this->assertEquals(
			file_get_contents($this->getDataDir() . 'ParentIdsTest.csv'),
			file_get_contents($parser->getCsvFiles()['test'])
		);
	}

	public function testProcessSimpleArray()
	{
		$parser = $this->getParser();
		$parser->process(json_decode('["a","b"]'));
		$this->assertEquals(
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

		$this->assertEquals($originalData, $inputData);
		$this->assertEquals(serialize($originalData), serialize($inputData), "The object does not match original.");
	}

	/**
	 * There's no current use case for this.
	 * It should, however, be supported as it is a valid JSON string
	 * @expectedException \Keboola\Json\Exception\JsonParserException
	 * @expectedExceptionMessage Unsupported data row in 'root'!
	 */
	public function testNestedArraysError()
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
		$parser = new Parser(new \Monolog\Logger('test', [$logHandler]));
		$parser->setNestedArrayAsJson(true);
		$data = json_decode('
			[
				[1,2,3,[7,8]],
				[4,5,6]
			]
		');

		$parser->process($data);
		$this->assertEquals(
			true,
			$logHandler->hasWarning("Unsupported array nesting in 'root'! Converting to JSON string."),
			"Warning should have been logged"
		);
		$this->assertEquals(
			file_get_contents($this->getDataDir() . 'NestedArraysJson.csv'),
			file_get_contents($parser->getCsvFiles()['root'])
		);
	}

	/**
	 * There's no current use case for this.
	 * It should, however, be supported as it is a valid JSON string
	 * @expectedException \Keboola\Json\Exception\JsonParserException
	 * @expectedExceptionMessage Unhandled type change from "string" to "array" in 'root.strArr'
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
	 * There's no current use case for this.
	 * It should, however, be supported as it is a valid JSON string
	 * @expectedException \Keboola\Json\Exception\JsonParserException
	 * @expectedExceptionMessage Unhandled type change from "array" to "string" in 'root.strArr'
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

	public function testStringArrayMix()
	{
		// Not using $this->getParser() to preserve $logHandler accessibility
		$logHandler = new \Monolog\Handler\TestHandler();
		$parser = new Parser(new \Monolog\Logger('test', [$logHandler]));
		$parser->setAllowArrayStringMix(true);

		$data = [
			(object) [
				"id" => 1,
				"strArr" => "string"
			],
			(object) [
				"id" => 2,
				"strArr" => ["ar", "ra", "y"]
			],
			(object) [
				"id" => 3,
				"strArr" => 65536
			]
		];

		$parser->process($data);

		$this->assertEquals(
			'"id","strArr"' . PHP_EOL .
			'"1","string"' . PHP_EOL .
			'"2","root_d7135b2b8e2015e3cd4be6d071f880b0"' . PHP_EOL .
			'"3","65536"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['root'])
		);
		$this->assertEquals(
			'"data","JSON_parentId"' . PHP_EOL .
			'"ar","root_d7135b2b8e2015e3cd4be6d071f880b0"' . PHP_EOL .
			'"ra","root_d7135b2b8e2015e3cd4be6d071f880b0"' . PHP_EOL .
			'"y","root_d7135b2b8e2015e3cd4be6d071f880b0"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['root_strArr'])
		);
		$this->assertEquals(
			true,
			$logHandler->hasWarningRecords("An array was encountered where scalar 'string' was expected!")
		);
	}

	public function testUnderscoreHeader()
	{
		$parser = $this->getParser();
		$data = (object) [
			'ts' => 1423961676,
			'resends' => NULL,
			'_id' => '123456',
			'sender' => 'ka@rel.cz',
		];

		$parser->process([$data]);

		$this->assertEquals(
			'"ts","resends","id","sender"' . PHP_EOL .
			'"1423961676","","123456","ka@rel.cz"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['root'])
		);
	}

	public function testAutoUpgradeToArray()
	{
		$parser = $this->getParser();
		$parser->setAutoUpgradeToArray(true);

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

		$parser->process($data);

		// TODO guess this could be in files..
		$this->assertEquals(
			'"key"' . PHP_EOL .
			'"root_a83365954a5bf8892d0596229b25f7a2"' . PHP_EOL .
			'"root_572b95cbb90e943052169c890b067f4d"' . PHP_EOL .
			'"root_305c2ce4f6faf5fa01fdad118ea1cfe9"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['root'])
		);

		$this->assertEquals(
			'"subKey1","subKey2","JSON_parentId"' . PHP_EOL .
			'"val1.1","val1.2","root_a83365954a5bf8892d0596229b25f7a2"' . PHP_EOL .
			'"val2.1.1","val2.1.2","root_572b95cbb90e943052169c890b067f4d"' . PHP_EOL .
			'"val2.2.1","val2.2.2","root_572b95cbb90e943052169c890b067f4d"' . PHP_EOL .
			'"val3.1","val3.2","root_305c2ce4f6faf5fa01fdad118ea1cfe9"' . PHP_EOL,
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

		$this->assertEquals(
			'"key"' . PHP_EOL .
			'"arr_572b95cbb90e943052169c890b067f4d"' . PHP_EOL .
			'"arr_305c2ce4f6faf5fa01fdad118ea1cfe9"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['arr'])
		);

		$this->assertEquals(
			'"subKey1","subKey2","JSON_parentId"' . PHP_EOL .
			'"val2.1.1","val2.1.2","arr_572b95cbb90e943052169c890b067f4d"' . PHP_EOL .
			'"val2.2.1","val2.2.2","arr_572b95cbb90e943052169c890b067f4d"' . PHP_EOL .
			'"val3.1","val3.2","arr_305c2ce4f6faf5fa01fdad118ea1cfe9"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['arr_key'])
		);



		// TODO do $this->testProcess() with setAutoUpgradeToArray(true) (nothing should change there or in any other test, apart from testStringArrayMix)
	}

	/**
	 * @expectedException \Keboola\Json\Exception\JsonParserException
	 * @expectedExceptionMessage Unhandled type change from "autoArrayOfobject" to "string" in 'root.key'
	 */
	public function testAutoUpgradeToArrayMismatch()
	{
		$parser = $this->getParser();
		$parser->setAutoUpgradeToArray(true);

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
		$parser->setAutoUpgradeToArray(true);

		// Test with object > array > object
		$data = [
			(object) [
				'key' => 'str1'
			],
			(object) [
				'key' => [
					'str2.1',
					'str2.2'
				]
			],
			(object) [
				'key' => 'str3'
			]
		];

		$parser->process($data);

		$this->assertEquals(
			'"key"' . PHP_EOL .
			'"root_bbce8a190b45b40da599ac5c4996d18e"' . PHP_EOL .
			'"root_c082d1464d60ad223155f227eae399bd"' . PHP_EOL .
			'"root_a7be7f6f64e079d830a8cfed00d13d7c"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['root'])
		);

		$this->assertEquals(
			'"data","JSON_parentId"' . PHP_EOL .
			'"str1","root_bbce8a190b45b40da599ac5c4996d18e"' . PHP_EOL .
			'"str2.1","root_c082d1464d60ad223155f227eae399bd"' . PHP_EOL .
			'"str2.2","root_c082d1464d60ad223155f227eae399bd"' . PHP_EOL .
			'"str3","root_a7be7f6f64e079d830a8cfed00d13d7c"' . PHP_EOL,
			file_get_contents($parser->getCsvFiles()['root_key'])
		);
	}

	/**
	 * Ensure "proper" JSON that doesn't require the upgrade is parsed the same as before
	 */
	public function testProcessWithAutoUpgradeToArray()
	{
		$parser = $this->getParser();
		$parser->setAutoUpgradeToArray(true);

		$testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

		$data = $this->loadJson('Json_tweets_pinkbike');

		$parser->process($data);

		foreach($parser->getCsvFiles() as $name => $table) {
			// compare result files
			$this->assertEquals(
				file_get_contents("{$testFilesPath}/{$name}.csv"),
				file_get_contents($table->getPathname())
			);

			// compare column counts
			$parsedFile = file($table->getPathname());
			foreach($parsedFile as $row) {
				if (empty($headerCount)) {
					$headerCount = count($row);
				} else {
					$this->assertEquals($headerCount, count($row));
				}
			}
		}

		// make sure all the files are present
		$dir = scandir($testFilesPath);
		array_walk($dir, function (&$val) {
				$val = str_replace(".csv", "", $val);
			}
		);
		$this->assertEquals(array(".",".."), array_diff($dir, array_keys($parser->getCsvFiles())));
		$this->assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
	}

	// TODO test whether rows with identical array within receive unique IDs (also with autoArrayOfobject)

	/**
	 * Call a non-public method
	 * @param mixed $obj
	 * @param string $name
	 * @param array $args
	 * @return mixed the class' return value
	 */
	protected static function callMethod($obj, $name, array $args)
	{
		$class = new \ReflectionClass($obj);
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method->invokeArgs($obj, $args);
	}

	protected function loadJson($fileName)
	{
		$testFilesPath = $this->getDataDir() . $fileName . ".json";
		$file = file_get_contents($testFilesPath);
		return Utils::json_decode($file);
	}

	protected function getParser()
	{
		return new Parser(new \Monolog\Logger('test', [new \Monolog\Handler\TestHandler()]));
	}

	protected function getDataDir()
	{
		return __DIR__ . "/../../_data/";
	}
}
