<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\CreateTableCommand;
use MySqlMemory\Dictionary\Key;
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

    public function testCreatedUsesTheStatementClockForTheTransactionalDictionary(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; SET timestamp=1700000000; CREATE TABLE t(a INT)');

        self::assertSame(1700000000, $session->instance->dictionary->table('d', 't')?->created);
    }

    public function testCreatedUsesTheActualFileClockInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $before = time();
        $session->query('CREATE DATABASE d; USE d; SET timestamp=1700000000; CREATE TABLE t(a INT)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertGreaterThanOrEqual($before, $table->created);
        self::assertLessThanOrEqual(time(), $table->created);
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

    public function testStoreKeepsAStartedTableUntilCommit(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT PRIMARY KEY) START TRANSACTION');
        self::assertNull($session->instance->dictionary->table('d', 't'));
        $session->query('COMMIT');
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
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

    public function testDuplicatesAnswersEachKeyThatRepeatsAnEarlierOne(): void
    {
        $keys = [new Key('PRIMARY', KeyKind::Primary, [0]), new Key('a', KeyKind::Unique, [0]), new Key('a_2', KeyKind::Unique, [0]), new Key('a_3', KeyKind::Unique, [0]), new Key('b', KeyKind::Index, [0])];

        self::assertSame(['a_2', 'a_3'], array_map(static fn (Key $key): string => $key->name, (new CreateTableCommand())->duplicates($keys)));
    }

    public function testExecuteWarnsOfADuplicateIndex(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE u (a INT, UNIQUE (a), UNIQUE (a), KEY (a), KEY (a DESC))');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1831', "Duplicate index 'a_2' defined on the table 'p.u'. This is deprecated and will be disallowed in a future release."]], $warnings->rows);
    }

    public function testExecuteFillsATableWithTheRowsOfItsQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2)');

        $reply = $session->query('CREATE TABLE c SELECT a FROM t')[0];
        $rows = $session->query('SELECT * FROM c')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1'], ['2']], $rows->rows);
    }

    public function testExecuteLetsATemporaryTableHideABaseTableOfItsName(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $other = $instance->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1); CREATE TEMPORARY TABLE t (b INT)');
        $other->query('USE d');

        $result1 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $result2 = $other->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        $result3 = $session->query('SHOW TABLES')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertSame([[], [['1']], [['t']]], [$result1->rows, $result2->rows, $result3->rows]);
    }
}
