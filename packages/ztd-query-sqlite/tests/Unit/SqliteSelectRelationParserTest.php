<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqliteSelectRelationParser::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenKind::class)]
#[UsesClass(SqlTokenStream::class)]
final class SqliteSelectRelationParserTest extends TestCase
{
    public function testFindsSqliteQuotedAndNestedRelations(): void
    {
        $sql = 'SELECT * FROM (SELECT * FROM [catalog].[a]]b] UNION SELECT * FROM `archived`) p JOIN json_each(p.data) j ON TRUE JOIN audit_log a ON TRUE';

        self::assertSame(
            ['audit_log', 'a]b', 'archived'],
            (new SqliteSelectRelationParser())->tableNames($sql),
        );
    }

    public function testFromClausesUseSqliteBoundaries(): void
    {
        $parser = new SqliteSelectRelationParser();

        self::assertSame(
            ['users', 'archived'],
            $parser->fromClauses('SELECT * FROM users LIMIT 1; SELECT * FROM archived RETURNING id'),
        );
    }

    public function testReferencesPreserveOffsetsAndUnqualifyOnlyTargets(): void
    {
        $parser = new SqliteSelectRelationParser();
        $sql = 'SELECT * FROM main.[users] u JOIN logs.audit_log a ON TRUE';

        self::assertSame(['users', 'audit_log'], array_column($parser->references($sql), 'name'));
        self::assertSame(
            'SELECT * FROM [users] u JOIN logs.audit_log a ON TRUE',
            $parser->unqualify($sql, ['users']),
        );
    }

    public function testRejectsLiteralAndProducerSourceForms(): void
    {
        $parser = new SqliteSelectRelationParser();

        self::assertSame([], $parser->tableNames("SELECT * FROM 'literal'"));
        self::assertSame([], $parser->tableNames('SELECT * FROM VALUES (1)'));
        self::assertSame([], $parser->tableNames('SELECT * FROM +invalid'));
    }
}
