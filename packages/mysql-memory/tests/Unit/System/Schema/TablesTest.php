<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Reading;
use MySqlMemory\System\Schema\Tables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Tables::class)]
#[Small]
final class TablesTest extends TestCase
{
    public function testRowsListsTablesAndViewsWithTheirFigures(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query("CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, n VARCHAR(5), KEY (n)) COMMENT 'parents'");
        $s->query("INSERT INTO p (n) VALUES ('a'), ('b')");
        $s->query('CREATE VIEW v AS SELECT id FROM p');

        $result1 = $s->query("SELECT TABLE_NAME, TABLE_TYPE, ENGINE, VERSION, ROW_FORMAT, TABLE_ROWS, AVG_ROW_LENGTH, DATA_LENGTH, INDEX_LENGTH, AUTO_INCREMENT, TABLE_COLLATION, CREATE_OPTIONS, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['p', 'BASE TABLE', 'InnoDB', '10', 'Dynamic', '2', '8192', '16384', '16384', '3', 'utf8mb4_0900_ai_ci', '', 'parents'], ['v', 'VIEW', null, null, null, null, null, null, null, null, null, null, 'VIEW']], $result1->rows);
    }

    public function testRowsListsTheSystemTables(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');

        $result2 = $s->query("SELECT TABLE_SCHEMA, TABLE_TYPE, ENGINE, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_NAME IN ('SCHEMATA', 'user') ORDER BY TABLE_SCHEMA")[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['information_schema', 'SYSTEM VIEW', null, ''], ['mysql', 'BASE TABLE', 'InnoDB', 'Users and global privileges']], $result2->rows);
    }

    public function testTableAnswersTheFiguresOfABaseTable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $table = $s->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['BASE TABLE', 'InnoDB', 'Dynamic', 0, null], [...array_values(array_slice((new Tables())->table($table, $reading), 0, 1)), ...array_values(array_slice((new Tables())->table($table, $reading), 1, 1)), (new Tables())->table($table, $reading)['ROW_FORMAT'], (new Tables())->table($table, $reading)['TABLE_ROWS'], (new Tables())->table($table, $reading)['AUTO_INCREMENT']]);
    }

    public function testViewAnswersTheCommentView(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);

        self::assertSame(['TABLE_TYPE' => 'VIEW', 'CREATE_TIME' => '2026-01-01 00:00:00', 'TABLE_COMMENT' => 'VIEW'], (new Tables())->view('2026-01-01 00:00:00', $reading));
    }

    public function testSystemAnswersTheCatalogOfASystemTable(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $system = $s->instance->dictionary->system;
        self::assertNotNull($system);
        $reading = new Reading($s->instance, new Connection($s->variables, new Context($s->modes(), $s->diagnostics, $s->variables, 0.0), 'root', 'localhost', $s->id), $system->catalog->tables[0], GrammarRelease::MySql847);
        $user = $system->find('mysql', 'user');
        self::assertNotNull($user);

        self::assertSame(['BASE TABLE', 'InnoDB', 'Dynamic', 'utf8mb3_bin', 'row_format=DYNAMIC stats_persistent=0'], [(new Tables())->system($user, '2026-01-01 00:00:00', $reading)['TABLE_TYPE'], (new Tables())->system($user, '2026-01-01 00:00:00', $reading)['ENGINE'], (new Tables())->system($user, '2026-01-01 00:00:00', $reading)['ROW_FORMAT'], (new Tables())->system($user, '2026-01-01 00:00:00', $reading)['TABLE_COLLATION'], (new Tables())->system($user, '2026-01-01 00:00:00', $reading)['CREATE_OPTIONS']]);
    }
}
