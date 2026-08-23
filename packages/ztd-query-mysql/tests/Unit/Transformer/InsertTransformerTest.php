<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Schema\IdentityGenerationStrategy;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(InsertTransformer::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlSelectRelationParser::class)]
#[UsesClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(InsertSelectSourceExtractor::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[UsesClass(InsertSelectRenderer::class)]
#[UsesClass(MySqlSelectListAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class InsertTransformerTest extends TestCase
{
    public function testProjectsUpsertExpressionUsingCandidateKeys(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
                'candidateKeys' => ['PRIMARY' => ['id']],
            ],
        ];

        $result = $transformer->transform(
            "INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = VALUES(name)",
            $tables,
        );

        self::assertStringContainsString('`__ztd_incoming`.`name`', $result);
        self::assertStringContainsString('__ztd_upsert_value_0', $result);
        self::assertStringNotContainsString('VALUES(', $result);
    }

    public function testTransformInsertFromCompoundSelectPreservesEveryBranch(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'combined' => [
                'rows' => [],
                'columns' => ['name', 'amount'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform(
            'INSERT INTO combined (name, amount) SELECT name, amount FROM archive UNION ALL SELECT name, amount FROM current',
            $tables,
        );

        self::assertStringContainsString(
            'FROM archive UNION ALL SELECT name, amount FROM current',
            $result,
        );
        self::assertStringContainsString('`__ztd_insert_0` AS `name`', $result);
        self::assertStringContainsString('`__ztd_insert_1` AS `amount`', $result);
    }

    public function testUsesInjectedCastRendererAndColumnTypes(): void
    {
        $castRenderer = self::createStub(CastRenderer::class);
        $castRenderer->method('renderCast')->willReturn('CUSTOM_CAST');
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer(), $castRenderer);
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => ['id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT')],
            ],
        ];

        $result = $transformer->transform('INSERT INTO users (id) VALUES (1)', $tables);

        self::assertStringContainsString('SELECT CUSTOM_CAST AS `id`', $result);
    }

    public function testTransformInsertWithValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice')";
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

    public function testTransformThrowsForNonInsertStatement(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Expected INSERT statement');
        $transformer->transform('SELECT 1', []);
    }

    public function testTransformInsertWithSetSyntax(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users SET id = 1, name = 'Alice'";
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

    public function testTransformInsertMultipleRowsUsesUnionAll(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob')";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('UNION ALL', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformInsertResolvesColumnsFromTablesContext(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users VALUES (1, 'Alice')";
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

    public function testTransformInsertThrowsWhenNoColumnsResolvable(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users VALUES (1, 'Alice')";

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $transformer->transform($sql, []);
    }

    public function testTransformInsertSelectSubquery(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id, name) SELECT id, name FROM users WHERE active = 0";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformInsertSetProjectsCorrectColumns(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users SET id = 1, name = 'Alice'";
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

    public function testTransformInsertValuesProjectsColumnAliases(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("1 AS `id`", $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformInsertValuesExactSelectFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t (a, b) VALUES (1, 2)";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('SELECT 1 AS `a`, 2 AS `b`', $result);
    }

    public function testTransformInsertMultipleRowsExactUnionFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t (a) VALUES (1), (2)";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 1 AS `a` UNION ALL SELECT 2 AS `a`', $result);
    }

    public function testTransformInsertFromSelectWithWhereAndGroupBy(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (cnt) SELECT COUNT(*) FROM users WHERE active = 1 GROUP BY dept";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['cnt'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `cnt`', $result);
        self::assertStringContainsString('FROM users', $result);
        self::assertStringContainsString('WHERE active = 1', $result);
        self::assertStringContainsString('GROUP BY dept', $result);
    }

    public function testTransformInsertFromSelectWithOrderByAndLimit(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id) SELECT id FROM users ORDER BY id LIMIT 10";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('FROM users', $result);
        self::assertStringContainsString('ORDER BY', $result);
        self::assertStringContainsString('id', $result);
        self::assertStringContainsString('LIMIT', $result);
    }

    public function testTransformInsertFromSelectWithHaving(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (cnt) SELECT COUNT(*) FROM users GROUP BY dept HAVING COUNT(*) > 1";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['cnt'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `cnt`', $result);
        self::assertStringContainsString('FROM users', $result);
        self::assertStringContainsString('GROUP BY dept', $result);
        self::assertStringContainsString('HAVING COUNT(*) > 1', $result);
    }

    public function testTransformInsertFromSelectWithAliasedExpression(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (total) SELECT SUM(amount) AS total FROM orders";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['total'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `total`', $result);
    }

    public function testTransformInsertFromSelectWithCaseExpression(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO statuses (status) SELECT CASE WHEN active = 1 THEN 'yes' ELSE 'no' END FROM users";
        $tables = [
            'statuses' => [
                'rows' => [],
                'columns' => ['status'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `status`', $result);
        self::assertStringContainsString('CASE', $result);
    }

    public function testTransformInsertFromSelectExactOrderByLimitFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id) SELECT id FROM users ORDER BY id DESC LIMIT 5";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('ORDER BY id DESC', $result);
        self::assertStringContainsString('LIMIT', $result);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('FROM', $result);
    }

    public function testTransformInsertFromSelectSubqueryWrapping(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id, name) SELECT id, name FROM users WHERE active = 0";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`__ztd_insert_source`', $result);
        self::assertStringContainsString('AS (SELECT id AS `__ztd_insert_0`, name AS `__ztd_insert_1` FROM users WHERE active = 0)', $result);
        self::assertStringContainsString('SELECT `__ztd_insert_0` AS `id`', $result);
    }

    public function testTransformInsertFromSelectUsesTableColumnsWhenColumnListIsOmitted(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['archive' => [
            'rows' => [],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
        ]];

        $result = $transformer->transform('INSERT INTO archive SELECT id, name FROM users', $tables);

        self::assertSame(
            'WITH `__ztd_insert_source` (`__ztd_insert_0`, `__ztd_insert_1`) AS (SELECT id AS `__ztd_insert_0`, name AS `__ztd_insert_1` FROM users) SELECT `__ztd_insert_0` AS `id`, `__ztd_insert_1` AS `name` FROM `__ztd_insert_source`',
            $result,
        );
    }

    public function testTransformInsertFromSelectColumnCountMismatchThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id, name, email) SELECT id, name FROM users";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name', 'email'],
                'columnTypes' => [],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $transformer->transform($sql, $tables);
    }

    public function testTransformInsertSetSelectFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users SET id = 1, name = 'Alice'";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('SELECT', $result);
        self::assertStringContainsString('1 AS `id`', $result);
    }

    public function testTransformInsertValuesCountMismatchThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1)";
        $tables = [];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(0);
        $transformer->transform($sql, $tables);
    }

    public function testTransformInsertFromSelectWithWhereClauseExactFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id) SELECT id FROM users WHERE id > 10";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString(' WHERE id > 10', $result);
    }

    public function testTransformInsertFromSelectWithGroupByExactFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (cnt) SELECT COUNT(*) FROM users GROUP BY status";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['cnt'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString(' GROUP BY status', $result);
    }

    public function testTransformInsertFromSelectWithHavingExactFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (cnt) SELECT COUNT(*) FROM users GROUP BY dept HAVING COUNT(*) > 5";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['cnt'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString(' HAVING COUNT(*) > 5', $result);
    }

    public function testTransformInsertFromSelectLimitValueIncluded(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id) SELECT id FROM users LIMIT 7";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertMatchesRegularExpression('/LIMIT\s+\d/', $result);
    }

    public function testTransformInsertFromSelectWrappedSubqueryHasSelectKeyword(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (id) SELECT id FROM users WHERE id > 0";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('__ztd_insert_source', $result);
    }

    public function testTransformInsertFromSelectGroupByValueIncluded(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO archive (cnt) SELECT COUNT(*) FROM users GROUP BY department";
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['cnt'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('GROUP BY', $result);
        self::assertStringContainsString('department', $result);
    }

    public function testTransformInsertFromSelectWrappedExactOutput(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (a, b) SELECT x, y FROM src WHERE x > 0';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame('WITH `__ztd_insert_source` (`__ztd_insert_0`, `__ztd_insert_1`) AS (SELECT x AS `__ztd_insert_0`, y AS `__ztd_insert_1` FROM src WHERE x > 0) SELECT `__ztd_insert_0` AS `a`, `__ztd_insert_1` AS `b` FROM `__ztd_insert_source`', $result);
    }

    public function testTransformInsertFromSelectAliasedExpressionDropsAlias(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (val) SELECT x AS alias FROM src';
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('`__ztd_insert_0`', $result);
        self::assertStringContainsString('__ztd_insert_source', $result);
        self::assertStringNotContainsString('AS `alias`', $result);
    }

    public function testTransformInsertSetExactSqlOutput(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t SET name = 'Alice', age = 30";
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['name', 'age'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("'Alice' AS `name`", $result);
        self::assertStringContainsString('30 AS `age`', $result);
    }

    public function testTransformInsertValuesTrimsWhitespace(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t (a) VALUES ( 'hello' )";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 'hello' AS `a`", $result);
    }

    public function testTransformInsertFromSelectWithAllClauses(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (val) SELECT cnt FROM src WHERE cnt > 0 GROUP BY dept HAVING cnt > 1 ORDER BY cnt LIMIT 10';
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('WHERE', $result);
        self::assertStringContainsString('GROUP BY', $result);
        self::assertStringContainsString('HAVING', $result);
        self::assertStringContainsString('ORDER BY', $result);
        self::assertStringContainsString('LIMIT', $result);
    }

    public function testTransformInsertColumnsResolvedFromTableContext(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t VALUES (1, 'x')";
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

    public function testTransformInsertNoColumnsNoContextThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t VALUES (1, 'x')";

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $transformer->transform($sql, []);
    }

    public function testTransformInsertFromSelectWithFromAndWhereExact(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (a) SELECT x FROM src WHERE x > 0';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame(
            'WITH `__ztd_insert_source` (`__ztd_insert_0`) AS (SELECT x AS `__ztd_insert_0` FROM src WHERE x > 0) SELECT `__ztd_insert_0` AS `a` FROM `__ztd_insert_source`',
            $result
        );
    }

    public function testTransformInsertFromSelectMultipleColumnsFromMultipleTables(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (a, b) SELECT s1.x, s2.y FROM s1, s2 WHERE s1.id = s2.id';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FROM s1, s2', $result);
        self::assertStringContainsString('WHERE s1.id = s2.id', $result);
        self::assertStringContainsString('AS `a`', $result);
        self::assertStringContainsString('AS `b`', $result);
    }

    public function testTransformInsertFromSelectWithAllClausesExactOutput(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (cnt) SELECT COUNT(*) FROM src WHERE active = 1 GROUP BY dept HAVING COUNT(*) > 1 ORDER BY dept ASC LIMIT 10';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('FROM src', $result);
        self::assertStringContainsString('WHERE active = 1', $result);
        self::assertStringContainsString('GROUP BY dept', $result);
        self::assertStringContainsString('HAVING COUNT(*) > 1', $result);
        self::assertStringContainsString('ORDER BY', $result);
        self::assertStringContainsString('LIMIT', $result);
    }

    public function testTransformInsertFromSelectAliasedExprDropsAliasExactOutput(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = 'INSERT INTO t (val) SELECT x AS myalias FROM src';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame(
            'WITH `__ztd_insert_source` (`__ztd_insert_0`) AS (SELECT x AS `__ztd_insert_0` FROM src) SELECT `__ztd_insert_0` AS `val` FROM `__ztd_insert_source`',
            $result
        );
    }

    public function testTransformInsertValuesWithWhitespaceTrimsCorrectly(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new InsertTransformer($parser, $selectTransformer);

        $sql = "INSERT INTO t (a, b) VALUES (  1  ,  'hello'  )";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString("1 AS `a`", $result);
        self::assertStringContainsString("'hello' AS `b`", $result);
    }

    public function testTransformProjectsOmittedAndExplicitDefaults(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [],
            'columns' => ['id', 'status', 'note'],
            'columnTypes' => [],
            'columnDefaults' => ['status' => "'active'"],
        ]];

        $result = $transformer->transform('INSERT INTO users (id, status) VALUES (1, DEFAULT)', $tables);

        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'active' AS `status`", $result);
        self::assertStringContainsString('NULL AS `note`', $result);
    }

    public function testTransformDefaultOnlyInsertProjectsCompleteRow(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['settings' => [
            'rows' => [],
            'columns' => ['enabled', 'label'],
            'columnTypes' => [],
            'columnDefaults' => ['enabled' => '1', 'label' => "'new'"],
        ]];

        $result = $transformer->transform('INSERT INTO settings () VALUES ()', $tables);

        self::assertStringContainsString('1 AS `enabled`', $result);
        self::assertStringContainsString("'new' AS `label`", $result);
    }

    public function testTransformProjectsDefaultWhenIntoIsOmitted(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['settings' => [
            'rows' => [],
            'columns' => ['enabled'],
            'columnTypes' => [],
            'columnDefaults' => ['enabled' => '1'],
        ]];

        $result = $transformer->transform(
            "INSERT # lead\n HIGH_PRIORITY -- priority\n IGNORE settings VALUE(/* value */ DEFAULT)",
            $tables,
        );

        self::assertStringContainsString('1 AS `enabled`', $result);
    }

    public function testTransformNormalizesSparseTableColumnKeys(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [],
            'columns' => [2 => 'id', 5 => 'name'],
            'columnTypes' => [],
        ]];

        $result = $transformer->transform("INSERT INTO users (id, name) VALUES (1, 'Alice')", $tables);

        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformAllocatesMonotonicAutoIncrementValues(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::MaxValue],
        ]];

        $first = $transformer->transform("INSERT INTO users (name) VALUES ('Alice'), ('Bob')", $tables);
        $transformer->commitRewriteState();
        $second = $transformer->transform("INSERT INTO users (name) VALUES ('Carol')", $tables);

        self::assertStringContainsString('1 AS `id`', $first);
        self::assertStringContainsString('2 AS `id`', $first);
        self::assertStringContainsString('3 AS `id`', $second);
    }

    public function testUncommittedTransformDoesNotConsumeAutoIncrementValue(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::MaxValue],
        ]];

        $preview = $transformer->transform("INSERT INTO users (name) VALUES ('preview')", $tables);
        $executed = $transformer->transform("INSERT INTO users (name) VALUES ('executed')", $tables);

        self::assertStringContainsString('1 AS `id`', $preview);
        self::assertStringContainsString('1 AS `id`', $executed);
    }

    public function testTransformAllocatesAfterExistingAutoIncrementRows(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [['id' => 7, 'name' => 'Existing']],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::MaxValue],
        ]];

        $result = $transformer->transform("INSERT INTO users (name) VALUES ('Alice')", $tables);

        self::assertStringContainsString('8 AS `id`', $result);
    }

    public function testTransformInsertSelectAllocatesOnlyOmittedIdentityColumns(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [['id' => 7, 'name' => 'Existing']],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::MaxValue],
        ]];

        $explicit = $transformer->transform('INSERT INTO users (id, name) SELECT id, name FROM source', $tables);
        $generated = $transformer->transform('INSERT INTO users (name) SELECT name FROM source', $tables);

        self::assertStringContainsString('8 + ROW_NUMBER() OVER () - 1 AS `id`', $generated);
        self::assertStringContainsString('`__ztd_insert_0` AS `id`', $explicit);
        self::assertStringNotContainsString('ROW_NUMBER()', $explicit);
    }

    public function testExplicitValuesIdentityDoesNotConsumeGeneratedIdentity(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::MaxValue],
        ]];

        $transformer->transform("INSERT INTO users (id, name) VALUES (42, 'explicit')", $tables);
        $transformer->commitRewriteState();
        $generated = $transformer->transform("INSERT INTO users (name) VALUES ('generated')", $tables);

        self::assertStringContainsString('1 AS `id`', $generated);
    }

    public function testExplicitSetIdentityDoesNotConsumeGeneratedIdentity(): void
    {
        $transformer = new InsertTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => [
            'rows' => [],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::MaxValue],
        ]];

        $transformer->transform("INSERT INTO users SET id = 42, name = 'explicit'", $tables);
        $transformer->commitRewriteState();
        $generated = $transformer->transform("INSERT INTO users SET name = 'generated'", $tables);

        self::assertStringContainsString('1 AS `id`', $generated);
    }
}
