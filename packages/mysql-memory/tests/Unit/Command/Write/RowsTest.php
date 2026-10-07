<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\Rows;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;

#[CoversClass(Rows::class)]
#[Small]
final class RowsTest extends TestCase
{
    public function testWriteRefusesARowOfTheWrongWidth(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1136);
        $this->expectExceptionMessage("Column count doesn't match value count at row 1");

        $session->query('INSERT INTO t VALUES (1)');
    }

    public function testWriteNamesTheRowOfTheWrongWidthAmongSeveral(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1136);
        $this->expectExceptionMessage("Column count doesn't match value count at row 2");

        $session->query('INSERT INTO t VALUES (1, 2), (3)');
    }

    public function testWriteGeneratesTheAutoIncrementValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, v INT)');

        $session->query('INSERT INTO t (v) VALUES (1); INSERT INTO t VALUES (10, 2); INSERT INTO t VALUES (NULL, 3), (0, 4)');
        $result = $session->query('SELECT id, v FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['10', '2'], ['11', '3'], ['12', '4']], $result->rows);
    }

    public function testDefaultedRefusesAColumnWithoutDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT NOT NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1364);
        $this->expectExceptionMessage("Field 'b' doesn't have a default value");

        $session->query('INSERT INTO t (a) VALUES (1)');
    }

    public function testDefaultedWritesTheImplicitDefaultWithAWarningOutsideStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = ''; CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT NOT NULL, c VARCHAR(3) NOT NULL)");

        $reply = $session->query('INSERT INTO t (a) VALUES (1)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $result = $session->query('SELECT a, b, c FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 2], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1364', "Field 'b' doesn't have a default value"], ['Warning', '1364', "Field 'c' doesn't have a default value"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '']], $result->rows);
    }

    public function testNotNullRefusesNullForANotNullColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT NOT NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1048);
        $this->expectExceptionMessage("Column 'b' cannot be null");

        $session->query('INSERT INTO t VALUES (1, NULL)');
    }

    public function testNotNullWritesTheImplicitDefaultForNullInARowOfSeveralOutsideStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = ''; CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT NOT NULL)");

        $reply = $session->query('INSERT INTO t VALUES (1, NULL), (2, 5)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $result = $session->query('SELECT a, b FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 1, 'Records: 2  Duplicates: 0  Warnings: 1'], [$reply->affectedRows, $reply->warnings, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1048', "Column 'b' cannot be null"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0'], ['2', '5']], $result->rows);
    }

    public function testPlaceRefusesADuplicateKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry '1' for key 't.PRIMARY'");

        $session->query('INSERT INTO t VALUES (1, 11)');
    }

    public function testPlaceSkipsADuplicateKeyWithAWarningForIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10)');

        $reply = $session->query('INSERT IGNORE INTO t VALUES (1, 11), (2, 20)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $result = $session->query('SELECT id, v FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 1, 'Records: 2  Duplicates: 1  Warnings: 1'], [$reply->affectedRows, $reply->warnings, $reply->info]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1062', "Duplicate entry '1' for key 't.PRIMARY'"]], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '10'], ['2', '20']], $result->rows);
    }

    public function testPlaceReplacesTheConflictingRowCountingTwoAffectedRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10)');

        $replaced = $session->query('REPLACE INTO t VALUES (1, 12)')[0];
        $inserted = $session->query('REPLACE INTO t VALUES (2, 20)')[0];
        $result = $session->query('SELECT id, v FROM t')[0];

        self::assertInstanceOf(Completion::class, $replaced);
        self::assertInstanceOf(Completion::class, $inserted);
        self::assertSame([2, 1], [$replaced->affectedRows, $inserted->affectedRows]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '12'], ['2', '20']], $result->rows);
    }

    public function testPlaceDeletesEveryRowAReplacementConflictsWith(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, u INT UNIQUE, v INT); INSERT INTO t VALUES (1, 1, 0), (2, 2, 0)');

        $reply = $session->query('REPLACE INTO t VALUES (1, 2, 9)')[0];
        $result = $session->query('SELECT id, u, v FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(3, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2', '9']], $result->rows);
    }

    public function testLastUniqueAnswersTheLastUniqueKeyOfTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, u INT UNIQUE, v INT, KEY (v))');
        $table = $session->instance->dictionary->table('d', 't');
        $operation = $session->analyze('INSERT INTO t VALUES (1, 1, 1)');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $statement = $operation->statement;

        self::assertNotNull($table);
        self::assertInstanceOf(InsertRows::class, $statement);
        self::assertSame('u', (new Rows($table, $context, $planner, $statement->into, [], $session))->lastUnique()?->name);
    }

    public function testUpdateAppliesOnDuplicateKeyUpdateCountingTwoAffectedRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT, w INT); INSERT INTO t VALUES (1, 10, 0)');

        $reply = $session->query('INSERT INTO t VALUES (1, 5, 0) ON DUPLICATE KEY UPDATE v = v + VALUES(v), w = v')[0];
        $result = $session->query('SELECT id, v, w FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '15', '15']], $result->rows);
    }

    public function testUpdateCountsNoAffectedRowWhenNothingChanges(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10)');

        $reply = $session->query('INSERT INTO t VALUES (1, 0) ON DUPLICATE KEY UPDATE v = v')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
    }

    public function testUpdateRefusesAnUpdateThatConflictsWithAnotherRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT); INSERT INTO t VALUES (1, 10), (2, 20)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);
        $this->expectExceptionMessage("Duplicate entry '2' for key 't.PRIMARY'");

        $session->query('INSERT INTO t VALUES (1, 0) ON DUPLICATE KEY UPDATE id = 2');
    }

    public function testUpdateScopeAnswersTheSameScopeEachTime(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $operation = $session->analyze('INSERT INTO t VALUES (1, 1)');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $statement = $operation->statement;

        self::assertNotNull($table);
        self::assertInstanceOf(InsertRows::class, $statement);
        $rows = new Rows($table, $context, $planner, $statement->into, [], $session);
        self::assertSame($rows->updateScope(), $rows->updateScope());
        self::assertSame($table->definition, $rows->updateScope()->inserted);
    }
}
