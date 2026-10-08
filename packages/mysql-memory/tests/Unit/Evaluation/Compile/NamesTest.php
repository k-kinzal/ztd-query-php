<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Names;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Names::class)]
#[Small]
final class NamesTest extends TestCase
{
    public function testColumnReadsTheColumnOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20)');
        $result = $session->query('SELECT v, t.id FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10', '1'], ['20', '2']], $result->rows);
    }

    public function testColumnRefusesANameThatResolvesToNoColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'nosuch' in 'field list'");

        $session->query('SELECT nosuch FROM t');
    }

    public function testResolvedReadsTheColumnsOfEachRelationOfAJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, v INT)');
        $session->query('CREATE TABLE u (id INT, w INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20)');
        $session->query('INSERT INTO u VALUES (2, 200), (3, 300)');
        $result = $session->query('SELECT t.v, u.w, u.id FROM t JOIN u ON u.id = t.id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['20', '200', '2']], $result->rows);
    }

    public function testPositionFindsAColumnOfADerivedTable(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT dt.b, dt.a FROM (SELECT 1 AS a, 2 AS b) AS dt')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '1']], $result->rows);
    }

    public function testPositionFindsAColumnOfAStoredTableByItsDeclaration(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, h INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 2, 3)');
        $result = $session->query('SELECT b, h, a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '2', '1']], $result->rows);
    }

    public function testFieldReadsASelectItemNamedByItsAlias(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (v INT)');
        $session->query('INSERT INTO t VALUES (10), (30), (20)');
        $result = $session->query('SELECT v * 2 AS w FROM t ORDER BY w DESC')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['60'], ['40'], ['20']], $result->rows);
    }

    public function testInsertedReadsTheValueTheInsertWasToWrite(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, 10)');
        $reply = $session->query('INSERT INTO t VALUES (1, 5) ON DUPLICATE KEY UPDATE v = v + VALUES(v)')[0];
        $result = $session->query('SELECT v FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['15']], $result->rows);
    }

    public function testInsertedIsNullOutsideOnDuplicateKeyUpdate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (v INT)');
        $session->query('INSERT INTO t VALUES (10)');
        $result = $session->query('SELECT VALUES(v) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testOrdinalReadsTheSelectItemAtAPosition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, v INT)');
        $session->query('INSERT INTO t VALUES (1, 20), (2, 10)');
        $result = $session->query('SELECT id, v FROM t ORDER BY 2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '10'], ['1', '20']], $result->rows);
    }

    public function testOuterReadsAColumnOfTheEnclosingBlock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT)');
        $session->query('CREATE TABLE u (id INT, w INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $session->query('INSERT INTO u VALUES (1, 100), (3, 300)');
        $result = $session->query('SELECT id, (SELECT u.w FROM u WHERE u.id = t.id) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '100'], ['2', null]], $result->rows);
    }

    public function testParameterReadsTheValueBoundToTheMarker(): void
    {
        $session = (new Instance())->connect();
        $result = $session->run('SELECT ? * 2', [[21, Domain::integer()]], true)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['42']], $result->rows);
    }

    public function testUserVariableReadsAVariableNeverAssignedAsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT @nothing')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testUserVariableReadsTheNameWithoutRegardToLetterCase(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @Word = 'abc'");
        $result = $session->query('SELECT @word, @WORD')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', 'abc']], $result->rows);
    }

    public function testAssignmentAssignsTheVariableAndAnswersTheValue(): void
    {
        $session = (new Instance())->connect();
        $assigned = $session->query('SELECT @y := 4')[0];
        $read = $session->query('SELECT @y')[0];

        self::assertInstanceOf(ResultSet::class, $assigned);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([[['4']], [['4']]], [$assigned->rows, $read->rows]);
    }

    public function testStoredKeepsTheValueOfEachKindAssigned(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @i = 5, @d = 1.5, @f = 1e1, @s = 'x'");
        $result = $session->query('SELECT @i, @d, @f, @s')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', '1.5', '10', 'x']], $result->rows);
    }

    public function testSystemVariableReadsTheValueOfAVariable(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT @@autocommit, @@div_precision_increment, @@SESSION.div_precision_increment, @@GLOBAL.div_precision_increment')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '4', '4', '4']], $result->rows);
    }

    public function testSystemVariableRefusesAnUnknownVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        $this->expectExceptionMessage("Unknown system variable 'nosuch'");

        $session->query('SELECT @@nosuch');
    }

    public function testSystemVariableRefusesTheSessionValueOfAGlobalVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1238);
        $this->expectExceptionMessage("Variable 'version' is a GLOBAL variable");

        $session->query('SELECT @@SESSION.version');
    }

    public function testSystemVariableRefusesTheGlobalValueOfASessionVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1238);
        $this->expectExceptionMessage("Variable 'timestamp' is a SESSION variable");

        $session->query('SELECT @@GLOBAL.timestamp');
    }

    public function testDefaultReadsTheDefaultOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT UNSIGNED DEFAULT 3, b INT DEFAULT -5, c INT)');
        $session->query('INSERT INTO t VALUES (1, 2, 3)');
        $result = $session->query('SELECT DEFAULT(a), DEFAULT(b), DEFAULT(c) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '-5', null]], $result->rows);
    }

    public function testDefaultRefusesAColumnWithoutADefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (c INT NOT NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1364);
        $this->expectExceptionMessage("Field 'c' doesn't have a default value");

        $session->query('SELECT DEFAULT(c) FROM t');
    }

    public function testDefaultReadsTheDefaultCurrentTimestampAsNullOrTheZeroValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (c TIMESTAMP DEFAULT CURRENT_TIMESTAMP, d DATETIME(3) NOT NULL DEFAULT NOW(3)); INSERT INTO t () VALUES ()');

        $result = $session->query('SELECT DEFAULT(c), DEFAULT(d) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '0000-00-00 00:00:00.000']], $result->rows);
    }

    public function testColumnReadsAnInvisibleColumnByName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE v (a INT, e INT INVISIBLE); CREATE TABLE u (e INT, b INT); INSERT INTO v (a, e) VALUES (1, 2); INSERT INTO u VALUES (2, 7)');

        $result = $session->query('SELECT * FROM v JOIN u USING (e)')[0];
        $named = $session->query('SELECT e, v.e, u.e FROM v JOIN u USING (e)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '1', '7']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $named);
        self::assertSame([['2', '2', '2']], $named->rows);
    }

    public function testSystemVariableNamesAnUnknownStructuredVariableWithItsInstance(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        $this->expectExceptionMessage("Unknown system variable 'hot.sort_buffer_size'");

        $session->query('SELECT @@hot.sort_buffer_size');
    }
}
