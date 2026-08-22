<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\UpdateAssignmentExtractor;
use ZtdQuery\Platform\MySql\UpdateSourceExtractor;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(DmlWhereClauseExtractor::class)]
#[UsesClass(UpdateAssignmentExtractor::class)]
#[UsesClass(UpdateSourceExtractor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class UpdateTransformerTest extends TestCase
{
    public function testBuildProjectionPreservesDerivedJoinSource(): void
    {
        $sql = 'UPDATE summary s JOIN (SELECT category, COUNT(*) AS cnt, MIN(price) AS mn FROM products GROUP BY category) p ON s.category = p.category SET s.min_price = p.mn, s.item_count = p.cnt';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());

        $result = $transformer->buildProjection(
            $statement,
            ['id', 'category', 'min_price', 'item_count'],
            ['id'],
            [],
            ['p.mn', 'p.cnt'],
            null,
            (new UpdateSourceExtractor())->extract($sql),
        );

        self::assertSame(
            'SELECT p.mn AS `min_price`, p.cnt AS `item_count`, `s`.`id`, `s`.`category`, `s`.`id` AS `__ztd_original_id` FROM summary s JOIN (SELECT category, COUNT(*) AS cnt, MIN(price) AS mn FROM products GROUP BY category) p ON s.category = p.category',
            $result['sql'],
        );
    }

    public function testTransformPreservesIntroducedHexLiteral(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'data' => [
                'rows' => [['id' => 1, 'payload' => 'Hello']],
                'columns' => ['id', 'payload'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform("UPDATE data SET payload = X'576F726C64' WHERE id = 1", $tables);

        self::assertStringContainsString("X'576F726C64' AS `payload`", $result);
        self::assertStringNotContainsString('SELECT X AS `payload`', $result);
    }

    public function testTransformPreservesIntroducedLiteralsForEveryUpdateTarget(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'data' => [
                'rows' => [['id' => 1, 'payload' => 'old']],
                'columns' => ['id', 'payload'],
                'columnTypes' => [],
                'primaryKeys' => ['id'],
            ],
            'audit' => [
                'rows' => [['id' => 1, 'bits' => '0']],
                'columns' => ['id', 'bits'],
                'columnTypes' => [],
                'primaryKeys' => ['id'],
            ],
        ];

        $result = $transformer->transform(
            "UPDATE data d, audit a SET d.payload = X'00', a.bits = B'01' WHERE d.id = a.id",
            $tables,
        );

        self::assertStringContainsString("X'00' AS `__ztd_multi_0_value_1`", $result);
        self::assertStringContainsString("B'01' AS `__ztd_multi_1_value_1`", $result);
    }

    public function testTransformPreservesIntervalUnitInAssignment(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'tasks' => [
                'rows' => [['id' => 1, 'created_at' => '2025-01-01 10:00:00', 'due_at' => null]],
                'columns' => ['id', 'created_at', 'due_at'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform(
            'UPDATE tasks SET due_at = created_at + INTERVAL 30 DAY WHERE id = 1',
            $tables,
        );

        self::assertStringContainsString('created_at + INTERVAL 30 DAY AS `due_at`', $result);
    }

    public function testTransformPreservesCaseWhereExpression(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            't' => [
                'rows' => [['id' => 1, 'score' => 85], ['id' => 2, 'score' => 60]],
                'columns' => ['id', 'score'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform(
            'UPDATE t SET score = 0 WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1',
            $tables,
        );

        self::assertStringContainsString('WHERE CASE WHEN score > 80 THEN 1 ELSE 0 END = 1', $result);
    }

    public function testBuildUpdateSelectUsesAliasAndColumns(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertStringContainsString("SELECT 'Bob' AS `name`", $result['sql']);
        self::assertStringContainsString("`u`.`id`", $result['sql']);
        self::assertStringContainsString("FROM `users` AS u", $result['sql']);
        self::assertStringContainsString("WHERE id = 1", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertCount(1, $result['tables']);
    }

    public function testBuildUpdateSelectWithQualifiedColumnName(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE products SET `products`.`name` = 'Widget' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name', 'price']);

        self::assertStringContainsString("AS `name`", $result['sql']);
        self::assertStringNotContainsString("``name``", $result['sql']);
        self::assertStringContainsString("`products`.`id`", $result['sql']);
        self::assertStringContainsString("`products`.`price`", $result['sql']);
    }

    public function testBuildUpdateSelectWithUnqualifiedColumnName(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE products SET name = 'Widget' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name', 'price']);

        self::assertStringContainsString("AS `name`", $result['sql']);
        self::assertStringNotContainsString("``", $result['sql']);
        self::assertStringContainsString("`products`.`id`", $result['sql']);
        self::assertStringContainsString("`products`.`price`", $result['sql']);
    }

    public function testBuildUpdateSelectWithBacktickedUnqualifiedColumn(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE products SET `name` = 'Widget' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertStringContainsString("AS `name`", $result['sql']);
        self::assertStringNotContainsString("``name``", $result['sql']);
    }

    public function testBuildUpdateSelectWithJoin(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id SET u.name = 'Updated' WHERE o.amount > 100";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertStringContainsString("SELECT 'Updated' AS `name`", $result['sql']);
        self::assertStringContainsString("`u`.`id`", $result['sql']);
        self::assertStringContainsString("FROM `users` AS u", $result['sql']);
        self::assertStringContainsString("JOIN `orders` AS o", $result['sql']);
        self::assertStringContainsString("ON u.id = o.user_id", $result['sql']);
        self::assertStringContainsString("WHERE o.amount > 100", $result['sql']);
    }

    public function testBuildUpdateSelectWithLeftJoin(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u LEFT JOIN orders o ON u.id = o.user_id SET u.status = 'inactive' WHERE o.id IS NULL";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'status']);

        self::assertStringContainsString("SELECT 'inactive' AS `status`", $result['sql']);
        self::assertStringContainsString("LEFT JOIN `orders` AS o", $result['sql']);
        self::assertStringContainsString("ON u.id = o.user_id", $result['sql']);
    }

    public function testBuildUpdateSelectWithMultipleJoins(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id JOIN products p ON o.product_id = p.id SET u.name = 'VIP' WHERE p.price > 1000";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertStringContainsString("JOIN `orders` AS o", $result['sql']);
        self::assertStringContainsString("JOIN `products` AS p", $result['sql']);
        self::assertStringContainsString("ON u.id = o.user_id", $result['sql']);
        self::assertStringContainsString("ON o.product_id = p.id", $result['sql']);
    }

    public function testBuildMultiTableUpdate(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u, orders o SET u.name = 'Updated', o.status = 'processed' WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertStringContainsString("FROM `users`", $result['sql']);
        self::assertStringContainsString("`orders`", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertCount(2, $result['tables']);
        self::assertArrayHasKey('users', $result['tables']);
        self::assertArrayHasKey('orders', $result['tables']);
    }

    public function testTransformThrowsForNonUpdateStatement(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());

        self::expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        $transformer->transform('SELECT 1', []);
    }

    public function testTransformSimpleUpdateReturnsSelect(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
                'primaryKeys' => ['id'],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString("'Bob' AS `name`", $result);
        self::assertStringContainsString('`users`.`id` AS `__ztd_original_id`', $result);
    }

    public function testTransformUpdateWithPartitionThrows(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());

        self::expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        self::expectExceptionMessage('PARTITION');
        $transformer->transform("UPDATE users PARTITION (p0) SET name = 'Bob' WHERE id = 1", []);
    }

    public function testBuildUpdateSelectWithOrderByAndLimit(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id > 5 ORDER BY id LIMIT 10";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertStringContainsString('ORDER BY', $result['sql']);
        self::assertStringContainsString('LIMIT', $result['sql']);
    }

    public function testBuildUpdateSelectWithOnlyOrderBySelectsRowsBeforeProjecting(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $parser = new Parser("UPDATE users SET name = 'Bob' ORDER BY id");
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertSame(
            "SELECT 'Bob' AS `name`, `users`.`id` FROM (SELECT * FROM `users` ORDER BY id ASC) AS `users`",
            $result['sql'],
        );
    }

    public function testBuildUpdateSelectWithOnlyLimitSelectsRowsBeforeProjecting(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $parser = new Parser("UPDATE users SET name = 'Bob' LIMIT 2");
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertSame(
            "SELECT 'Bob' AS `name`, `users`.`id` FROM (SELECT * FROM `users` LIMIT 0, 2) AS `users`",
            $result['sql'],
        );
    }

    public function testBuildUpdateSelectWithJoinAndLimitKeepsJoinInResultSelect(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $parser = new Parser("UPDATE users JOIN orders ON users.id = orders.user_id SET users.name = 'Bob' LIMIT 2");
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);

        self::assertSame(
            "SELECT 'Bob' AS `name`, `users`.`id` FROM `users` JOIN `orders` ON users.id = orders.user_id LIMIT 0, 2",
            $result['sql'],
        );
    }

    public function testBuildUpdateSelectWithEmptyColumnsUsesWildcard(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, []);

        self::assertStringContainsString("'Bob' AS `name`", $result['sql']);
    }

    public function testTransformUpdateWithShadowDataAddsCte(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('WITH', $result);
    }

    public function testBuildUpdateSelectExactFormat(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 WHERE b = 2";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b']);
        self::assertStringStartsWith('SELECT 1 AS `a`, `t`.`b` FROM `t`', $result['sql']);
        self::assertStringContainsString('WHERE b = 2', $result['sql']);
    }

    public function testBuildUpdateSelectWithoutColumnsIncludesSetValuesOnly(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, []);
        self::assertStringStartsWith('SELECT 1 AS `a` FROM `t`', $result['sql']);
    }

    public function testBuildUpdateSelectAliasClause(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u SET u.name = 'X' WHERE u.id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('FROM `users` AS u', $result['sql']);
        self::assertStringContainsString("`u`.`id`", $result['sql']);
    }

    public function testBuildMultiTableUpdateAdditionalTableAlias(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u, orders o SET u.name = 'X' WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString(', `orders` AS o', $result['sql']);
        self::assertSame('users', $result['table']);
    }

    public function testBuildUpdateSelectCoveredColNotDuplicated(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1, b = 2 WHERE c = 3";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b', 'c']);
        self::assertSame(1, substr_count($result['sql'], 'AS `a`'));
        self::assertSame(1, substr_count($result['sql'], 'AS `b`'));
        self::assertStringContainsString('`t`.`c`', $result['sql']);
    }

    public function testBuildUpdateWithJoinUsingClause(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users JOIN orders USING (id) SET users.name = 'X' WHERE orders.amount > 100";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('JOIN', $result['sql']);
    }

    public function testBuildUpdateSelectOrderByFormatContainsKeyword(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 ORDER BY b DESC LIMIT 5";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b']);
        self::assertStringContainsString('ORDER BY', $result['sql']);
        self::assertStringContainsString('b DESC', $result['sql']);
        self::assertStringContainsString('LIMIT', $result['sql']);
    }

    public function testTransformWithLowercasePartitionThrows(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());

        self::expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        self::expectExceptionMessage('PARTITION');
        $transformer->transform("UPDATE users partition (p0) SET name = 'Bob' WHERE id = 1", []);
    }

    public function testBuildProjectionOrderByExactFormat(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 ORDER BY b ASC LIMIT 3";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b']);
        self::assertStringContainsString(' ORDER BY b ASC', $result['sql']);
        self::assertStringContainsString(' LIMIT ', $result['sql']);
        self::assertMatchesRegularExpression('/ORDER BY b ASC LIMIT/', $result['sql']);
    }

    public function testBuildProjectionColumnsNotEmpty(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $tables = ['t' => ['columns' => ['a', 'b'], 'rows' => [], 'columnTypes' => []]];
        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `a`', $result);
        self::assertStringContainsString('`t`.`b`', $result);
    }

    public function testBuildProjectionCoveredColIsNotDuplicatedWhenTrue(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 99 WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b']);
        self::assertSame(1, substr_count($result['sql'], '`a`'));
    }

    public function testBuildProjectionMultiTableReturnsAllTables(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u, orders o SET u.name = 'X' WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertSame('u', $result['tables']['users']['alias']);
        self::assertSame('o', $result['tables']['orders']['alias']);
    }

    public function testBuildProjectionJoinOnClause(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id SET u.name = 'X'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('ON u.id = o.user_id', $result['sql']);
        self::assertStringContainsString('JOIN `orders`', $result['sql']);
    }

    public function testBuildProjectionJoinTypeIsIncluded(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u LEFT JOIN orders o ON u.id = o.user_id SET u.name = 'X'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('LEFT JOIN', $result['sql']);
    }

    public function testBuildProjectionWithNoWhereReturnsNoWhereClause(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a']);
        self::assertStringNotContainsString('WHERE', $result['sql']);
    }

    public function testBuildProjectionWithNoOrderByReturnsNoOrderClause(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a']);
        self::assertStringNotContainsString('ORDER BY', $result['sql']);
        self::assertStringNotContainsString('LIMIT', $result['sql']);
    }

    public function testBuildProjectionCoveredColIsTrueNotFalse(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 99 WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b']);
        self::assertStringNotContainsString('`t`.`a`', $result['sql']);
        self::assertStringContainsString('99 AS `a`', $result['sql']);
        self::assertStringContainsString('`t`.`b`', $result['sql']);
    }

    public function testBuildProjectionLimitContainsActualValue(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 ORDER BY b LIMIT 5";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a', 'b']);
        self::assertMatchesRegularExpression('/LIMIT\s+\d/', $result['sql']);
    }

    public function testBuildProjectionSingleTableNoAdditionalTables(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET a = 1 WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['a']);
        $fromPos = strpos($result['sql'], 'FROM `t`');
        self::assertNotFalse($fromPos);
        $afterFrom = substr($result['sql'], $fromPos + strlen('FROM `t`'));
        self::assertStringNotContainsString('`t`', $afterFrom);
    }

    public function testBuildProjectionMultiTableAdditionalTableSameNameNoAlias(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users, orders SET users.name = 'X' WHERE users.id = orders.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('`orders`', $result['sql']);
    }

    public function testBuildProjectionJoinUsingClauseExactFormat(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users JOIN orders USING (id) SET users.name = 'X'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('USING', $result['sql']);
        self::assertStringContainsString('(', $result['sql']);
        self::assertStringContainsString(')', $result['sql']);
    }

    public function testBuildProjectionJoinOnConditionFormatted(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id SET u.name = 'X' WHERE o.amount > 50";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString(' ON ', $result['sql']);
        self::assertStringContainsString('u.id = o.user_id', $result['sql']);
    }

    public function testBuildProjectionJoinAliasPresentInOutput(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id SET u.name = 'X'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('AS o', $result['sql']);
    }

    public function testBuildProjectionExactSqlForSimpleUpdate(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertSame("SELECT 'Bob' AS `name`, `users`.`id` FROM `users` WHERE id = 1", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'users']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForUpdateWithAlias(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u SET u.name = 'Bob' WHERE u.id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertSame("SELECT 'Bob' AS `name`, `u`.`id` FROM `users` AS u WHERE u.id = 1", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForMultiTableUpdate(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u, orders o SET u.name = 'X', o.status = 'done' WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertSame("SELECT 'X' AS `name`, 'done' AS `status`, `u`.`id` FROM `users` AS u, `orders` AS o WHERE u.id = o.user_id", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u'], 'orders' => ['alias' => 'o']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForUpdateWithOrderByAndLimit(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'X' ORDER BY id LIMIT 5";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertSame("SELECT 'X' AS `name`, `users`.`id` FROM (SELECT * FROM `users` ORDER BY id ASC LIMIT 0, 5) AS `users`", $result['sql']);
    }

    public function testBuildProjectionExactSqlForUpdateWithJoin(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u JOIN orders o ON u.id = o.user_id SET u.name = 'X' WHERE o.status = 'pending'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertSame("SELECT 'X' AS `name`, `u`.`id` FROM `users` AS u JOIN `orders` AS o ON u.id = o.user_id WHERE o.status = 'pending'", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u']], $result['tables']);
    }

    public function testTransformExactOutputWithShadowData(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertSame(
            'WITH `users` AS (SELECT CAST(1 AS SIGNED) AS `id`, CAST(' . "'" . 'Alice' . "'" . ' AS CHAR) AS `name`)' . "\n"
            . "SELECT 'Bob' AS `name`, `users`.`id` FROM users WHERE id = 1",
            $result
        );
    }

    public function testTransformExactOutputWithEmptyRows(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertSame(
            'WITH `users` AS (SELECT CAST(NULL AS CHAR) AS `id`, CAST(NULL AS CHAR) AS `name` FROM DUAL WHERE 0)' . "\n"
            . "SELECT 'Bob' AS `name`, `users`.`id` FROM users WHERE id = 1",
            $result
        );
    }

    public function testBuildProjectionWithNoColumnsUsesWildcard(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users SET name = 'Bob' WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, []);
        self::assertSame("SELECT 'Bob' AS `name` FROM `users` WHERE id = 1", $result['sql']);
    }

    public function testBuildProjectionJoinUsingExactColumnInOutput(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users JOIN orders USING (user_id) SET users.name = 'X'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['user_id', 'name']);
        self::assertStringContainsString('USING (user_id)', $result['sql']);
    }

    public function testBuildProjectionMultiTableNoAliasSameNameNotDuplicated(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users, orders SET users.name = 'X' WHERE users.id = orders.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringNotContainsString('AS orders', $result['sql']);
        self::assertStringContainsString('`orders`', $result['sql']);
    }

    public function testBuildProjectionMultiTableWithAliasDifferentFromTableName(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users u, orders o SET u.name = 'Y' WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('`orders` AS o', $result['sql']);
    }

    public function testBuildProjectionExactSqlForUpdateWithJoinUsing(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users JOIN orders USING (id) SET users.name = 'Z' WHERE orders.amount > 10";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'name']);
        self::assertStringContainsString('JOIN `orders`', $result['sql']);
        self::assertStringContainsString('USING (id)', $result['sql']);
        self::assertStringContainsString("'Z' AS `name`", $result['sql']);
        self::assertStringContainsString('WHERE orders.amount > 10', $result['sql']);
    }

    public function testBuildProjectionExactSqlForUpdateWithLeftJoinOn(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t1 LEFT JOIN t2 ON t1.id = t2.t1_id SET t1.a = 99";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['id', 'a']);
        self::assertStringContainsString('LEFT JOIN `t2`', $result['sql']);
        self::assertStringContainsString('ON t1.id = t2.t1_id', $result['sql']);
        self::assertStringContainsString('99 AS `a`', $result['sql']);
    }

    public function testTransformExactOutputForUpdateWithJoinUsing(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE users JOIN orders USING (id) SET users.name = 'Z'";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('USING (id)', $result);
        self::assertStringContainsString("'Z' AS `name`", $result);
    }

    public function testBuildProjectionCoveredColBlocksColumnFromRemainder(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "UPDATE t SET x = 10, y = 20 WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);

        $result = $transformer->buildProjection($statement, ['x', 'y', 'z']);
        self::assertStringContainsString('10 AS `x`', $result['sql']);
        self::assertStringContainsString('20 AS `y`', $result['sql']);
        self::assertStringContainsString('`t`.`z`', $result['sql']);
        self::assertStringNotContainsString('`t`.`x`', $result['sql']);
        self::assertStringNotContainsString('`t`.`y`', $result['sql']);
    }

    public function testTransformMultiUpdateProjectsValuesAndOriginalIdentityPerTarget(): void
    {
        $transformer = new UpdateTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'users' => [
                'rows' => [['id' => 2, 'name' => 'Bob']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
                'primaryKeys' => ['id'],
            ],
            'orders' => [
                'rows' => [['order_id' => 9, 'user_id' => 2]],
                'columns' => ['order_id', 'user_id'],
                'columnTypes' => [],
                'primaryKeys' => ['order_id'],
            ],
        ];

        $result = $transformer->transform(
            "UPDATE users u, orders o SET `u`.`name` = 'Updated', `o`.`user_id` = 7 WHERE u.id = o.user_id",
            $tables,
        );

        self::assertStringContainsString(
            "SELECT `u`.`id` AS `__ztd_multi_0_value_0`, 'Updated' AS `__ztd_multi_0_value_1`, `u`.`id` AS `__ztd_multi_0_identity_0`",
            $result,
        );
        self::assertStringContainsString(
            '`o`.`order_id` AS `__ztd_multi_1_value_0`, 7 AS `__ztd_multi_1_value_1`, `o`.`order_id` AS `__ztd_multi_1_identity_0`',
            $result,
        );
    }
}
