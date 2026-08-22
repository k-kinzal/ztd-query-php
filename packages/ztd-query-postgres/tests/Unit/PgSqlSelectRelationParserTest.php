<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser;

#[CoversClass(PgSqlSelectRelationParser::class)]
final class PgSqlSelectRelationParserTest extends TestCase
{
    public function testFindsPostgreSqlRelationsWithoutTableFunctions(): void
    {
        $sql = 'SELECT * FROM generate_series(1, 3) day LEFT JOIN LATERAL (SELECT * FROM ONLY public."orders") totals ON TRUE JOIN audit_log a ON TRUE';

        self::assertSame(
            ['audit_log', 'orders'],
            (new PgSqlSelectRelationParser())->tableNames($sql),
        );
    }

    public function testFromClausesUsePostgreSqlBoundaries(): void
    {
        $parser = new PgSqlSelectRelationParser();

        self::assertSame(
            ['users', 'archived'],
            $parser->fromClauses('SELECT * FROM users FETCH FIRST 1 ROW; SELECT * FROM archived FOR UPDATE'),
        );
    }

    public function testReferencesPreserveOffsetsAndUnqualifyOnlyTargets(): void
    {
        $parser = new PgSqlSelectRelationParser();
        $sql = 'SELECT * FROM public."users" u JOIN logs.audit_log a ON TRUE';

        self::assertSame(['users', 'audit_log'], array_column($parser->references($sql), 'name'));
        self::assertSame(
            'SELECT * FROM "users" u JOIN logs.audit_log a ON TRUE',
            $parser->unqualify($sql, ['users']),
        );
    }

    public function testRejectsMySqlAndLiteralSourceForms(): void
    {
        $parser = new PgSqlSelectRelationParser();

        self::assertSame([], $parser->tableNames('SELECT * FROM `users`'));
        self::assertSame([], $parser->tableNames("SELECT * FROM 'literal'"));
        self::assertSame([], $parser->tableNames('SELECT * FROM VALUES (1)'));
    }
}
