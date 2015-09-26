<?php

use Keboola\Json\Struct;
// use Keboola\CsvTable\Table;
// use Keboola\Utils\Utils;

require_once 'tests/ParserTestCase.php';


class StructTest extends ParserTestCase
{
	public function testTypeIsScalar()
	{
		$struct = $this->getStruct();

		$this->assertTrue($this->callMethod($struct, 'typeIsScalar', ['integer']));
		$this->assertTrue($this->callMethod($struct, 'typeIsScalar', ['string']));
		$this->assertTrue($this->callMethod($struct, 'typeIsScalar', ['double']));
		$this->assertTrue($this->callMethod($struct, 'typeIsScalar', ['boolean']));
		$this->assertFalse($this->callMethod($struct, 'typeIsScalar', ['array']));
		$this->assertFalse($this->callMethod($struct, 'typeIsScalar', ['object']));
	}

	public function testUpgradeToArrayCheck()
	{
		$struct = $this->getStruct();
		$struct->setAutoUpgradeToArray(true);

		// scalar strict
		$this->assertTrue($this->callMethod($struct, 'upgradeToArrayCheck', ['arrayOfinteger', 'integer']));
		$this->assertTrue($this->callMethod($struct, 'upgradeToArrayCheck', ['integer', 'array']));
	}

	public function testAutoUpgradeToArray()
	{
		$struct = $this->getStruct();

		// TODO test all options/flags/combinations/types!

		// setAutoUpgradeToArray
		$struct->setAutoUpgradeToArray(true);
		$this->assertEquals('arrayOfscalar', $this->callMethod($struct, 'update', [
			'array',
			'integer',
			'test0',
			new \stdClass
		]));
		$this->assertEquals('arrayOfscalar', $this->callMethod($struct, 'update', [
			'integer',
			'array',
			'test1',
			new \stdClass
		]));

		$struct->setStrict(true);
		$this->assertEquals('arrayOfinteger', $this->callMethod($struct, 'update', [
			'array',
			'integer',
			'test2',
			new \stdClass
		]));
		$this->assertEquals('arrayOfstring', $this->callMethod($struct, 'update', [
			'string',
			'array',
			'test3',
			new \stdClass
		]));
	}

	// TODO testLoad() w/ error, add "soft" load that ditches wrong values

	protected function getStruct()
	{
		return new Struct(new \Monolog\Logger('test', [new \Monolog\Handler\TestHandler()]));
	}

}
