<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteTransformer::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
final class DeleteTransformerTest extends TestCase
{
    public function testBuildDeleteWithJoinAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);

        self::assertSame('users', $result['table']);
        self::assertStringContainsString('SELECT `u`.`id` AS `id`, `u`.`name` AS `name`', $result['sql']);
        self::assertStringContainsString('FROM users', $result['sql']);
        self::assertStringContainsString('AS `u`', $result['sql']);
        self::assertStringContainsString('JOIN orders', $result['sql']);
        self::assertStringContainsString('AS `o`', $result['sql']);
        self::assertStringContainsString("WHERE o.status = 'canceled'", $result['sql']);
    }

    public function testBuildDeleteWithPartitionIsBlocked(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users PARTITION (p0) WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('PARTITION clause');

        $transformer->buildProjection($statement, $sql, ['id']);
    }

    public function testBuildDeleteWithMultipleTargetsReturnsAllTables(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);

        self::assertSame('users', $result['table']);
        self::assertArrayHasKey('tables', $result);
        self::assertCount(2, $result['tables']);
        self::assertArrayHasKey('users', $result['tables']);
        self::assertArrayHasKey('orders', $result['tables']);
        self::assertSame('u', $result['tables']['users']['alias']);
        self::assertSame('o', $result['tables']['orders']['alias']);
    }

    public function testBuildDeleteWithSingleTargetReturnsSingleTable(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);

        self::assertSame('users', $result['table']);
        self::assertArrayHasKey('tables', $result);
        self::assertCount(1, $result['tables']);
        self::assertArrayHasKey('users', $result['tables']);
    }

    public function testBuildDeleteWithUsingSyntaxMultiTable(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u USING users u, orders o WHERE u.id = o.user_id AND o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);

        self::assertSame('users', $result['table']);
        self::assertArrayHasKey('tables', $result);
    }

    public function testTransformThrowsForNonDeleteStatement(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());

        self::expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        $transformer->transform('SELECT 1', []);
    }

    public function testTransformSimpleDeleteReturnsSelect(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
        $tables = [
            'users' => [
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

    public function testBuildDeleteWithWhereClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);

        self::assertStringContainsString('WHERE id = 1', $result['sql']);
        self::assertStringContainsString('AS `id`', $result['sql']);
        self::assertStringContainsString('AS `name`', $result['sql']);
    }

    public function testBuildDeleteWithOrderByAndLimit(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users ORDER BY id LIMIT 10";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);

        self::assertStringContainsString('ORDER BY', $result['sql']);
        self::assertStringContainsString('LIMIT', $result['sql']);
    }

    public function testBuildDeleteWithNoColumnsUsesWildcard(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);

        self::assertStringContainsString('`users`.*', $result['sql']);
    }

    public function testTransformDeleteWithShadowDataAddsCte(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('WITH', $result);
        self::assertStringContainsString('`users`', $result);
    }

    public function testBuildDeleteProjectionFromClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringContainsString('FROM', $result['sql']);
        self::assertStringContainsString('SELECT', $result['sql']);
        self::assertSame('users', $result['table']);
    }

    public function testBuildDeleteProjectionIncludesJoinClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u JOIN orders o ON u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertStringContainsString('JOIN', $result['sql']);
        self::assertStringContainsString('FROM', $result['sql']);
    }

    public function testBuildDeleteProjectionSelectListFormat(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);
        self::assertStringStartsWith('SELECT `users`.`id` AS `id`, `users`.`name` AS `name`', $result['sql']);
    }

    public function testBuildDeleteTargetFromJoinAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE o FROM users u JOIN orders o ON u.id = o.user_id WHERE u.active = 0";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('orders', $result['table']);
    }

    public function testBuildDeleteWithFromAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users u WHERE u.id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('users', $result['table']);
        self::assertStringContainsString('`u`.`id` AS `id`', $result['sql']);
    }

    public function testBuildMultiDeleteResolvesAliasFromUsing(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u USING users u, orders o WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('users', $result['table']);
    }

    public function testBuildDeleteMultiTargetResolvesFromJoin(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE u.active = 0";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('users', $result['table']);
        self::assertArrayHasKey('users', $result['tables']);
        self::assertArrayHasKey('orders', $result['tables']);
    }

    public function testTransformDeleteWithShadowDataProducesCteSelect(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('WITH', $result);
        self::assertStringContainsString('`users` AS', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
        self::assertStringContainsString('WHERE id = 1', $result);
    }

    public function testBuildDeleteWithJoinAndOrderByLimitClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id > 5 ORDER BY id LIMIT 10";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringContainsString('ORDER BY', $result['sql']);
        self::assertStringContainsString('id', $result['sql']);
        self::assertStringContainsString('LIMIT', $result['sql']);
        self::assertStringContainsString('WHERE id > 5', $result['sql']);
    }

    public function testBuildDeleteWithLowercasePartitionThrows(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users partition (p0) WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('PARTITION');
        $transformer->buildProjection($statement, $sql, ['id']);
    }

    public function testBuildDeleteOrderByExactFormat(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users ORDER BY id ASC LIMIT 5";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringContainsString(' ORDER BY id ASC', $result['sql']);
        self::assertStringContainsString(' LIMIT ', $result['sql']);
    }

    public function testBuildDeleteWithMultipleColumnsSelectFormat(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id > 0";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name', 'email']);
        self::assertStringContainsString('`users`.`id` AS `id`', $result['sql']);
        self::assertStringContainsString('`users`.`name` AS `name`', $result['sql']);
        self::assertStringContainsString('`users`.`email` AS `email`', $result['sql']);
    }

    public function testBuildDeleteTargetFromJoinWhenAliasNotInFrom(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.amount < 10";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'amount']);
        self::assertSame('orders', $result['table']);
        self::assertStringContainsString('`o`.`id` AS `id`', $result['sql']);
        self::assertStringContainsString('`o`.`amount` AS `amount`', $result['sql']);
    }

    public function testBuildDeleteWithNoWhereClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringNotContainsString('WHERE', $result['sql']);
    }

    public function testBuildDeleteWithNoOrderByClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringNotContainsString('ORDER BY', $result['sql']);
        self::assertStringNotContainsString('LIMIT', $result['sql']);
    }

    public function testBuildDeleteResolvedTablesContainsAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('u', $result['tables']['users']['alias']);
        self::assertSame('o', $result['tables']['orders']['alias']);
    }

    public function testBuildDeleteSingleTargetAliasMatchesTable(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('users', $result['tables']['users']['alias']);
    }

    public function testTransformDeleteColumnsFromTablesArg(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE id = 1";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'email'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `email`', $result);
    }

    public function testTransformDeleteWithNoColumnsUsesWildcard(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM unknown_table WHERE id = 1";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('.*', $result);
    }

    public function testBuildDeleteWithUsingClauseOverridesFrom(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u USING users u, orders o WHERE u.id = o.user_id AND o.amount < 5";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringContainsString('FROM', $result['sql']);
        self::assertStringContainsString('WHERE', $result['sql']);
    }

    public function testBuildDeleteLimitClauseContainsActualValue(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users LIMIT 10";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertMatchesRegularExpression('/LIMIT\s+\d/', $result['sql']);
    }

    public function testBuildDeleteFromClauseContainsTableName(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users WHERE active = 0";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringContainsString('FROM users', $result['sql']);
    }

    public function testBuildDeleteJoinClauseContainsJoinTableName(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u JOIN orders o ON u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertStringContainsString('JOIN', $result['sql']);
        self::assertStringContainsString('orders', $result['sql']);
    }

    public function testBuildDeleteResolveAliasFromUsingNotFromOrJoin(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u USING users u, orders o WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('users', $result['table']);
        self::assertArrayHasKey('users', $result['tables']);
    }

    public function testBuildDeleteMultiTargetResolvesSecondAliasFromJoin(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'old'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('u', $result['tables']['users']['alias']);
        self::assertSame('o', $result['tables']['orders']['alias']);
        self::assertSame('users', $result['table']);
    }

    public function testBuildDeleteOrderByExactValueIncluded(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE FROM users ORDER BY id DESC LIMIT 3";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertStringContainsString('ORDER BY id DESC', $result['sql']);
        self::assertMatchesRegularExpression('/LIMIT\s+\d/', $result['sql']);
    }

    public function testBuildProjectionExactSqlForSimpleDelete(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);
        self::assertSame('SELECT `users`.`id` AS `id`, `users`.`name` AS `name` FROM users  WHERE id = 1', $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'users']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForDeleteWithJoinAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u FROM users u JOIN orders o ON u.id = o.user_id WHERE o.status = 'canceled'";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);
        self::assertSame("SELECT `u`.`id` AS `id`, `u`.`name` AS `name` FROM users AS `u` JOIN orders AS `o` ON u.id = o.user_id  WHERE o.status = 'canceled'", $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForMultiTargetDelete(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE u, o FROM users u JOIN orders o ON u.id = o.user_id';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('SELECT `u`.* FROM users AS `u` JOIN orders AS `o` ON u.id = o.user_id ', $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u'], 'orders' => ['alias' => 'o']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForDeleteWithFromAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users u WHERE u.id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('SELECT `u`.`id` AS `id` FROM users AS `u`  WHERE u.id = 1', $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForDeleteWithUsingSyntax(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE u FROM users u USING users u, orders o WHERE u.id = o.user_id';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('SELECT `u`.`id` AS `id` FROM users AS `u`, orders AS `o`  WHERE u.id = o.user_id', $result['sql']);
        self::assertSame('users', $result['table']);
        self::assertSame(['users' => ['alias' => 'u']], $result['tables']);
    }

    public function testBuildProjectionExactSqlForDeleteWithOrderByAndLimit(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users ORDER BY id DESC LIMIT 3';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('SELECT `users`.`id` AS `id` FROM users  ORDER BY id DESC LIMIT 0, 3', $result['sql']);
    }

    public function testBuildProjectionExactSqlForDeleteWithNoColumns(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('SELECT `users`.* FROM users  WHERE id = 1', $result['sql']);
    }

    public function testTransformExactOutputWithShadowData(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
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
            . 'SELECT `users`.`id` AS `id`, `users`.`name` AS `name` FROM users  WHERE id = 1',
            $result
        );
    }

    public function testTransformExactOutputWithEmptyRows(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
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
            . 'SELECT `users`.`id` AS `id`, `users`.`name` AS `name` FROM users  WHERE id = 1',
            $result
        );
    }

    public function testTransformExactOutputWildcardWhenNoTableContext(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';

        $result = $transformer->transform($sql, []);
        self::assertSame('SELECT `users`.* FROM users  WHERE id = 1', $result);
    }

    public function testBuildDeleteTargetResolvedFromJoinNotFrom(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE o FROM users u JOIN orders o ON u.id = o.user_id WHERE u.active = 0";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'amount']);
        self::assertSame('orders', $result['table']);
        self::assertSame('SELECT `o`.`id` AS `id`, `o`.`amount` AS `amount` FROM users AS `u` JOIN orders AS `o` ON u.id = o.user_id  WHERE u.active = 0', $result['sql']);
        self::assertSame(['orders' => ['alias' => 'o']], $result['tables']);
    }

    public function testBuildDeleteMultiTargetResolvesFromUsingClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = "DELETE u, o FROM users u USING users u, orders o WHERE u.id = o.user_id";
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('users', $result['table']);
        self::assertArrayHasKey('users', $result['tables']);
        self::assertArrayHasKey('orders', $result['tables']);
        self::assertSame('u', $result['tables']['users']['alias']);
        self::assertSame('o', $result['tables']['orders']['alias']);
    }

    public function testBuildProjectionFromClauseResolvesTargetTableName(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id', 'name']);
        self::assertSame('users', $result['table']);
        self::assertSame('SELECT `users`.`id` AS `id`, `users`.`name` AS `name` FROM users  WHERE id = 1', $result['sql']);
        self::assertSame(['users' => ['alias' => 'users']], $result['tables']);
    }

    public function testBuildProjectionAliasedFromResolvesAlias(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users AS u WHERE u.id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, ['id']);
        self::assertSame('users', $result['table']);
        self::assertStringContainsString('`u`.`id`', $result['sql']);
    }

    public function testBuildProjectionPartitionThrowsRuntimeException(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users PARTITION (p0) WHERE id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('PARTITION');
        $transformer->buildProjection($statement, $sql, []);
    }

    public function testBuildProjectionWithOrderByAndLimit(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE active = 0 ORDER BY id LIMIT 10';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertStringContainsString('ORDER BY', $result['sql']);
        self::assertStringContainsString('LIMIT', $result['sql']);
    }

    public function testBuildProjectionWithJoinClause(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE users FROM users JOIN orders ON users.id = orders.user_id WHERE orders.total = 0';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertStringContainsString('JOIN', $result['sql']);
        self::assertSame('users', $result['table']);
    }

    public function testBuildProjectionEmptyColumnsUsesWildcard(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM users WHERE id = 1';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertStringContainsString('`users`.*', $result['sql']);
    }

    public function testTransformDeleteFromSimpleExactSql(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE FROM items WHERE id = 5';
        $tables = [
            'items' => [
                'rows' => [['id' => 5, 'val' => 'x']],
                'columns' => ['id', 'val'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `val`', $result);
        self::assertStringContainsString('WHERE id = 5', $result);
    }

    public function testTransformDeleteMultiTableFromJoinExactOutput(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE o FROM users u JOIN orders o ON u.id = o.user_id WHERE u.active = 0';
        $tables = [
            'orders' => [
                'rows' => [['id' => 1, 'user_id' => 10]],
                'columns' => ['id', 'user_id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `user_id`', $result);
    }

    public function testBuildProjectionMultiDeleteTargetFromUsingResolvesBothTables(): void
    {
        $transformer = new DeleteTransformer(new MySqlParser(), new SelectTransformer());
        $sql = 'DELETE a FROM t1 a USING t1 a, t2 b WHERE a.id = b.ref_id';
        $parser = new Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);

        $result = $transformer->buildProjection($statement, $sql, []);
        self::assertSame('t1', $result['table']);
        self::assertSame(['t1' => ['alias' => 'a']], $result['tables']);
    }
}
