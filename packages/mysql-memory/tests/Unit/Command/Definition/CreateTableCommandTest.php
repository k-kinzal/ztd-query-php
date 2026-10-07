<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateTableCommand::class)]
#[Small]
final class CreateTableCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new CreateTableCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesAnEmptyTableInTheCurrentDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $reply = $session->query('CREATE TABLE t (a INT)')[0];
        $table = $session->instance->dictionary->table('d', 't');

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 0, 0], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings]);
        self::assertNotNull($table);
        self::assertSame([], $table->data->rows);
    }

    public function testExecuteCreatesATableInTheDatabaseItNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE DATABASE e; USE d');

        $session->query('CREATE TABLE e.t (a INT)');

        self::assertSame([false, true], [$session->instance->dictionary->table('d', 't') !== null, $session->instance->dictionary->table('e', 't') !== null]);
    }

    public function testExecuteCommitsTheOpenTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $session->query('BEGIN; INSERT INTO t VALUES (1); CREATE TABLE u (a INT); ROLLBACK');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testExecuteCreatesAnExistingTableIfNotExistsWithANote(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $reply = $session->query('CREATE TABLE IF NOT EXISTS t (b INT)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(1, $reply->warnings);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1050', "Table 't' already exists"]], $warnings->rows);
        self::assertSame('a', $session->instance->dictionary->table('d', 't')?->definition->columns[0]->name);
    }

    public function testExecuteRefusesAnExistingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1050);
        $this->expectExceptionMessage("Table 't' already exists");

        $session->query('CREATE TABLE t (a INT)');
    }

    public function testExecuteRefusesWithoutADatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query('CREATE TABLE t (a INT)');
    }

    public function testExecuteRefusesAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nope'");

        $session->query('CREATE TABLE nope.t (a INT)');
    }

    public function testPrimaryNotNullMakesTheColumnsOfThePrimaryKeyNotNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, c INT, PRIMARY KEY (a, c))');
        $columns = $session->instance->dictionary->table('d', 't')?->definition->columns ?? [];

        self::assertSame(
            [[false, false], [true, true], [false, false]],
            [[$columns[0]->nullable(), $columns[0]->default->declared], [$columns[1]->nullable(), $columns[1]->default->declared], [$columns[2]->nullable(), $columns[2]->default->declared]],
        );
    }

    public function testPrimaryNotNullRefusesNullInAPrimaryKeyColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1048);
        $this->expectExceptionMessage("Column 'a' cannot be null");

        $session->query('INSERT INTO t VALUES (NULL, 1)');
    }

    public function testUniqueTellsTheKeyKindsThatRefuseDuplicates(): void
    {
        $command = new CreateTableCommand();

        self::assertSame(
            [true, true, false, false, false],
            [$command->unique(KeyKind::Primary), $command->unique(KeyKind::Unique), $command->unique(KeyKind::Index), $command->unique(KeyKind::FullText), $command->unique(KeyKind::Spatial)],
        );
    }
}
