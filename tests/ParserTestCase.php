<?php
// namespace Keboola\Json;

use Keboola\Json\Parser;
use Keboola\CsvTable\Table;
use Keboola\Utils\Utils;

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

// 	protected static function getProperty($obj, $name)
// 	{
// 		$class = new \ReflectionClass($obj);
// 		$property = $class->getProperty($name);
// 		$property->setAccessible(true);
// 		return $property->getValue($obj);
// 	}
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
		return Utils::json_decode($file);
	}

	protected function getParser()
	{
		return Parser::create(new \Monolog\Logger('test', [new \Monolog\Handler\TestHandler()]));
	}

	protected function getDataDir()
	{
		return __DIR__ . "/_data/";
	}
}
