<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlConflictTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class ConflictClauseTest extends TestCase
{
    public function testExtractOnConflictTarget(): void
    {
        $sql = 'INSERT INTO users VALUES (1) ON CONFLICT (id) DO UPDATE SET name = excluded.name WHERE users.active';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertEquals(new \ZtdQuery\Platform\Postgres\PgSqlConflictTarget(specified: true, columns: ['id'], predicate: null, constraint: null), (new \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause())->extractOnConflictTarget($sql));
    }

    public function testExtractOnConflictUpdateColumns(): void
    {
        $sql = 'INSERT INTO users VALUES (1) ON CONFLICT (id) DO UPDATE SET name = excluded.name WHERE users.active';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(['columns' => ['name'], 'values' => ['name' => 'excluded.name']], (new \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause())->extractOnConflictUpdateColumns($sql));
    }

    public function testExtractOnConflictUpdateWhere(): void
    {
        $sql = 'INSERT INTO users VALUES (1) ON CONFLICT (id) DO UPDATE SET name = excluded.name WHERE users.active';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame('users.active', (new \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause())->extractOnConflictUpdateWhere($sql));
    }

    public function testFindOnConflictTargetStart(): void
    {
        $sql = 'INSERT INTO users VALUES (1) ON CONFLICT (id) DO UPDATE SET name = excluded.name WHERE users.active';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(9, \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::findOnConflictTargetStart($tokens));
    }

    public function testFindTopLevelSymbol(): void
    {
        $sql = 'INSERT INTO users VALUES (1) ON CONFLICT (id) DO UPDATE SET name = excluded.name WHERE users.active';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(11, \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::findTopLevelSymbol($tokens, 10, ')'));
    }

    public function testFindTopLevelKeyword(): void
    {
        $sql = 'INSERT INTO users VALUES (1) ON CONFLICT (id) DO UPDATE SET name = excluded.name WHERE users.active';
        $stream = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();

        self::assertSame(12, \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::findTopLevelKeyword($tokens, 0, 'DO'));
    }
    public function testNamedConstraintReadsTheArbiterName(): void
    {
        $sql = 'ON CONSTRAINT users_pkey DO NOTHING';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $target = (new \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause())->namedConstraint($tokens, 0);
        self::assertNotNull($target);
        self::assertSame('users_pkey', $target->constraint);
        self::assertTrue($target->specified);
    }

    public function testIndexColumnsRejectsExpressionsAndPreservesNames(): void
    {
        $clause = new \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause();
        self::assertSame(['id', 'Email'], $clause->indexColumns('id, "Email"'));
        self::assertNull($clause->indexColumns('lower(email)'));
    }

    public function testIndexTargetCarriesThePartialPredicate(): void
    {
        $sql = '(id) WHERE active DO UPDATE SET id = excluded.id';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $target = (new \ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause())->indexTarget($sql, $tokens, 2, ['id']);
        self::assertNotNull($target);
        self::assertSame(['id'], $target->columns);
        self::assertSame('active', $target->predicate);
    }
}
