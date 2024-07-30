<?php

declare(strict_types=1);

namespace Keboola\Json\Tests;

use Keboola\Json\Exception\InconsistentValueException;
use Keboola\Json\Exception\JsonParserException;
use Keboola\Json\NodePath;
use Keboola\Json\Structure;
use PHPUnit\Framework\TestCase;

class StructureTest extends TestCase
{
    public function testSaveNodeInvalid(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Node path [] does not exist.');
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'array']);
    }

    public function testSaveNode(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'prop']), ['nodeType' => 'scalar']);
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_prop' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveNodeReserved1(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => ['object']]);

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Conflict property nodeType');
        $structure->getData();
    }

    public function testSaveNodeReserved2(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(
            new NodePath(['root', Structure::ARRAY_NAME, Structure::ARRAY_NAME]),
            ['nodeType' => 'scalar'],
        );

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Array [] is not an array.');
        $structure->getData();
    }

    public function testSaveValue(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME]), 'headerNames', 'my-object');
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            'headerNames' => 'my-object',
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveValueConflictProp(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', 'obj']), ['headerName' => 'object']);

        $this->expectException(InconsistentValueException::class);
        $this->expectExceptionMessage("Attempting to overwrite 'headerName' value 'object' with 'my-object'");
        $structure->saveNodeValue(new NodePath(['root', 'obj']), 'headerName', 'my-object');
    }

    public function testSaveValueConflictTypeUpgradeFail(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', 'obj']), ['nodeType' => 'object']);

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Unhandled nodeType change from "object" to "string" in "root.obj"');
        $structure->saveNodeValue(new NodePath(['root', 'obj']), 'nodeType', 'string');
    }

    public function testSaveValueConflictTypeUpgradeAllowed1(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', 'obj']), ['nodeType' => 'null']);
        $structure->saveNodeValue(new NodePath(['root', 'obj']), 'nodeType', 'string');
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'object',
                        '_obj' => [
                            'nodeType' => 'string',
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveValueConflictTypeUpgradeAllowed2(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', 'obj']), ['nodeType' => 'object']);
        $structure->saveNodeValue(new NodePath(['root', 'obj']), 'nodeType', 'null');
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'object',
                        '_obj' => [
                            'nodeType' => 'object',
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveValueConflictTypeUpgradeArrayAllowed1(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'str']), ['nodeType' => 'scalar']);
        $structure->saveNodeValue(
            new NodePath(['root', Structure::ARRAY_NAME, 'str', Structure::ARRAY_NAME]),
            'nodeType',
            'scalar',
        );
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'str']), 'nodeType', 'array');
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_str' => [
                                '[]' => [
                                    'nodeType' => 'scalar',
                                ],
                                'nodeType' => 'array',
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveValueConflictTypeUpgradeArrayAllowed2(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'obj', '[]']), ['nodeType' => 'object']);
        $structure->saveNode(
            new NodePath(['root', Structure::ARRAY_NAME, 'obj', Structure::ARRAY_NAME, 'prop']),
            ['nodeType' => 'scalar'],
        );
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), 'nodeType', 'object');
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_obj' => [
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_prop' => [
                                        'nodeType' => 'scalar',
                                    ],
                                ],
                                'nodeType' => 'array',
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveValueConflictTypeUpgradeArrayAllowed3(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(
            new NodePath(['root', Structure::ARRAY_NAME, 'obj']),
            ['nodeType' => 'object', 'headerNames' => 'my-obj'],
        );
        $structure->saveNode(
            new NodePath(['root', Structure::ARRAY_NAME, 'obj', Structure::ARRAY_NAME]),
            ['nodeType' => 'object'],
        );
        $structure->saveNode(
            new NodePath(['root', Structure::ARRAY_NAME, 'obj', Structure::ARRAY_NAME, 'prop']),
            ['nodeType' => 'scalar'],
        );
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), 'nodeType', 'array');
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            '_obj' => [
                                '[]' => [
                                    'nodeType' => 'object',
                                    '_prop' => [
                                        'nodeType' => 'scalar',
                                    ],
                                    'headerNames' => 'data',
                                ],
                                'nodeType' => 'array',
                                'headerNames' => 'my-obj',
                            ],
                        ],
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testSaveValueConflictTypeUpgradeArrayInvalid(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), ['nodeType' => 'object']);

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Array contents are unknown');
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), 'nodeType', 'array');
    }

    public function testSaveValueConflictTypeUpgradeArrayDisabled(): void
    {
        $structure = new Structure(false);
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), ['nodeType' => 'object']);

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Unhandled nodeType change from "object" to "array" in "root.[].obj"');
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), 'nodeType', 'array');
    }

    public function testSaveValueConflictTypeUpgradeArrayNotAllowed1(): void
    {
        $structure = new Structure(false);
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), ['nodeType' => 'object']);

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Unhandled nodeType change from "object" to "string" in "root.[].obj"');
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'obj']), 'nodeType', 'string');
    }

    public function testSaveValueConflictTypeUpgradeArrayNotAllowed2(): void
    {
        $structure = new Structure();
        $structure->saveNode(new NodePath(['root']), ['nodeType' => 'array']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME]), ['nodeType' => 'object']);
        $structure->saveNode(new NodePath(['root', Structure::ARRAY_NAME, 'str']), ['nodeType' => 'scalar']);
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'str', '[]']), 'nodeType', 'object');

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage("Data array in 'root.[].str' contains incompatible types 'object' and 'scalar'");
        $structure->saveNodeValue(new NodePath(['root', Structure::ARRAY_NAME, 'str']), 'nodeType', 'array');
    }

    public function testLoad(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                'root' => [
                    'nodeType' => 'object',
                    'obj' => [
                        'nodeType' => 'string',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertEquals($data, $structure->getData());
    }

    public function testGetNode(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                '_root' => [
                    'nodeType' => 'object',
                    '_obj' => [
                        'nodeType' => 'string',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertEquals(['nodeType' => 'string'], $structure->getNode(new NodePath(['root', 'obj'])));
    }

    public function testGetNodeInvalid(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                'root' => [
                    'nodeType' => 'object',
                    'obj' => [
                        'nodeType' => 'string',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertNull($structure->getNode(new NodePath(['root', 'non-existent'])));
    }

    public function testGetNodeProperty(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                '_root' => [
                    'nodeType' => 'object',
                    '_obj' => [
                        'nodeType' => 'string',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertEquals('string', $structure->getNodeProperty(new NodePath(['root', 'obj']), 'nodeType'));
    }

    public function testGetColumnTypes1(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                '_root' => [
                    'nodeType' => 'object',
                    '_obj' => [
                        'nodeType' => 'object',
                        '_prop1' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                        '_prop2' => [
                            'nodeType' => 'scalar',
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertEquals(
            ['prop1' => 'array', 'prop2' => 'scalar'],
            $structure->getColumnTypes(new NodePath(['root', 'obj'])),
        );
    }

    public function testGetColumnTypes2(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                '_root' => [
                    'nodeType' => 'object',
                    '_obj' => [
                        'nodeType' => 'object',
                        '_prop1' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                        '_prop2' => [
                            'nodeType' => 'scalar',
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertEquals(
            ['prop2' => 'scalar'],
            $structure->getColumnTypes(new NodePath(['root', 'obj', 'prop2'])),
        );
    }

    public function testGetColumnTypes3(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                '_root' => [
                    'nodeType' => 'object',
                    '_obj' => [
                        'nodeType' => 'object',
                        '_prop1' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                        '_prop2' => [
                            'nodeType' => 'scalar',
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        self::assertEquals(
            [],
            $structure->getColumnTypes(new NodePath(['root', 'obj', 'prop1'])),
        );
    }

    public function testHeaderNames(): void
    {
        $structure = new Structure();
        $data = [
            'data' => [
                '_root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        '_a very long name of a property of an object which exceeds the length of 60 characters' => [
                            'nodeType' => 'object',
                        ],
                        '_some special characters!@##%$*&(^%$#09do' => [
                            'nodeType' => 'scalar',
                        ],
                        '_prop2.something' => [
                            'nodeType' => 'scalar',
                        ],
                        '_prop2_something' => [
                            'nodeType' => 'scalar',
                        ],
                        '_array' => [
                            'nodeType' => 'array',
                            '[]' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [],
        ];
        $structure->load($data);
        $structure->generateHeaderNames();
        self::assertEquals(
            [
                'data' => [
                    '_root' => [
                        '[]' => [
                            '_a very long name of a property of an object which exceeds the length of 60 characters' =>
                                [
                                    'nodeType' => 'object',
                                    'headerNames' => 'of_an_object_which_exceeds_the_length_of_60_characters',
                                ],
                            '_some special characters!@##%$*&(^%$#09do' => [
                                'nodeType' => 'scalar',
                                'headerNames' => 'some_special_characters_09do',
                            ],
                            '_prop2.something' => [
                                'nodeType' => 'scalar',
                                'headerNames' => 'prop2_something',
                            ],
                            '_prop2_something' => [
                                'nodeType' => 'scalar',
                                'headerNames' => 'prop2_something_u0',
                            ],
                            '_array' => [
                                'nodeType' => 'array',
                                'headerNames' => 'array',
                                '[]' => [
                                    'nodeType' => 'scalar',
                                    'headerNames' => 'data',
                                ],
                            ],
                            'nodeType' => 'object',
                            'headerNames' => 'data',
                        ],
                        'nodeType' => 'array',
                    ],
                ],
                'parent_aliases' => [],
            ],
            $structure->getData(),
        );
    }

    public function testGetTypeFromPath(): void
    {
        $structure = new Structure();
        self::assertEquals(
            'root_prop',
            $structure->getTypeFromNodePath(new NodePath(['root', Structure::ARRAY_NAME, 'prop'])),
        );
    }

    public function testLoadInvalid1(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Undefined data type invalid-type');
        $structure->load([
            'data' => [
                '_root' => [
                    'nodeType' => 'invalid-type',
                    '[]' => [
                        'nodeType' => 'scalar',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ]);
    }

    public function testLoadInvalid2(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Undefined property _invalidProperty');
        $structure->load([
            'data' => [
                '_root' => [
                    'nodeType' => 'array',
                    '_invalidProperty' => 'array',
                    '[]' => [
                        'nodeType' => 'scalar',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ]);
    }

    public function testLoadInvalid3(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Array node does not have array.');
        $structure->load([
            'data' => [
                '_root' => [
                    'headerNames' => 'root',
                    'nodeType' => 'array',
                    '_invalidArray' => [
                        'nodeType' => 'scalar',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ]);
    }

    public function testLoadInvalid4(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Conflict property nodeType');
        $structure->load([
            'data' => [
                '_root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        '_prop1' => [
                            'nodeType' => 'scalar',
                            'type' => 'parent',
                        ],
                        '_prop2' => [
                            'nodeType' => [
                                'invalid-node-type',
                            ],
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [],
        ]);
    }

    public function testLoadInvalid5(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Undefined property invalid-property');
        $structure->load([
            'data' => [
                '_root' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        '_prop1' => [
                            'nodeType' => 'scalar',
                            'type' => 'parent',
                        ],
                        '_prop2' => [
                            'nodeType' => 'object',
                            'invalid-property' => 'fooBar',
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [],
        ]);
    }

    public function testLoadInvalid6(): void
    {
        $structure = new Structure();

        $this->expectException(JsonParserException::class);
        $this->expectExceptionMessage('Node data type is not set.');
        $structure->load([
            'data' => [
                '_root' => [
                    '[]' => [
                        'nodeType' => 'object',
                    ],
                ],
            ],
            'parent_aliases' => [],
        ]);
    }
}
