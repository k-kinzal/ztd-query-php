<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Storage\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Writer::class)]
#[Small]
final class WriterTest extends TestCase
{
    public function testConflictFindsTheRowAndTheKeyARowCollidesWith(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY, code VARCHAR(10), UNIQUE KEY uc (code))');
        $session->query("INSERT INTO d.t VALUES (1, 'ab'), (2, NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        $conflict = $writer->conflict([3, 'AB']);

        self::assertSame([1, 'uc'], [$conflict[0] ?? null, $conflict[1]->name ?? null]);
    }

    public function testConflictChecksThePrimaryKeyFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY, code VARCHAR(10), UNIQUE KEY uc (code))');
        $session->query("INSERT INTO d.t VALUES (1, 'ab'), (2, NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        $conflict = $writer->conflict([2, 'ab']);

        self::assertSame([2, 'PRIMARY'], [$conflict[0] ?? null, $conflict[1]->name ?? null]);
    }

    public function testConflictIgnoresNullsAndTheExceptedRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY, code VARCHAR(10), UNIQUE KEY uc (code))');
        $session->query("INSERT INTO d.t VALUES (1, 'ab'), (2, NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([null, null], [$writer->conflict([3, null]), $writer->conflict([1, 'ab'], 1)]);
    }

    public function testKeyIsEqualForValuesTheCollationHoldsEqual(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY, code VARCHAR(10), UNIQUE KEY uc (code))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));
        $key = $table->definition->keys[1];

        self::assertSame([true, false, null], [$writer->key([1, 'Ab'], $key) === $writer->key([2, 'aB'], $key), $writer->key([1, 'ab'], $key) === $writer->key([2, 'ac'], $key), $writer->key([1, null], $key)]);
    }

    public function testKeyComparesOnlyTheIndexedPrefix(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (code VARCHAR(10), UNIQUE KEY (code(2)))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));
        $key = $table->definition->keys[0];

        self::assertSame([true, false], [$writer->key(['abc'], $key) === $writer->key(['abd'], $key), $writer->key(['abc'], $key) === $writer->key(['acc'], $key)]);
    }

    public function testDuplicateNamesTheEntryAndTheKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY, code VARCHAR(10), UNIQUE KEY uc (code))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        $error = $writer->duplicate([1, 'ab'], $table->definition->keys[1]);

        self::assertSame([1062, "Duplicate entry 'ab' for key 't.uc'"], [$error->getCode(), $error->getMessage()]);
    }

    public function testEntryJoinsTheValuesOfTheKeyColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, b VARCHAR(5), UNIQUE KEY k (a, b))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame(['1-ab', 't.k'], $writer->entry([1, 'ab'], $table->definition->keys[0]));
    }

    public function testAutoIncrementFillsAMissingValueFromTheCounter(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT AUTO_INCREMENT PRIMARY KEY, a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([[[1, 5], 1], [[2, 6], 2], 3], [$writer->autoIncrement([null, 5], false), $writer->autoIncrement([0, 6], false), $table->data->autoIncrement]);
    }

    public function testAutoIncrementKeepsZeroWhenZeroIsAValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT AUTO_INCREMENT PRIMARY KEY, a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([[[0, 5], null], 1], [$writer->autoIncrement([0, 5], true), $table->data->autoIncrement]);
    }

    public function testAutoIncrementAdvancesTheCounterPastANamedValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT AUTO_INCREMENT PRIMARY KEY, a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([[[10, 5], null], [[11, 6], 11]], [$writer->autoIncrement([10, 5], false), $writer->autoIncrement([null, 6], false)]);
    }

    public function testAutoIncrementLeavesARowOfATableWithoutTheColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([[null], null], $writer->autoIncrement([null], false));
    }

    public function testDefaultAnswersTheDefaultOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT NOT NULL, b INT, c INT DEFAULT 7, e INT DEFAULT (1 + 1))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $writer = new Writer($table, $context);
        $frame = new Frame($context);
        $columns = $table->definition->columns;

        self::assertSame([[false, null], [true, null], [true, 7], [true, 2]], [$writer->default($columns[0], $frame), $writer->default($columns[1], $frame), $writer->default($columns[2], $frame), $writer->default($columns[3], $frame)]);
    }

    public function testImplicitAnswersTheImplicitDefaultOfEachType(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query("CREATE TABLE d.t (a INT, b DOUBLE, c DECIMAL(5,2), d DATE, e DATETIME(3), f TIME, g YEAR, h VARCHAR(3), i ENUM('x', 'y') NOT NULL, j BINARY(3), k BIT(10), l JSON)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([0, 0.0, '0.00', '0000-00-00', '0000-00-00 00:00:00.000', '00:00:00', 0, '', 'x', "\0\0\0", "\0\0", 'null'], array_map($writer->implicit(...), $table->definition->columns));
    }

    public function testRefreshSetsTheColumnsOnUpdateCurrentTimestampThatNoAssignmentWrote(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT, d DATETIME(3) ON UPDATE CURRENT_TIMESTAMP(3), e TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 1100000000.25));

        self::assertSame([2, date('Y-m-d H:i:s', 1100000000) . '.250', '2001-01-01 00:00:00'], $writer->refresh([2, null, '2001-01-01 00:00:00'], [0 => true, 2 => true]));
    }

    public function testEntryQuotesAValueOfAnotherCharacterSetInUtf8(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (c VARCHAR(10) CHARACTER SET latin1 PRIMARY KEY)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame(['é€', 't.PRIMARY'], $writer->entry(["\xE9\x80"], $table->definition->keys[0]));
    }

    public function testIgnoredWarnsOfADuplicateExceptIn56(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT PRIMARY KEY, code VARCHAR(10), UNIQUE KEY uc (code))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $modern = new Diagnostics();
        $legacy = new Diagnostics();
        (new Writer($table, new Context(new SqlModes([], GrammarRelease::MySql5744), $modern, new Variables(SystemVariables::of(GrammarRelease::MySql5744), new Globals()), 0.0)))->ignored([1, 'ab'], $table->definition->keys[1]);
        (new Writer($table, new Context(new SqlModes([], GrammarRelease::MySql5651), $legacy, new Variables(SystemVariables::of(GrammarRelease::MySql5651), new Globals()), 0.0)))->ignored([1, 'ab'], $table->definition->keys[1]);

        self::assertSame([['Warning', 1062, "Duplicate entry 'ab' for key 'uc'"]], $modern->conditions);
        self::assertSame([], $legacy->conditions);
    }

    public function testGenerateComputesTheGeneratedColumnsInColumnOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a VARCHAR(10), b INT AS (a), c TINYINT AS (length(a) * 100) STORED, d VARCHAR(2) AS (a)); SET sql_mode = ''; INSERT INTO t (a) VALUES ('xyz')");

        $result1 = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        $result2 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([[['Warning', '1366', "Incorrect integer value: 'xyz' for column 'b' at row 1"], ['Warning', '1264', "Out of range value for column 'c' at row 1"], ['Warning', '1265', "Data truncated for column 'd' at row 1"]], [['xyz', '0', '127', 'xy']]], [$result1->rows, $result2->rows]);
    }

    public function testViolatedAnswersTheFirstViolatedConstraintByName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE m (a INT, CONSTRAINT b2 CHECK (a > 5), CONSTRAINT a1 CHECK (a > 10), CONSTRAINT z CHECK (a IS NULL OR a > 0) NOT ENFORCED)');

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(3819);
        $this->expectExceptionMessage("Check constraint 'a1' is violated.");

        $session->query('INSERT INTO m VALUES (1)');
    }
    public function testConflictWaitsForADuplicateAnotherTransactionInserted(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (id INT PRIMARY KEY); BEGIN; INSERT INTO t VALUES (1)');

        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1205);

        $second->query('INSERT INTO t VALUES (1)');
    }

    public function testExistingChecksTheCommittedVersionsOfTheRowsOthersChanged(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $first = $instance->connect('root', 'localhost', 'd');
        $second = $instance->connect('root', 'localhost', 'd');
        $first->query('CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1); BEGIN; DELETE FROM t');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $writer = new Writer($table, new Context($second->modes(), $second->diagnostics, $second->variables, 0.0));

        self::assertSame([1, null], [$writer->existing([1], null, $second->transaction)[0] ?? null, $writer->existing([1], null, $first->transaction)]);
    }
}
