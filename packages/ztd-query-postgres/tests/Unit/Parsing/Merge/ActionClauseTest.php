<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\BranchTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\RelationTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::class)]
final class ActionClauseTest extends TestCase
{
    public function testParseClause(): void
    {
        self::assertEquals(new \ZtdQuery\Platform\Postgres\PgSqlMergeClause(matchKind: \ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::Matched, conditionSql: null, actionKind: \ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::Delete, assignments: [], insertColumns: [], insertValues: []), (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseClause('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', 'WHEN MATCHED THEN DELETE'));

        self::assertEquals(new \ZtdQuery\Platform\Postgres\PgSqlMergeClause(matchKind: \ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::NotMatched, conditionSql: null, actionKind: \ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::DoNothing, assignments: [], insertColumns: [], insertValues: []), (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseClause('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', 'WHEN NOT MATCHED THEN DO NOTHING'));

        self::assertEquals(new \ZtdQuery\Platform\Postgres\PgSqlMergeClause(matchKind: \ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::Matched, conditionSql: 's.active', actionKind: \ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::Update, assignments: ['name' => 's.name'], insertColumns: [], insertValues: []), (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseClause('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', 'WHEN MATCHED AND s.active THEN UPDATE SET name = s.name'));

        self::assertEquals(new \ZtdQuery\Platform\Postgres\PgSqlMergeClause(matchKind: \ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::NotMatched, conditionSql: null, actionKind: \ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::Insert, assignments: [], insertColumns: ['id', 'name'], insertValues: ['s.id', 's.name']), (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseClause('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)'));
    }

    public function testParseAssignments(): void
    {
        $sql = 'UPDATE SET id = source.id, name = source.name';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(['id' => 'source.id', 'name' => 'source.name'], (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseAssignments('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', $sql, $tokens));
    }

    public function testParseInsert(): void
    {
        $sql = 'INSERT (id, name) VALUES (s.id, s.name)';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(['columns' => ['id', 'name'], 'values' => ['s.id', 's.name']], (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseInsert('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', $sql, $tokens));

        $sql = 'INSERT DEFAULT VALUES';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(['columns' => [], 'values' => []], (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parseInsert('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', $sql, $tokens));
    }

    public function testParenthesizedList(): void
    {
        $sql = '(id, coalesce(name, \'x\'))';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(['items' => ['id', 'coalesce(name, \'x\')'], 'next' => 10], (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->parenthesizedList('MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)', $sql, $tokens, 0));
    }
    public function testActionMatchesTheBranchKind(): void
    {
        $clause = (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->action('MERGE test', ' DELETE', \ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::Matched, 'active');
        self::assertSame(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::Delete, $clause->actionKind);
        self::assertSame('active', $clause->conditionSql);
    }

    public function testInsertColumnsUnquotesOrderedIdentifiers(): void
    {
        self::assertSame(['id', 'Display Name'], (new \ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause())->insertColumns('MERGE test', ['id', '"Display Name"']));
    }
}
