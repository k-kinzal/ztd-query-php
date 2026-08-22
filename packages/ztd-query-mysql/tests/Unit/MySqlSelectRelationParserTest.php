<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;

#[CoversClass(MySqlSelectRelationParser::class)]
final class MySqlSelectRelationParserTest extends TestCase
{
    public function testFindsPhysicalRelationsAcrossMySqlSelectScopes(): void
    {
        $sql = 'SELECT * FROM (SELECT * FROM app.`users` UNION ALL SELECT * FROM archived_users) p JOIN JSON_TABLE(p.data, \'$[*]\' COLUMNS(id INT PATH \'$.id\')) j ON TRUE JOIN audit_log a ON TRUE';

        self::assertSame(
            ['audit_log', 'users', 'archived_users'],
            (new MySqlSelectRelationParser())->tableNames($sql),
        );
    }

    public function testFromClausesUseMySqlBoundaries(): void
    {
        $parser = new MySqlSelectRelationParser();

        self::assertSame(
            ['users', 'archived'],
            $parser->fromClauses('SELECT * FROM users PROCEDURE ANALYSE(); SELECT * FROM archived LOCK IN SHARE MODE'),
        );
    }

    public function testReferencesPreserveOffsetsAndUnqualifyOnlyTargets(): void
    {
        $parser = new MySqlSelectRelationParser();
        $sql = 'SELECT * FROM app.`users` u JOIN logs.audit_log a ON TRUE';

        self::assertSame(['users', 'audit_log'], array_column($parser->references($sql), 'name'));
        self::assertSame(
            'SELECT * FROM `users` u JOIN logs.audit_log a ON TRUE',
            $parser->unqualify($sql, ['users']),
        );
    }

    public function testRejectsNonMySqlSourceForms(): void
    {
        $parser = new MySqlSelectRelationParser();

        self::assertSame([], $parser->tableNames("SELECT * FROM 'literal'"));
        self::assertSame([], $parser->tableNames('SELECT * FROM VALUES (1)'));
        self::assertSame([], $parser->tableNames('SELECT * FROM +invalid'));
    }
}
