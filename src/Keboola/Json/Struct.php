<?php
namespace Keboola\Json;

use Monolog\Logger;
use Keboola\Json\Exception\JsonParserException;

class Struct
{
	/**
	 * Structures of analyzed data
	 * @var array
	 */
	protected $struct = [];

	/**
	 * @var bool
	 */
	protected $strict = false;

	/**
	 * @var bool
	 */
	protected $autoUpgradeToArray = false;

	/**
	 * @var array
	 */
	protected $scalars = [
		"integer",
		"double",
		// "float", // get_type returns "double"
		"string",
		"boolean",
		"NULL"
	];

	/**
	 * @var Logger
	 */
	protected $log;

	public function __construct(Logger $logger)
	{
		$this->log = $logger;
	}

	public function load(array $struct = [])
	{
		foreach($struct as $type => $defs) {
			foreach($defs as $node => $type) {
				if (
					!$this->isValidType($type)
					&& !(substr($type, 0, 11) == 'arrayOf' && $this->isValidType(substr($type, 11)))
				) {
					if (!is_scalar($type)) {
						$type = json_encode($type);
					}

					throw new JsonParserException("Error loading data structure definition in '{$node}'! '{$type}' is not a valid data type.");
				}
			}
		}

		$this->struct = $struct;
	}

	/**
	 * Save the analysis result
	 * @param string $type
	 * @param array $struct
	 */
	public function add($type, array $struct)
	{
		if (empty($this->struct[$type]) || $this->struct[$type] == "NULL") {
			// if we already know the row's types
			$this->struct[$type] = $struct;
		} elseif ($this->struct[$type] !== $struct) {
			// If the current row doesn't match the known structure
			$diff = array_diff_assoc($struct, $this->struct[$type]);
			// Walk through mismatched fields
			foreach($diff as $diffKey => $diffVal) {
				$this->struct[$type][$diffKey] = $this->update(
					empty($this->struct[$type][$diffKey]) ? null : $this->struct[$type][$diffKey],
					$struct[$diffKey],
					"{$type}.{$diffKey}"
				);
			}
		}
	}

	/**
	 * Return currently stored dataType with currently analyzed one,
	 * if it is a valid update
	 * @param string &$oldType
	 * @param string $newType
	 * @param string $type for logging
	 * @return string $oldType|$newType
	 */
	public function update($oldType, $newType, $type)
	{
		if (!$this->strict) {
			if ($this->typeIsScalar($oldType)) {
				$oldType = 'scalar';
			}

			if ($this->typeIsScalar($newType)) {
				$newType = 'scalar';
			}
		}

		if (
			empty($oldType)
			|| $oldType == "NULL"
		) {
			// Assign if the field is new
			return $newType;
		} elseif (
			$newType == "NULL"
			|| $newType == $oldType
		) {
			// If new type is null or unchanged
			// do nothing and keep the originally stored type!
			return $oldType;
		} elseif (
			$this->upgradeToArrayCheck($oldType, $newType)
		) {
			return $this->upgradeToArray($oldType, $newType);
		} elseif ($newType != "NULL") {
			// Throw a JsonParserException 'cos of a type mismatch
			$old = json_encode($oldType);
			$new = json_encode($newType);
			throw new JsonParserException(
				"Unhandled type change from {$old} to {$new} in '{$type}'"
			);
		} else {
			// Now obviously this shouldn't ever possibly happen,
			// but if it does, let's have something to work with
			throw new JsonParserException(
				"Unexpected error occured while updating the structure tree!",
				[
					'oldType' => $oldType,
					'newType' => $newType,
					'type' => $type
				]
			);
		}
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	protected function isValidType($type)
	{
		$nonScalars = [
			'scalar',
			'object',
			'array'
		];
		return in_array($type, $this->scalars) || in_array($type, $nonScalars);
	}

	/**
	 * @param string $oldType
	 * @param string $newType
	 * @return bool
	 */
	protected function upgradeToArrayCheck($oldType, $newType)
	{
		return $this->autoUpgradeToArray
			&& (
				(substr($oldType, 0, 7) == 'arrayOf' && substr($oldType, 7) == $newType) // the newType will have to be a check for scalar/whatevs (scalar OR object? what about arrays?)
				|| $oldType == 'array'
				|| $newType == 'array'
			);
	}

	/**
	 * @param string $oldType
	 * @param string $newType
	 * @return string
	 */
	protected function upgradeToArray($oldType, $newType)
	{
		if (substr($oldType, 0, 7) == 'arrayOf') {
			return $oldType;
		} elseif ($oldType == 'array') {
			return 'arrayOf' . $newType;
		} else {
			return 'arrayOf' . $oldType;
		}
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	protected function typeIsScalar($type)
	{
		return in_array($type, $this->scalars);
	}

	/**
	 * Set whether scalars are treated as compatible
	 * within a field (default = false -> compatible)
	 * @param bool $strict
	 */
	public function setStrict($strict)
	{
		$this->strict = (bool) $strict;
	}


	/**
	 * If enabled, and an object contains an array where
	 * an array is not expected, a "link" ID is saved in place
	 * of the string and a child CSV is created.
	 * Takes priority over allowArrayStringMix
	 *
	 * This should **only** be used with $analyzeRows = -1
	 *
	 * Only enable this as a last resort if you cannot supply a JSON
	 * without inconsistent array/object conflicts
	 * @param bool $enable
	 * @experimental
	 */
	public function setAutoUpgradeToArray($enable)
	{
		$this->log->log('debug', "Using automatic conversion of single values to arrays where required.");

		$this->autoUpgradeToArray = (bool) $enable;
	}

	/**
	 * Return structure definitions as an array
	 * @return array
	 */
	public function getStruct()
	{
		return $this->struct;
	}
}
