<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DropTableCommand::class)]
#[Small]
final class DropTableCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new DropTableCommand())->clearsDiagnostics());
    }

    public function testExecuteDropsTheTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE DATABASE e; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT); CREATE TABLE e.v (a INT)');

        $reply = $session->query('DROP TABLE t, e.v')[0];
        $tables = $session->query('SHOW TABLES')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 0], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $tables);
        self::assertSame([['u']], $tables->rows);
        self::assertNull($session->instance->dictionary->table('e', 'v'));
    }

    public function testExecuteDropsTheTablesThatExistIfExistsWithANoteForEachMissingOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE b (a INT); CREATE TABLE c (a INT)');

        $reply = $session->query('DROP TABLE IF EXISTS nope, b, zz')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $tables = $session->query('SHOW TABLES')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->warnings);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1051', "Unknown table 'd.nope'"], ['Note', '1051', "Unknown table 'd.zz'"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $tables);
        self::assertSame([['c']], $tables->rows);
    }

    public function testExecuteCommitsTheOpenTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');

        $session->query('BEGIN; INSERT INTO t VALUES (1); DROP TABLE u; ROLLBACK');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testExecuteTruncatesATableAndRestartsItsAutoIncrement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY); INSERT INTO t VALUES (), ()');

        $reply = $session->query('TRUNCATE TABLE t')[0];
        $session->query('INSERT INTO t VALUES ()');
        $result = $session->query('SELECT id FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 0], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testExecuteRefusesToTruncateAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nope' doesn't exist");

        $session->query('TRUNCATE TABLE nope');
    }

    public function testExecuteNamesEveryMissingTableAndDropsNone(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE w (a INT)');
        $error = $session->run('DROP TABLE nope, w, nodb.x')[0];
        $tables = $session->query('SHOW TABLES')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1051, '42S02', "Unknown table 'p.nope,nodb.x'"], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
        self::assertInstanceOf(ResultSet::class, $tables);
        self::assertSame([['w']], $tables->rows);
    }
}
