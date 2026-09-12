<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Delete;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Delete\ResultSelect;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\DmlWhereClauseExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\TargetProjection::class)]
#[CoversClass(ResultSelect::class)]
final class ResultSelectTest extends TestCase
{
    public function testBuildProjection(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE FROM users WHERE id = 1 ORDER BY id DESC LIMIT 2'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        self::assertSame(['sql' => 'SELECT `users`.`id` AS `id` FROM users  WHERE id = 1 ORDER BY id DESC LIMIT 0, 2', 'table' => 'users', 'tables' => ['users' => ['alias' => 'users']]], (new ResultSelect())->buildProjection($statement, 'DELETE FROM users WHERE id = 1 ORDER BY id DESC LIMIT 2', ['id']));
    }

    public function testPrimaryTarget(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE u, p FROM users u JOIN posts p ON p.uid = u.id'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        self::assertSame(['table' => 'users', 'alias' => 'u', 'aliases' => ['u' => 'u', 'p' => 'p']], (new ResultSelect())->primaryTarget($statement));
    }

    public function testNamedTarget(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE u, p FROM users u JOIN posts p ON p.uid = u.id'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        $projection = new ResultSelect();
        self::assertSame('users', $projection->namedTarget($statement, 'u'));
        self::assertSame('posts', $projection->namedTarget($statement, 'p'));
        self::assertSame('unknown', $projection->namedTarget($statement, 'missing'));
    }

    public function testSourceClause(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE u, p FROM users u JOIN posts p ON p.uid = u.id'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        self::assertSame(' FROM users AS `u` JOIN posts AS `p` ON p.uid = u.id', (new ResultSelect())->sourceClause($statement));
    }

    public function testSuffix(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE FROM users WHERE id = 1 ORDER BY id DESC LIMIT 2'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        self::assertSame('  WHERE id = 1 ORDER BY id DESC LIMIT 0, 2', (new ResultSelect())->suffix($statement, 'DELETE FROM users WHERE id = 1 ORDER BY id DESC LIMIT 2'));
    }

    public function testSelectList(): void
    {
        $projection = new ResultSelect();
        self::assertSame('`u`.`id` AS `id`, `u`.`name` AS `name`', $projection->selectList('u', ['id', 'name']));
        self::assertSame('`u`.*', $projection->selectList('u', []));
    }

    public function testResolvedTables(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE u, p FROM users u JOIN posts p ON p.uid = u.id'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        self::assertSame(['users' => ['alias' => 'u'], 'posts' => ['alias' => 'p']], (new ResultSelect())->resolvedTables($statement, ['table' => 'users', 'alias' => 'u', 'aliases' => ['u' => 'u', 'p' => 'p']]));
    }

}
