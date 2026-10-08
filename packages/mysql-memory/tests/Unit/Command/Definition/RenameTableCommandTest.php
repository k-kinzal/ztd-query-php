<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\RenameTableCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;

#[CoversClass(RenameTableCommand::class)]
#[Small]
final class RenameTableCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RenameTableCommand())->clearsDiagnostics());
    }

    public function testExecuteSwapsTwoTablesThroughAThirdName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (b INT); INSERT INTO t VALUES (1)');

        $session->query('RENAME TABLE t TO x, u TO t, x TO u');
        $rows = $session->query('SELECT * FROM u')[0];
        $tables = $session->query('SHOW TABLES')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1']], $rows->rows);
        self::assertInstanceOf(ResultSet::class, $tables);
        self::assertSame([['t'], ['u']], $tables->rows);
    }

    public function testExecuteRenamesNothingWhenARenameFails(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $error = $session->run('RENAME TABLE t TO x, nope TO z')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1146, "Table 'd.nope' doesn't exist"], [$error->getCode(), $error->getMessage()]);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }

    public function testExecuteChecksTheNewNameBeforeTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE u (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1050);
        $this->expectExceptionMessage("Table 'u' already exists");

        $session->query('RENAME TABLE nope TO u');
    }

    public function testExecuteDoesNotRenameATemporaryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE tt (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        $session->query('RENAME TABLE tt TO tt2');
    }

    public function testWrittenChecksEveryNewNameBeforeTheDatabase(): void
    {
        $statement = (new Instance())->connect()->analyze('RENAME TABLE t TO u, v TO ` `')->statement;
        self::assertInstanceOf(RenameTable::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1103);

        (new RenameTableCommand())->written($statement, '');
    }

    public function testWrittenRefusesAnUnqualifiedNameWithoutADatabase(): void
    {
        $statement = (new Instance())->connect()->analyze('RENAME TABLE d.t TO u')->statement;
        self::assertInstanceOf(RenameTable::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new RenameTableCommand())->written($statement, '');
    }

    public function testValidRefusesANameEndingWithASpace(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1103);
        $this->expectExceptionMessage("Incorrect table name 'x '");

        (new RenameTableCommand())->valid('x ');
    }

    public function testValidRefusesALongName(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1059);

        (new RenameTableCommand())->valid(str_repeat('a', 65));
    }

    public function testVacantRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        (new RenameTableCommand())->vacant($session, 'abc', 'x');
    }

    public function testRenamedDeclaresTheTableUnderItsNewName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $definition = (new RenameTableCommand())->renamed($session, $context, new Connection($session->variables, $context), $table, 'd', 'u');

        self::assertSame(['d', 'u', 'u'], [$definition->schema, $definition->name, $definition->declaration->name->name->value]);
    }

    public function testNamesLeavesTemporaryTablesOut(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TEMPORARY TABLE u (a INT)');

        self::assertSame(["d\0t"], array_keys((new RenameTableCommand())->names($session->instance->dictionary)));
    }

    public function testExecuteReportsAMissingTableAsAMissingFileIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1017);
        $this->expectExceptionMessage("Can't find file: './d/nosuch.frm' (errno: 2 - No such file or directory)");

        $session->query('RENAME TABLE nosuch TO other');
    }
}
