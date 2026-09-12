<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Update\TargetProjection;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(TargetProjection::class)]
final class TargetProjectionTest extends TestCase
{
    public function testTargetsFromContexts(): void
    {
        $targets = (new TargetProjection())->targetsFromContexts(['users' => ['alias' => 'u'], 'missing' => ['alias' => 'm']], ['users' => ['columns' => ['id', 'name'], 'primaryKeys' => ['id']]]);
        self::assertCount(1, $targets);
        self::assertSame('users', $targets[0]->tableName());
        self::assertSame(['id', 'name'], $targets[0]->columns());
        self::assertSame(['id'], $targets[0]->primaryKeys());
    }

    public function testMultiTableSelectColumns(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u SET name = 7 WHERE id = 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        $target = new \ZtdQuery\Shadow\Mutation\MultiTableMutationTarget('users', ['id', 'name'], ['id']);
        $codec = new \ZtdQuery\Shadow\Mutation\MultiTableMutationRow();
        self::assertSame(['`u`.`id` AS `' . $codec->valueColumn(0, 0) . '`', '9 AS `' . $codec->valueColumn(0, 1) . '`', '`u`.`id` AS `' . $codec->identityColumn(0, 0) . '`'], (new TargetProjection())->multiTableSelectColumns($statement, ['users' => ['alias' => 'u']], [$target], ['9']));
    }

    public function testAssignmentsByTable(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u, posts p SET u.name = 7, p.title = 8'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertSame(['users' => ['name' => '9'], 'posts' => ['title' => '8']], (new TargetProjection())->assignmentsByTable($statement, ['users' => ['alias' => 'u'], 'posts' => ['alias' => 'p']], ['9']));
    }

    public function testUnquoteIdentifier(): void
    {
        self::assertSame('a`b', TargetProjection::unquoteIdentifier('`a``b`'));
        self::assertSame('name', TargetProjection::unquoteIdentifier('name'));
    }

    public function testBuildAdditionalTables(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u, posts p SET u.name = 7, p.title = 8'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertSame(', `posts` AS p', (new TargetProjection())->buildAdditionalTables($statement));
    }

    public function testBuildJoinClause(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u JOIN posts p ON p.uid = u.id SET u.name = 7'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertSame(' JOIN `posts` AS p ON p.uid = u.id', (new TargetProjection())->buildJoinClause($statement));
    }

    public function testTargetTable(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u SET name = 7 WHERE id = 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertSame('users', (new TargetProjection())->targetTable($statement, $statement->build()));
    }

}
