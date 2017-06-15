<?php
namespace Keboola\Json\Test;

use Keboola\Json\Parser;
use Psr\Log\NullLogger;

class ParserTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Call a non-public method
     * @param mixed $obj
     * @param string $name
     * @param array $args
     * @return mixed the class' method's return value
     */
    protected static function callMethod($obj, $name, array $args = [])
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }

    protected function getLogger($name = 'test', $null = false)
    {
        return new \Monolog\Logger(
            $name,
            $null ? [new \Monolog\Handler\NullHandler()] : []
        );
    }

    protected function loadJson($fileName)
    {
        $testFilesPath = $this->getDataDir() . $fileName . ".json";
        $file = file_get_contents($testFilesPath);
        return \Keboola\Utils\jsonDecode($file);
    }

    protected function getParser()
    {
        return Parser::create(new NullLogger());
    }

    protected function getDataDir()
    {
        return __DIR__ . "/../../../../tests/_data/";
    }
}
