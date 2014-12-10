<?php

use Keboola\Json\Parser;
use Keboola\CsvTable\Table;
use Keboola\Utils\Utils;

class ParserTest extends \PHPUnit_Framework_TestCase {

	public function testProcess() {
		$parser = $this->getParser();

		$testFilesPath = '/../../_data/Json_tweets_pinkbike';

		$file = file_get_contents(__DIR__ . "{$testFilesPath}.json");
		$data = json_decode($file);

		$parser->process($data);

		foreach($parser->getCsvFiles() as $name => $table) {
			// using uniqid() for parents makes this struggle :(
			// TODO parse each CSV row, ditch the parentId and compare data
// 			$this->assertEquals(file($table->getPathname()), file(__DIR__ . "{$testFilesPath}/{$name}.csv"));
			// compare headers at least
			$this->assertEquals(file($table->getPathname())[0], file(__DIR__ . "{$testFilesPath}/{$name}.csv")[0]);
		}

		// compare all the files are present
		$dir = scandir(__DIR__ . "{$testFilesPath}/");
		array_walk($dir, function (&$val) {
				$val = str_replace(".csv", "", $val);
			}
		);
		$this->assertEquals(array_diff($dir, array_keys($parser->getCsvFiles())), array(".",".."));
		$this->assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getCsvFiles());
	}

	public function testValidateHeader() {
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

		$this->assertEquals($validHeader, $expectedHeader);
	}

	public function testPrimaryKeys()
	{
		$parser = $this->getParser();

		$testFilesPath = '/../../_data/Json_tweets_pinkbike';

		$file = file_get_contents(__DIR__ . "{$testFilesPath}.json");
		$data = json_decode($file);

		$pks = [
			'root_statuses' => 'id',
			'root_statuses_entities_urls' => 'url,JSON_parentId'
		];
		$parser->process($data);
		$parser->addPrimaryKeys($pks);

		$files = $parser->getCsvFiles();
		foreach($pks as $table => $pk) {
			$this->assertEquals($files[$table]->getPrimaryKey(), $pk);
		}
		$this->assertEquals($files['root']->getPrimaryKey(), null);
	}

	public function testNoStrictScalarChange()
	{
		$parser = $this->getParser();

		$data = Utils::json_decode('[
			{"field": 128},
			{"field": "string"},
			{"field": true}
		]');

		$parser->process($data);
		$this->assertEquals(file(reset($parser->getCsvFiles())->getPathname()), [
			'"field"' . PHP_EOL,
			'"128"' . PHP_EOL,
			'"string"' . PHP_EOL,
			'"1"' . PHP_EOL // true gets converted to "1"! should be documented!
		]);
	}

	/**
	 * @expectedException \Keboola\Json\Exception\JsonParserException
	 * @expectedExceptionMessage Unhandled type change from "integer" to "string" in 'root.field'
	 */
	public function testStrictScalarChange()
	{
		$parser = $this->getParser();
		$parser->setStrict(true);

		$data = Utils::json_decode('[
			{"field": 128},
			{"field": "string"},
			{"field": true}
		]');

		$parser->process($data);
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
}
