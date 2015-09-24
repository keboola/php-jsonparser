<?php

use Keboola\Json\Analyzer,
	Keboola\Json\Struct;
// use Keboola\CsvTable\Table;
// use Keboola\Utils\Utils;

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
		$analyzer = new Analyzer($this->getLogger());
		$analyzer->analyze($data, 'root');

		$this->assertEquals(
			[
				'root.arr' => ['data' => 'integer'],
				'root.obj' => [
					'str' => 'string',
					'double' => 'double',
					'scalar' => 'scalar',
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
				]
			],
			(object) [
				'id' => 2,
				'arrOfScalars' => [2,3],
				'arrOfObjects' => (object) ['innerId' => 2.1]
			]
		];

		$analyzer = new Analyzer($this->getLogger());
		$analyzer->getStruct()->setAutoUpgradeToArray(true);
		$analyzer->analyze($data, 'root');

		var_dump($analyzer->getStruct()->getStruct());
	}
}
