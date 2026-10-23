<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Instance;
use MySqlMemory\Session\State\StatementKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementKind::class)]
#[Small]
final class StatementKindTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'select' => ['SELECT 1', 'Com_select'];
        yield 'insert rows' => ['INSERT INTO d.t VALUES (1)', 'Com_insert'];
        yield 'replace rows' => ['REPLACE INTO d.t VALUES (1)', 'Com_replace'];
        yield 'insert query' => ['INSERT INTO d.t SELECT 1', 'Com_insert_select'];
        yield 'replace query' => ['REPLACE INTO d.t SELECT 1', 'Com_replace_select'];
        yield 'single update' => ['UPDATE d.t SET a=1', 'Com_update'];
        yield 'joined update' => ['UPDATE d.t JOIN d.u ON t.a=u.a SET t.a=1', 'Com_update_multi'];
        yield 'explained update' => ['EXPLAIN UPDATE d.t SET a=1', 'Com_update'];
        yield 'warning count' => ['SHOW COUNT(*) WARNINGS', 'Com_select'];
        yield 'status' => ['SHOW SESSION STATUS', 'Com_show_status'];
        yield 'alter view' => ['ALTER VIEW d.v AS SELECT 1', 'Com_create_view'];
        yield 'describe' => ['DESCRIBE d.t', 'Com_show_fields'];
        yield 'create view listing' => ['SHOW CREATE VIEW d.v', 'Com_show_create_table'];
        yield 'alter procedure' => ["ALTER PROCEDURE d.p COMMENT 'x'", 'Com_alter_procedure'];
        yield 'alter function' => ["ALTER FUNCTION d.f COMMENT 'x'", 'Com_alter_function'];
        yield 'drop procedure' => ['DROP PROCEDURE d.p', 'Com_drop_procedure'];
        yield 'drop function' => ['DROP FUNCTION d.f', 'Com_drop_function'];
        yield 'drop trigger' => ['DROP TRIGGER d.tr', 'Com_drop_trigger'];
        yield 'drop event' => ['DROP EVENT d.e', 'Com_drop_event'];
        yield 'default role' => ['SET DEFAULT ROLE NONE TO a', 'Com_alter_user_default_role'];
        yield 'alter default role' => ['ALTER USER a DEFAULT ROLE NONE', 'Com_alter_user_default_role'];
        yield 'reset' => ['RESET BINARY LOGS AND GTIDS', 'Com_reset'];
        yield 'diagnostics' => ['GET DIAGNOSTICS @a=NUMBER', 'Com_get_diagnostics'];
    }

    #[DataProvider('providerStatements')]
    public function testOfDistinguishesTheExecutionCommand(string $sql, string $counter): void
    {
        $statement = (new Instance())->connect()->analyze($sql)->statement;
        self::assertSame($counter, StatementKind::of($statement));
    }
}
