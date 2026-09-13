<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\BranchTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\RelationTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::class)]
final class StatementPartsTest extends TestCase
{
    public function testTargetSeparatesRelationAliasAndUsingDelimiter(): void
    {
        $sql = 'MERGE INTO users u USING source s ON u.id = s.id WHEN MATCHED THEN DELETE';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $parts = new \ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts();
        $target = $parts->target($sql, $sql, $tokens);
        self::assertSame('users', $target['name']);
        self::assertSame('users', $target['sql']);
        self::assertSame('u', $target['alias']);
        self::assertSame('USING', $target['using']->text);
    }

    public function testJoinSeparatesSourceConditionAndBranches(): void
    {
        $sql = 'MERGE INTO users u USING source s ON u.id = s.id WHEN MATCHED THEN DELETE';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $parts = new \ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts();
        $target = $parts->target($sql, $sql, $tokens);
        $join = $parts->join($sql, $sql, $tokens, $target['using']);
        self::assertSame('source s', $join['source']);
        self::assertSame('u.id = s.id', $join['condition']);
        self::assertCount(1, $join['when']);
        self::assertSame('WHEN', $join['when'][0]->text);
    }

    public function testClausesParsesEachBranchAction(): void
    {
        $sql = 'MERGE INTO users u USING source s ON u.id = s.id WHEN MATCHED THEN DELETE';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();
        $parts = new \ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts();
        $when = (new \ZtdQuery\Platform\Postgres\Parsing\Merge\BranchTokens())->mergeWhenTokens($tokens);
        $clauses = $parts->clauses($sql, $sql, $when);
        self::assertCount(1, $clauses);
        self::assertSame(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::Delete, $clauses[0]->actionKind);
    }
}
