<?php

use Keboola\Json\Parser;
use Keboola\CsvTable\Table;
use Keboola\Utils\Utils;

class ParserTest extends \PHPUnit_Framework_TestCase {

	public function testProcess()
	{
		$parser = $this->getParser();

		$testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

		$file = file_get_contents("{$testFilesPath}.json");
		$data = json_decode($file);

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

	public function testRowCount()
	{
		$parser = $this->getParser();

		$testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

		$data = json_decode(file_get_contents("{$testFilesPath}.json"));

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

		$testFilesPath = $this->getDataDir() . 'Json_tweets_pinkbike';

		$file = file_get_contents("{$testFilesPath}.json");
		$data = json_decode($file);

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

		$data = json_decode(file_get_contents($this->getDataDir() . "PrimaryKeyTest/multilevel.json"));

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
		$json = file_get_contents($this->getDataDir() . "Json_zendesk_comments_empty_objects.json");

		$j = json_decode($json);
		$parser = $this->getParser();
		$parser->process($j->data);
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

	protected static function callMethod($obj, $name, array $args)
	{
		$class = new \ReflectionClass($obj);
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method->invokeArgs($obj, $args);
	}

	protected function getParser()
	{
		return new Parser(new \Monolog\Logger('test'));
	}

	protected function getDataDir()
	{
		return __DIR__ . "/../../_data/";
	}
}
