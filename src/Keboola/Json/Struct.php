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
     * arrayOfX is always the way an array is stored
     * scalar is default in non-strict
     */
    const STRUCT_VERSION = 2.0;

    /**
     * @var Logger
     */
    protected $log;

    public function __construct(Logger $logger)
    {
        $this->log = $logger;
    }

    /**
     * Initialize the structure with an array of definitions
     * @param array $struct
     */
    public function load(array $struct = [])
    {
        foreach($struct as $key => $defs) {
            if (!is_array($defs)) {
                throw new JsonParserException(
                    "Invalid data definitions in '{$key}'. Each key should contain an array of data types in an associative array.",
                    [
                        'key' => $key,
                        'defs' => $defs
                    ]
                );
            }

            foreach($defs as $node => $type) {
                if (
                    !is_scalar($type)
                    || (!$this->isValidType($type)
                    && !($this->isValidType($this->isArrayOf($type))))
                ) {
                    if (!is_scalar($type)) {
                        $type = json_encode($type);
                    }

                    throw new JsonParserException("Error loading data structure definition in '{$key}.{$node}'! '{$type}' is not a valid data type.");
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
        // TODO $this->struct[$type] should NEVER be 'NULL' - remove in the future
        if (empty($this->struct[$type]) || $this->struct[$type] == "NULL") {
            // If we don't know the type yet
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
     * Should only be called with different $oldType and $newType
     * to prevent turning all into 'scalar'
     * @param string &$oldType
     * @param string $newType
     * @param string $type for logging
     * @return string $oldType|$newType
     */
    public function update($oldType, $newType, $type)
    {
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
        } else {
            // Throw a JsonParserException 'cos of a type mismatch
            $old = json_encode($oldType);
            $new = json_encode($newType);
            throw new JsonParserException(
                "Unhandled type change from {$old} to {$new} in '{$type}'"
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
                ($this->isArrayOf($oldType) == $newType)
                || $newType == 'arrayOf' . $oldType
                || $this->isArrayOf($oldType) == 'NULL'
                || $this->isArrayOf($newType) == 'NULL'
            );
    }

    /**
     * @param string $oldType
     * @param string $newType
     * @return string
     */
    protected function upgradeToArray($oldType, $newType)
    {
        if ($this->isArrayOf($oldType)) {
            if ($this->isArrayOf($oldType) == 'NULL') {
                return $this->isArrayOf($newType) ? $newType : 'arrayOf' . $newType;
            }

            return $oldType;
        } elseif ($oldType == 'array') {
            return 'arrayOf' . $newType;
        } else {
            return 'arrayOf' . $oldType;
        }
    }

    /**
     * @param string $type
     * @return string type of array chuldren
     */
    public function isArrayOf($type)
    {
        return substr($type, 0, 7) == 'arrayOf' ? substr($type, 7) : false;
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
     * If enabled, and an object contains an array where
     * an array is not expected, a "link" ID is saved in place
     * of the string and a child CSV is created.
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
     * Return all structure definitions as an array
     * @return array
     */
    public function getStruct()
    {
        return $this->struct;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasDefinitions($type)
    {
        return !empty($this->struct[$type]);
    }

    /**
     * Get all child data types
     * @param string $type Key for which to retrieve data types
     * @return array Array of data types within the $type ($type should really be a key!)
     */
    public function getDefinitions($type)
    {
        if (!$this->hasDefinitions($type)) {
            throw new JsonParserException("Trying to retrieve unknown definitions for '{$type}'");
        }

        return $this->struct[$type];
    }

    /**
     * @param string $type
     * @param string $child
     * @return bool
     */
    public function hasType($type, $child)
    {
        return $this->hasDefinitions($type) && !empty($this->getDefinitions($type)[$child]);
    }

    /**
     * Get a particular data type of a single key
     * @param string $type
     * @param string $child
     * @return string data type
     */
    public function getType($type, $child)
    {
        $definitions = $this->getDefinitions($type);
        if (!$this->hasType($type, $child)) {
            throw new JsonParserException("Trying to retrieve type of '{$type}.{$child}'");
        }

        return $this->getDefinitions($type)[$child];
    }

    /**
     * Gets the contents type of an array from Struct
     * @param string $type
     * @return string|null
     */
    public function getArrayType($type)
    {
        $separator = strrpos($type, '.');
        if (!$separator) {
            $parent = $type;
            $child = Parser::DATA_COLUMN;
        } else {
            $parent = substr($type, 0, $separator);
            $child = substr($type, $separator + 1);
        }

        if ($this->hasType($parent, $child)) {
            $array = $this->getType($parent, $child);
            return str_replace('arrayOf', '', $array);
        } else {
            return null;
        }
    }

    /**
     * Version of $struct array used in parser
     * @return double
     */
    public function getStructVersion()
    {
        return self::STRUCT_VERSION;
    }

    public function __toString()
    {
        return json_encode($this->getStruct());
    }
}
