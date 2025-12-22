<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;

#[CoversClass(ReplaceTransformer::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
final class ReplaceTransformerTest extends TestCase
{
    public function testTransformReplaceWithValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice')";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformThrowsForNonReplaceStatement(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Expected REPLACE statement');
        $transformer->transform('SELECT 1', []);
    }

    public function testTransformReplaceWithSetSyntax(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users SET id = 1, name = 'Alice'";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("1 AS `id`", $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformReplaceMultipleRows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('UNION ALL', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformReplaceResolvesColumnsFromContext(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users VALUES (1, 'Alice')";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformReplaceThrowsWhenNoColumnsResolvable(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users VALUES (1, 'Alice')";

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $transformer->transform($sql, []);
    }

    public function testTransformReplaceProjectsCorrectColumnValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("1 AS `id`", $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformReplaceSelectSubquery(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO archive (id, name) SELECT id, name FROM users";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformReplaceValuesExactSelectFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a, b) VALUES (1, 2)";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('SELECT 1 AS `a`, 2 AS `b`', $result);
    }

    public function testTransformReplaceSetExactFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t SET a = 1, b = 2";
        $tables = ['t' => ['columns' => ['a', 'b'], 'columnTypes' => [], 'rows' => []]];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('1 AS `a`', $result);
        self::assertStringContainsString('2 AS `b`', $result);
    }

    public function testTransformReplaceMultiRowsExactUnionFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a) VALUES (1), (2)";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 1 AS `a` UNION ALL SELECT 2 AS `a`', $result);
    }

    public function testTransformReplaceValuesCountMismatchReturnsInvalidReplace(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a, b) VALUES (1)";
        $tables = [];

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Invalid REPLACE');
        $transformer->transform($sql, $tables);
    }

    public function testTransformReplaceSelectSubqueryPassesThrough(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO archive (id, name) SELECT id, name FROM users WHERE active = 0";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('FROM users', $result);
        self::assertStringContainsString('WHERE active = 0', $result);
    }

    public function testTransformReplaceSetSelectContainsColumnValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t SET a = 10, b = 20";
        $tables = ['t' => ['columns' => ['a', 'b'], 'columnTypes' => [], 'rows' => []]];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('10 AS `a`', $result);
        self::assertStringContainsString('20 AS `b`', $result);
    }

    public function testTransformReplaceValuesExactSqlAssertion(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (id, name) VALUES (1, 'Alice')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 1 AS `id`, 'Alice' AS `name`", $result);
    }

    public function testTransformReplaceSetExactSqlAssertion(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t SET id = 1, name = 'Bob'";
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("1 AS `id`", $result);
        self::assertStringContainsString("'Bob' AS `name`", $result);
    }

    public function testTransformReplaceMultiRowExactSqlAssertion(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (id) VALUES (1), (2), (3)";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame('SELECT 1 AS `id` UNION ALL SELECT 2 AS `id` UNION ALL SELECT 3 AS `id`', $result);
    }

    public function testTransformReplaceColumnsResolvedFromTableContext(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t VALUES (1, 'x')";
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("1 AS `id`", $result);
        self::assertStringContainsString("'x' AS `name`", $result);
    }

    public function testTransformReplaceNoColumnsNoContextThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t VALUES (1, 'x')";

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $transformer->transform($sql, []);
    }

    public function testTransformReplaceValuesCountMismatchReturnsInvalid(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t (id, name) VALUES (1)';

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Invalid REPLACE');
        $transformer->transform($sql, []);
    }

    public function testTransformReplaceTrimsWhitespaceInValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a) VALUES ( 'hello' )";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 'hello' AS `a`", $result);
    }

    public function testTransformReplaceSetExactSqlContainsSelectPrefix(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t SET x = 42";
        $tables = ['t' => ['columns' => ['x'], 'columnTypes' => [], 'rows' => []]];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 42 AS `x`', $result);
    }

    public function testTransformReplaceValuesTrimsWhitespace(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a, b) VALUES ( 1 , 'hello' )";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 1 AS `a`, 'hello' AS `b`", $result);
    }

    public function testTransformReplaceNullDestThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot resolve REPLACE target');
        $transformer->transform('REPLACE SELECT 1', []);
    }
}
