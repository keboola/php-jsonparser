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
        $this->assertTrue($this->callMethod($struct, 'upgradeToArrayCheck', ['integer', 'arrayOfinteger']));
        $this->assertFalse($this->callMethod($struct, 'upgradeToArrayCheck', ['integer', 'object']));
        $this->assertFalse($this->callMethod($struct, 'upgradeToArrayCheck', ['arrayOfscalar', 'object']));
    }

    public function testAutoUpgradeToArray()
    {
        $struct = $this->getStruct();

        $struct->setAutoUpgradeToArray(true);
        $this->assertEquals('arrayOfinteger', $this->callMethod($struct, 'update', [
            'arrayOfinteger',
            'integer',
            'a2s',
            new \stdClass
        ]));
        $this->assertEquals('arrayOfinteger', $this->callMethod($struct, 'update', [
            'integer',
            'arrayOfinteger',
            's2a',
            new \stdClass
        ]));
    }

    public function testLoad()
    {
        $struct = $this->getStruct();

        $data = [
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
        ];

        $struct->load($data);

        $this->assertEquals($data, $struct->getStruct());
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Error loading data structure definition in 'root.arr.data'! 'potato' is not a valid data type.
     */
    public function testLoadInvalidType()
    {

        $struct = $this->getStruct();

        $data = [
            'root.arr' => ['data' => 'potato']
        ];

        $struct->load($data);
    }

    /**
     * @expectedException \Keboola\Json\Exception\JsonParserException
     * @expectedExceptionMessage Error loading data structure definition in 'root.arr.oneTooMany'! '{"data":"potato"}' is not a valid data type.
     */
    public function testLoadTooDeep()
    {

        $struct = $this->getStruct();

        $data = [
            'root.arr' => [ 'oneTooMany' => ['data' => 'potato']]
        ];

        $struct->load($data);
    }

    // TODO testLoad() w/ error, add "soft" load that ditches wrong values

    protected function getStruct()
    {
        return new Struct(new \Monolog\Logger('test', [new \Monolog\Handler\TestHandler()]));
    }

}
