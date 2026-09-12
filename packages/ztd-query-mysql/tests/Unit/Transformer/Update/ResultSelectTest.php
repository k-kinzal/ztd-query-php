<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Update\ResultSelect;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\TargetProjection::class)]
#[CoversClass(ResultSelect::class)]
final class ResultSelectTest extends TestCase
{
    public function testBuildProjection(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u SET name = 7 WHERE id = 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertSame(['sql' => 'SELECT 7 AS `name`, `u`.`id` FROM `users` AS u WHERE id = 1', 'table' => 'users', 'tables' => ['users' => ['alias' => 'u']]], (new ResultSelect())->buildProjection($statement, ['id', 'name']));
    }

    public function testTargetTables(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u, posts p SET u.name = 7, p.title = 8'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertSame(['users' => ['alias' => 'u'], 'posts' => ['alias' => 'p']], (new ResultSelect())->targetTables($statement));
    }

    public function testSelectColumns(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u SET name = 7 WHERE id = 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        $identity = new \ZtdQuery\Shadow\Mutation\MutationRowIdentity();
        self::assertSame(['9 AS `name`', '`u`.`id`', '`u`.`id` AS `' . $identity->column('id') . '`'], (new ResultSelect())->selectColumns($statement, ['id', 'name'], ['id'], 'u', ['9']));
    }

    public function testBuildQuery(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users u SET name = 7 WHERE id = 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        self::assertNotNull($statement->tables);
        self::assertSame('SELECT * FROM custom_source WHERE id > 2', (new ResultSelect())->buildQuery($statement, [], $statement->tables[0], 'users', 'u', 'id > 2', 'custom_source'));
    }

}
