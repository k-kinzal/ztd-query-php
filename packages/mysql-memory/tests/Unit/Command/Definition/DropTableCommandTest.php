<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\DropTableCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

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

    public function testReferencedRefusesToDropATableAnotherTableReferences(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3730);
        $this->expectExceptionMessage("Cannot drop table 'p' referenced by a foreign key constraint 'c_ibfk_1' on table 'c'.");

        $session->query('DROP TABLE IF EXISTS p, nope');
    }

    public function testReferencedLetsAStatementDropTheReferencingTableToo(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id)); DROP TABLE p, c');

        $result1 = $session->query('SHOW TABLES')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([], $result1->rows);
    }

    public function testTruncatedRefusesToEmptyATableAnotherTableReferences(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1701);
        $this->expectExceptionMessage('Cannot truncate a table referenced in a foreign key constraint (`d`.`c`, CONSTRAINT `c_ibfk_1`)');

        $session->query('TRUNCATE TABLE p');
    }

    public function testExecuteDropsTheTemporaryTableBeforeTheBaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1); CREATE TEMPORARY TABLE t (b INT); DROP TABLE t');

        $result2 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['1']], $result2->rows);
    }

    public function testReferencedRefusesWithRowIsReferencedInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1217);
        $this->expectExceptionMessage('Cannot delete or update a parent row: a foreign key constraint fails');

        $session->query('DROP TABLE p');
    }

    public function testTruncateEmptiesTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');

        $reply = (new DropTableCommand())->truncate(new TruncateTable(new QualifiedName(new Name('t'))), $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testTruncateRefusesAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        (new DropTableCommand())->truncate(new TruncateTable(new QualifiedName(new Name('nope'))), $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
    }

    public function testTruncateRetainsThePreviousUpdateTimeIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->updated = 123;
        $session->query('TRUNCATE TABLE t');

        self::assertSame(123, $table->updated);
        self::assertSame([], $table->data->rows);
    }
}
