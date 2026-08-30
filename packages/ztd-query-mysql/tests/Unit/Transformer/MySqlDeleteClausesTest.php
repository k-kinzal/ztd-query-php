<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Parse\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Transformer\MySqlDeleteClauses;

#[CoversClass(MySqlDeleteClauses::class)]
#[UsesClass(MySqlComponentSql::class)]
#[UsesClass(DmlWhereClauseExtractor::class)]
final class MySqlDeleteClausesTest extends TestCase
{
    public function testOfCarriesOverEverythingTheDeleteWroteAfterItsTarget(): void
    {
        $sql = 'DELETE FROM users WHERE id = 1 ORDER BY id LIMIT 2';
        $statement = (new Parser($sql))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        $clauses = (new MySqlDeleteClauses(new MySqlComponentSql()))->of($statement, $sql);

        self::assertSame(' FROM users  WHERE id = 1 ORDER BY id ASC LIMIT 0, 2', $clauses);
    }

    public function testRelationClauseWritesTheUsingClauseWhereFromWouldGo(): void
    {
        $sql = 'DELETE u FROM users AS u USING orders AS o';
        $statement = (new Parser($sql))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        $clause = (new MySqlDeleteClauses(new MySqlComponentSql()))->relationClause($statement, $sql);

        self::assertStringContainsString('orders', $clause);
    }

    public function testRelationClauseWritesNothingWhereTheStatementReadsFromNothing(): void
    {
        self::assertSame(
            '',
            (new MySqlDeleteClauses(new MySqlComponentSql()))->relationClause(new DeleteStatement(), 'DELETE'),
        );
    }

    public function testJoinClauseWritesTheJoinsTheStatementMakes(): void
    {
        $sql = 'DELETE u FROM users AS u JOIN orders AS o ON o.user_id = u.id';
        $statement = (new Parser($sql))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        $clause = (new MySqlDeleteClauses(new MySqlComponentSql()))->joinClause($statement, $sql);

        self::assertStringContainsString('JOIN', $clause);
    }

    public function testJoinClauseWritesNothingWhereTheStatementMakesNone(): void
    {
        self::assertSame(
            '',
            (new MySqlDeleteClauses(new MySqlComponentSql()))->joinClause(new DeleteStatement(), 'DELETE'),
        );
    }

    public function testWhereClauseWritesTheConditionTheStatementRemovesRowsUnder(): void
    {
        $clauses = new MySqlDeleteClauses(new MySqlComponentSql());

        self::assertSame(
            [' WHERE id = 1', ''],
            [$clauses->whereClause('DELETE FROM users WHERE id = 1'), $clauses->whereClause('DELETE FROM users')],
        );
    }

    public function testOrderClauseWritesNothingWhereTheStatementWritesNoOrder(): void
    {
        self::assertSame(
            '',
            (new MySqlDeleteClauses(new MySqlComponentSql()))->orderClause(new DeleteStatement(), 'DELETE'),
        );
    }

    public function testLimitClauseWritesHowManyRowsTheStatementRemovesAtMost(): void
    {
        $sql = 'DELETE FROM users LIMIT 3';
        $statement = (new Parser($sql))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);
        $clauses = new MySqlDeleteClauses(new MySqlComponentSql());

        self::assertSame(
            [' LIMIT 0, 3', ''],
            [$clauses->limitClause($statement, $sql), $clauses->limitClause(new DeleteStatement(), 'DELETE')],
        );
    }
}
