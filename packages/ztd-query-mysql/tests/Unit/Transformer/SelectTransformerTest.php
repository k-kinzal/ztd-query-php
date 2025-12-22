<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\TransformerContractTest;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(SelectTransformer::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
final class SelectTransformerTest extends TransformerContractTest
{
    protected function createTransformer(): SqlTransformer
    {
        return new SelectTransformer();
    }

    protected function selectSql(): string
    {
        return 'SELECT * FROM users WHERE id = 1';
    }

    #[\Override]
    protected function nativeIntegerType(): string
    {
        return 'INT';
    }

    #[\Override]
    protected function nativeStringType(): string
    {
        return 'VARCHAR(255)';
    }

    public function testTransformWithNoTablesReturnsOriginalSql(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT 1';
        $result = $transformer->transform($sql, []);
        self::assertSame($sql, $result);
    }

    public function testTransformWithShadowDataAddsCte(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [['id' => '1', 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('WITH `users` AS (SELECT', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
        self::assertStringEndsWith('SELECT * FROM users', $result);
    }

    public function testTransformWithEmptyTableProducesEmptyCte(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`users` AS (SELECT', $result);
        self::assertStringContainsString('FROM DUAL WHERE 0)', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformSkipsUnreferencedTables(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT 1';
        $tables = [
            'orders' => [
                'rows' => [['id' => '1']],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertSame($sql, $result);
    }

    public function testTransformNullValueProducesNull(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => null]],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('NULL AS `name`', $result);
    }

    public function testTransformBoolValueProducesTrueOrFalse(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM flags';
        $tables = [
            'flags' => [
                'rows' => [['active' => true, 'deleted' => false]],
                'columns' => ['active', 'deleted'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('TRUE AS `active`', $result);
        self::assertStringContainsString('FALSE AS `deleted`', $result);
    }

    public function testTransformFloatValueProducesStringRepresentation(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM prices';
        $tables = [
            'prices' => [
                'rows' => [['amount' => 3.14]],
                'columns' => ['amount'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('3.14 AS `amount`', $result);
    }

    public function testTransformIntValueProducesCastExpression(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM nums';
        $tables = [
            'nums' => [
                'rows' => [['val' => 42]],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('42', $result);
        self::assertStringContainsString('AS `val`', $result);
    }

    public function testTransformStringValueProducesQuotedCast(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM names';
        $tables = [
            'names' => [
                'rows' => [['val' => 'hello']],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'hello'", $result);
        self::assertStringContainsString('AS `val`', $result);
    }

    public function testTransformWithColumnTypesAppliesCast(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM typed';
        $tables = [
            'typed' => [
                'rows' => [['val' => '42']],
                'columns' => ['val'],
                'columnTypes' => ['val' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CAST', $result);
        self::assertStringContainsString('AS `val`', $result);
    }

    public function testTransformWithEmptyRowsAndColumnTypesUsesNullCast(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM typed';
        $tables = [
            'typed' => [
                'rows' => [],
                'columns' => ['val'],
                'columnTypes' => ['val' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CAST(NULL', $result);
        self::assertStringContainsString('AS `val`', $result);
        self::assertStringContainsString('WHERE 0', $result);
    }

    public function testTransformMultipleRowsUsesUnionAll(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('UNION ALL', $result);
        self::assertStringContainsString('`users` AS (SELECT', $result);
        self::assertStringContainsString('SELECT * FROM users', $result);
        self::assertSame(2, substr_count($result, 'AS `id`'));
        self::assertSame(2, substr_count($result, 'AS `name`'));
    }

    public function testTransformEmptyColumnsAndRowsSkipsTable(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('WITH', $result);
    }

    public function testTransformColumnsInferredFromRowsWhenEmpty(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformWithExistingWithClausePrependsCtesCorrectly(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'WITH cte AS (SELECT 1) SELECT * FROM users, cte';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('WITH `users` AS', $result);
        self::assertStringContainsString('cte AS (SELECT 1)', $result);
        self::assertStringContainsString('SELECT * FROM users, cte', $result);
    }

    public function testTransformSetColumnOrderByRewriting(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red,blue']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue','green')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
    }

    public function testTransformOrderByWithNoSetColumnsUnchanged(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `name`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetValueNormalization(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items';
        $tables = [
            'items' => [
                'rows' => [['colors' => 'blue,red']],
                'columns' => ['colors'],
                'columnTypes' => [
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue','green')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'red,blue'", $result);
    }

    public function testTransformEmptySetValuePreserved(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items';
        $tables = [
            'items' => [
                'rows' => [['colors' => '']],
                'columns' => ['colors'],
                'columnTypes' => [
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("''", $result);
    }

    public function testTransformMultipleTablesProducesMultipleCtes(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users JOIN orders ON users.id = orders.user_id';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
            'orders' => [
                'rows' => [['id' => 10, 'user_id' => 1]],
                'columns' => ['id', 'user_id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`users` AS', $result);
        self::assertStringContainsString('`orders` AS', $result);
    }

    public function testTransformUnsupportedValueTypeThrows(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM data';
        $tables = [
            'data' => [
                'rows' => [['val' => [1, 2, 3]]],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Unsupported value type');
        $transformer->transform($sql, $tables);
    }

    public function testTransformWithFallbackNullCastWhenNoColumnType(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items';
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CAST(NULL', $result);
        self::assertStringContainsString('WHERE 0', $result);
    }

    public function testTransformEscapesSingleQuotesInValues(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [['name' => "O'Brien"]],
                'columns' => ['name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("O''Brien", $result);
    }

    public function testTransformQualifiedSetOrderByWithDirection(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `items`.`colors` DESC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('DESC', $result);
    }

    public function testTransformRowsWithNoColumnsAndRowsExistInfersFromRows(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM data';
        $tables = [
            'data' => [
                'rows' => [
                    ['a' => 1],
                    ['a' => 2, 'b' => 3],
                ],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `a`', $result);
        self::assertStringContainsString('AS `b`', $result);
    }

    public function testTransformSingleRowCteFormat(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['a' => 'x']],
                'columns' => ['a'],
                'columnTypes' => ['a' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertMatchesRegularExpression('/^WITH `t` AS \(SELECT .+ AS `a`\)\nSELECT \* FROM t$/', $result);
    }

    public function testTransformEmptyCteContainsDualAndWhere0(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['a'],
                'columnTypes' => ['a' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertMatchesRegularExpression('/`t` AS \(SELECT .+ AS `a` FROM DUAL WHERE 0\)/', $result);
    }

    public function testTransformCteWithMultipleTablesJoinedByComma(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM a JOIN b ON a.id = b.aid';
        $tables = [
            'a' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
            'b' => [
                'rows' => [],
                'columns' => ['aid'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`a` AS', $result);
        self::assertStringContainsString('`b` AS', $result);
        $withCount = substr_count($result, 'WITH');
        self::assertSame(1, $withCount);
    }

    public function testTransformSetOrderByMultipleItems(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`, `id` ASC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('`id` ASC', $result);
    }

    public function testTransformSetOrderByBitPowerCalculation(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue','green')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("IF(FIND_IN_SET('red'", $result);
        self::assertStringContainsString(', 1, 0)', $result);
        self::assertStringContainsString("IF(FIND_IN_SET('blue'", $result);
        self::assertStringContainsString(', 2, 0)', $result);
        self::assertStringContainsString("IF(FIND_IN_SET('green'", $result);
        self::assertStringContainsString(', 4, 0)', $result);
    }

    public function testTransformNoOrderBySkipsRewrite(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items WHERE id = 1';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetNormalizationReordersMembers(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'blue,red']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue','green')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'red,blue'", $result);
        self::assertStringNotContainsString("'blue,red'", $result);
    }

    public function testTransformSetNormalizationFiltersInvalidMembers(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'red,invalid']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'red'", $result);
    }

    public function testTransformEmptyColumnsEmptyRowsThrowsForRowsWithoutColumns(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['a' => 1]],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `a`', $result);
    }

    public function testTransformStringWithQuotesEscapesCorrectly(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['name' => "it's"]],
                'columns' => ['name'],
                'columnTypes' => ['name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("it''s", $result);
    }

    public function testTransformSkipsUnreferencedFirstTableProcessesSecond(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM orders';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
            'orders' => [
                'rows' => [['oid' => 10]],
                'columns' => ['oid'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`orders` AS', $result);
        self::assertStringNotContainsString('`users` AS', $result);
    }

    public function testTransformSkipsEmptyFirstTableProcessesSecond(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM users, orders';
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => [],
                'columnTypes' => [],
            ],
            'orders' => [
                'rows' => [['oid' => 10]],
                'columns' => ['oid'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`orders` AS', $result);
    }

    public function testTransformObjectWithToStringProducesStringValue(): void
    {
        $obj = new class () {
            public function __toString(): string
            {
                return 'stringified';
            }
        };
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['val' => $obj]],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('stringified', $result);
    }

    public function testTransformSetOrderByWithLimitClause(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` LIMIT 10';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('LIMIT 10', $result);
    }

    public function testTransformSetNormalizationAllInvalid(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'invalid,also_invalid']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'invalid,also_invalid'", $result);
    }

    public function testTransformSetNormalizationWithNonSetType(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'hello']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'hello'", $result);
    }

    public function testTransformSetNormalizationDoubleQuotedMembers(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'b,a']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'SET("a","b","c")'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetNormalizationUnquotedFallback(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'b,a']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'SET(a,b,c)'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetOrderBySkipsUnreferencedTableInRewrite(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'other' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                ],
            ],
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetOrderByConflictingSetColumnsAcrossTables(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM a JOIN b ON a.id = b.id ORDER BY `colors`';
        $tables = [
            'a' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
            'b' => [
                'rows' => [['id' => 1, 'colors' => 'x']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('x','y')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('ORDER BY `colors`', $result);
    }

    public function testTransformSetOrderByNonMatchingColumn(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `name`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red', 'name' => 'A']],
                'columns' => ['id', 'colors', 'name'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetNormalizationEmptyDeclaredList(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'val']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET()"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'val'", $result);
    }

    public function testTransformWithExistingWithAndLeadingComment(): void
    {
        $transformer = new SelectTransformer();
        $sql = "/* comment */WITH cte AS (SELECT 1) SELECT * FROM users, cte";
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`users` AS', $result);
        self::assertStringContainsString('cte AS (SELECT 1)', $result);
    }

    public function testTransformSetOrderByExactRankTermFormat(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` DESC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'a']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertMatchesRegularExpression('/ORDER BY\s+\(/', $result);
        self::assertStringContainsString(') DESC', $result);
        self::assertStringContainsString(' + ', $result);
    }

    public function testTransformSetOrderByAscDirection(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` ASC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'x']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('x','y')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertMatchesRegularExpression('/ORDER BY\s+\(/', $result);
        self::assertStringContainsString(') ASC', $result);
    }

    public function testTransformCteOutputContainsWithAndAs(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['a' => 1, 'b' => 'hello']],
                'columns' => ['a', 'b'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('WITH', $result);
        self::assertStringContainsString('`t` AS', $result);
        self::assertStringContainsString('AS `a`', $result);
        self::assertStringContainsString('AS `b`', $result);
        self::assertStringContainsString('SELECT * FROM t', $result);
    }

    public function testTransformMultiTableCteOutputContainsBothCtes(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t1 JOIN t2 ON t1.id = t2.t1_id';
        $tables = [
            't1' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
            't2' => [
                'rows' => [['t1_id' => 1, 'val' => 'x']],
                'columns' => ['t1_id', 'val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('WITH', $result);
        self::assertStringContainsString('`t1` AS', $result);
        self::assertStringContainsString('`t2` AS', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `val`', $result);
    }

    public function testTransformRowWithNullValueProducesNullLiteral(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'name' => null]],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('NULL AS `name`', $result);
    }

    public function testTransformRowWithIntValueProducesCastSigned(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 42, 'amt' => 99]],
                'columns' => ['id', 'amt'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CAST(42 AS SIGNED) AS `id`', $result);
        self::assertStringContainsString('CAST(99 AS SIGNED) AS `amt`', $result);
    }

    public function testTransformRowWithFloatValueProducesCast(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'price' => 9.99]],
                'columns' => ['id', 'price'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `price`', $result);
        self::assertStringContainsString('9.99', $result);
    }

    public function testTransformRowWithBoolTrueValueProducesTrue(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'active' => true]],
                'columns' => ['id', 'active'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('TRUE AS `active`', $result);
    }

    public function testTransformRowWithBoolFalseValueProducesFalse(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'active' => false]],
                'columns' => ['id', 'active'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FALSE AS `active`', $result);
    }

    public function testTransformRowWithStringValueProducesQuotedLiteral(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'Alice'", $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformMultipleRowsUnionAll(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'v' => 'a'], ['id' => 2, 'v' => 'b']],
                'columns' => ['id', 'v'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('UNION ALL', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString("'a'", $result);
        self::assertStringContainsString("'b'", $result);
        self::assertStringContainsString('AS `v`', $result);
    }

    public function testTransformEmptyRowsProducesWhereZero(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('WHERE 0', $result);
    }

    public function testTransformWithCastForIntegerColumn(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('CAST(', $result);
        self::assertStringContainsString('SIGNED', $result);
    }

    public function testTransformSetNormalizationWithEscapedSingleQuoteInMember(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [["s" => "it's"]],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('it''s','me')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("it''s", $result);
    }

    public function testTransformSetNormalizationWithEscapedDoubleQuoteInMember(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'say "hi"']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'SET("say ""hi""","other")'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('say "hi"', $result);
    }

    public function testTransformSetOrderByWithLowercaseSetType(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "set('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetOrderByQualifiedColumnRefUsesTableAndColumn(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `items`.`colors` ASC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('`items`.`colors`', $result);
        self::assertStringContainsString(') ASC', $result);
    }

    public function testTransformSetNormalizationLowercaseSetType(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'b,a']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "set('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetOrderByExtractsCorrectMembersFromDefinition(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue','green')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("FIND_IN_SET('red'", $result);
        self::assertStringContainsString("FIND_IN_SET('blue'", $result);
        self::assertStringContainsString("FIND_IN_SET('green'", $result);
    }

    public function testTransformSetOrderByDoubleQuotedMembersExtractedCorrectly(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'alpha']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, 'SET("alpha","beta")'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("FIND_IN_SET('alpha'", $result);
        self::assertStringContainsString("FIND_IN_SET('beta'", $result);
    }

    public function testTransformSetNormalizationPreservesOrderWhenAlreadySorted(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'a,b']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformConstructorDefaultsCastRenderer(): void
    {
        $transformer = new SelectTransformer(null, null);
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => ['id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CAST(', $result);
    }

    public function testTransformConstructorExplicitCastRenderer(): void
    {
        $castRenderer = new MySqlCastRenderer();
        $quoter = new MySqlIdentifierQuoter();
        $transformer = new SelectTransformer($castRenderer, $quoter);
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => ['id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CAST(', $result);
        self::assertStringContainsString('AS `id`', $result);
    }

    public function testTransformWithExistingWithClausePrependsCteBeforeExisting(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'WITH existing AS (SELECT 1 AS x) SELECT * FROM users, existing';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        $usersPos = strpos($result, '`users` AS');
        $existingPos = strpos($result, 'existing AS (SELECT 1');
        self::assertNotFalse($usersPos);
        self::assertNotFalse($existingPos);
        self::assertLessThan($existingPos, $usersPos);
    }

    public function testTransformSetOrderByForClausePreservesForUpdateSuffix(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` FOR UPDATE';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('FOR UPDATE', $result);
    }

    public function testTransformSetOrderByEmptyClauseNotRewritten(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY  LIMIT 10';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('ORDER BY', $result);
    }

    public function testTransformSetNormalizationTrimsCandidates(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => ' a , b ']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetNormalizationEmptyCandidateSkipped(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'a,,b']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetNormalizationWithNonSetStringType(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'hello']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'ENUM(\'a\',\'b\')'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'hello'", $result);
    }

    public function testTransformSetOrderByNonSetTypeSkipsRewriting(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `status`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'status' => 'active']],
                'columns' => ['id', 'status'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'status' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('active','inactive')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('ORDER BY `status`', $result);
    }

    public function testTransformSetOrderByWithNonBacktickedColumnNotRewritten(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY colors';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetNormalizationDoubleQuotedMemberWithEscapedQuote(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'say "hi"']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'SET("say ""hi""","other")'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('say "hi"', $result);
    }

    public function testTransformRowWithMissingColumnUsesNull(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['a' => 1]],
                'columns' => ['a', 'b'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('NULL AS `b`', $result);
    }

    public function testTransformSetNormalizationWithUnquotedEmptyTokensSkipped(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'a']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'SET(a, ,b)'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a'", $result);
    }

    public function testTransformWithExistingWithClauseProducesCommaBetweenCtes(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'WITH existing AS (SELECT 1 AS x) SELECT * FROM users, existing';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertSame(1, substr_count($result, 'WITH'));
    }

    public function testTransformSetOrderByMultipleItemsMixedColumnTypes(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`, `name`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red', 'name' => 'A']],
                'columns' => ['id', 'colors', 'name'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('`name`', $result);
    }

    public function testTransformSetOrderByBitRankUsesCorrectPowerOfTwo(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'a']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString(', 1, 0)', $result);
        self::assertStringContainsString(', 2, 0)', $result);
        self::assertStringContainsString(', 4, 0)', $result);
    }

    public function testTransformSetNormalizationReordersUnsortedValue(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'c,a']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,c'", $result);
    }

    public function testTransformSetNormalizationSkipsUnknownMember(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'a,unknown,b']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b','c')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetNormalizationAllUnknownReturnsOriginal(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'x,y']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'x,y'", $result);
    }

    public function testTransformSetNormalizationEmptyValueReturnsEmpty(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => '']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("''", $result);
    }

    public function testTransformSetOrderByNoOrderByKeywordSkipsRewrite(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items WHERE id = 1';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetOrderByLimitSuffixPreserved(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` LIMIT 10';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('LIMIT 10', $result);
    }

    public function testTransformSetOrderByLockSuffixPreserved(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` LOCK IN SHARE MODE';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('LOCK IN SHARE MODE', $result);
    }

    public function testTransformSetOrderByWithQualifiedColumn(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `items`.`colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('`items`.`colors`', $result);
    }

    public function testTransformSetOrderByWithDirectionPreserved(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` DESC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
        self::assertStringContainsString('DESC', $result);
    }

    public function testTransformSetOrderByConflictingColumnsInMultipleTablesNotRewritten(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items, products ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
            'products' => [
                'rows' => [['id' => 1, 'colors' => 'green']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('green','yellow')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringNotContainsString('FIND_IN_SET', $result);
    }

    public function testTransformSetNormalizationEmptyDefinitionReturnsOriginal(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'a,b']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET()"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'a,b'", $result);
    }

    public function testTransformSetNormalizationSingleQuotedMemberWithEscapedQuote(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [["s" => "it's"]],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('it''s','other')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("it''s", $result);
    }

    public function testTransformSetNormalizationInvalidSetTypeFallsThrough(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'abc']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "VARCHAR(100)"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'abc'", $result);
    }

    public function testTransformSetOrderByWithTableNotInSqlSkipsMap(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                ],
            ],
            'other_table' => [
                'rows' => [['colors' => 'x']],
                'columns' => ['colors'],
                'columnTypes' => [
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('x','y')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FIND_IN_SET', $result);
    }

    public function testTransformWithEmptyColumnsAndRowsDeriveColumnsFromRows(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['a' => 1, 'b' => 2], ['a' => 3, 'c' => 4]],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `a`', $result);
        self::assertStringContainsString('AS `b`', $result);
        self::assertStringContainsString('AS `c`', $result);
    }

    public function testTransformWithEmptyColumnsAndEmptyRowsSkipsCte(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertSame('SELECT * FROM t', $result);
    }

    public function testTransformMultipleRowsProducesUnionAll(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['id' => 1], ['id' => 2]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('UNION ALL', $result);
    }

    public function testTransformFloatValueProducesFloat(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['val' => 3.14]],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('3.14', $result);
    }

    public function testTransformObjectWithToStringUsesToString(): void
    {
        $obj = new class () {
            public function __toString(): string
            {
                return 'stringified';
            }
        };
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['val' => $obj]],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('stringified', $result);
    }

    public function testTransformWithRowsButNoColumnsUsesRowKeys(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['x' => 10, 'y' => 20]],
                'columns' => [],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `x`', $result);
        self::assertStringContainsString('AS `y`', $result);
    }

    public function testTransformSetOrderByExactBitRankExpression(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'a']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("IF(FIND_IN_SET('a', `colors`) > 0, 1, 0)", $result);
        self::assertStringContainsString("IF(FIND_IN_SET('b', `colors`) > 0, 2, 0)", $result);
    }

    public function testTransformSetOrderByQualifiedExactBitRank(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `items`.`colors` ASC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'a']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("FIND_IN_SET('a', `items`.`colors`)", $result);
        self::assertStringContainsString("FIND_IN_SET('b', `items`.`colors`)", $result);
        self::assertStringContainsString(' ASC', $result);
    }

    public function testTransformSetNormalizationReordersEscapedSingleQuoteMember(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [["s" => "it's,me"]],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, "SET('me','it''s')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'me,it''s'", $result);
    }

    public function testTransformSetNormalizationReordersEscapedDoubleQuoteMember(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['s' => 'say "hi",other']],
                'columns' => ['s'],
                'columnTypes' => [
                    's' => new ColumnType(ColumnTypeFamily::STRING, 'SET("other","say ""hi""")'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'other,say \"hi\"'", $result);
    }

    public function testTransformSetOrderByExactParenthesizedExpression(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'a']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString(
            "ORDER BY (IF(FIND_IN_SET('a', `colors`) > 0, 1, 0) + IF(FIND_IN_SET('b', `colors`) > 0, 2, 0))",
            $result,
        );
    }

    public function testTransformSetOrderByEscapesSingleQuoteInFindInSet(): void
    {
        $transformer = new SelectTransformer();
        $sql = "SELECT * FROM items ORDER BY `status`";
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'status' => "it's"]],
                'columns' => ['id', 'status'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'status' => new ColumnType(ColumnTypeFamily::STRING, "SET('it''s','ok')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("FIND_IN_SET('it''s', `status`)", $result);
    }

    public function testConstructorUsesCustomCastRenderer(): void
    {
        $castRenderer = self::createStub(CastRenderer::class);
        $castRenderer->method('renderCast')->willReturn('CUSTOM_CAST');
        $transformer = new SelectTransformer($castRenderer);
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['x' => 1]],
                'columns' => ['x'],
                'columnTypes' => [
                    'x' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('CUSTOM_CAST', $result);
    }

    public function testConstructorUsesCustomIdentifierQuoter(): void
    {
        $quoter = self::createStub(IdentifierQuoter::class);
        $quoter->method('quote')->willReturnCallback(static fn (string $id): string => "[$id]");
        $transformer = new SelectTransformer(null, $quoter);
        $sql = 'SELECT * FROM t';
        $tables = [
            't' => [
                'rows' => [['x' => 1]],
                'columns' => ['x'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('[t]', $result);
        self::assertStringContainsString('[x]', $result);
    }

    public function testTransformSetOrderByDirectionPlacedAfterClosingParen(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `colors` DESC';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'a']],
                'columns' => ['id', 'colors'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('a','b')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString(', 0)) DESC', $result);
    }

    public function testTransformSetOrderByEscapesDoubleQuoteInFindInSet(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `status`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'status' => 'say "hi"']],
                'columns' => ['id', 'status'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'status' => new ColumnType(ColumnTypeFamily::STRING, 'SET("say ""hi""","ok")'),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("FIND_IN_SET('say \"hi\"', `status`)", $result);
    }

    public function testTransformSetOrderByRewritesSecondSetColumnInSameTable(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'SELECT * FROM items ORDER BY `priority`';
        $tables = [
            'items' => [
                'rows' => [['id' => 1, 'colors' => 'red', 'priority' => 'low']],
                'columns' => ['id', 'colors', 'priority'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'colors' => new ColumnType(ColumnTypeFamily::STRING, "SET('red','blue')"),
                    'priority' => new ColumnType(ColumnTypeFamily::STRING, "SET('low','medium','high')"),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("FIND_IN_SET('low', `priority`)", $result);
        self::assertStringContainsString("FIND_IN_SET('medium', `priority`)", $result);
        self::assertStringContainsString("FIND_IN_SET('high', `priority`)", $result);
    }

    public function testTransformWithExistingWithClauseHasCommaBeforeExistingCte(): void
    {
        $transformer = new SelectTransformer();
        $sql = 'WITH existing AS (SELECT 1 AS x) SELECT * FROM users, existing';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("`users` AS", $result);
        $usersPos = strpos($result, '`users` AS');
        $existingPos = strpos($result, 'existing AS');
        self::assertNotFalse($usersPos);
        self::assertNotFalse($existingPos);
        $between = substr($result, $usersPos, $existingPos - $usersPos);
        self::assertStringContainsString(',', $between);
    }
}
