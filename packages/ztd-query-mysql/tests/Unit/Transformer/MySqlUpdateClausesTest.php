<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Transformer\MySqlUpdateClauses;

#[CoversClass(MySqlUpdateClauses::class)]
#[UsesClass(MySqlComponentSql::class)]
final class MySqlUpdateClausesTest extends TestCase
{
    public function testWhereClauseWritesTheConditionTheStatementWrote(): void
    {
        $statement = (new Parser('UPDATE users SET name = 1 WHERE id = 2'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);
        $clauses = new MySqlUpdateClauses();

        self::assertSame(
            [' WHERE id = 2', ''],
            [$clauses->whereClause($statement, 'id = 2'), $clauses->whereClause($statement, '')],
        );
    }

    public function testWhereClauseFallsBackToWhatTheParserReadWhereNothingCouldBeRead(): void
    {
        $statement = (new Parser('UPDATE users SET name = 1 WHERE id = 2'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);

        self::assertStringContainsString('WHERE', (new MySqlUpdateClauses())->whereClause($statement, null));
    }

    public function testOrderClauseWritesTheOrderTheStatementChangesRowsIn(): void
    {
        $statement = (new Parser('UPDATE users SET name = 1 ORDER BY id'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);

        self::assertSame(' ORDER BY id ASC', (new MySqlUpdateClauses())->orderClause($statement));
    }

    public function testOrderClauseWritesNothingWhereTheStatementWritesNoOrder(): void
    {
        self::assertSame('', (new MySqlUpdateClauses())->orderClause(new UpdateStatement()));
    }

    public function testLimitClauseWritesHowManyRowsTheStatementChangesAtMost(): void
    {
        $statement = (new Parser('UPDATE users SET name = 1 LIMIT 3'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);

        self::assertSame(
            [' LIMIT 0, 3', ''],
            [
                (new MySqlUpdateClauses())->limitClause($statement),
                (new MySqlUpdateClauses())->limitClause(new UpdateStatement()),
            ],
        );
    }
}
