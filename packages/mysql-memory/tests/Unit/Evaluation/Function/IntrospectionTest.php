<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Introspection;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Introspection::class)]
#[Small]
final class IntrospectionTest extends TestCase
{
    public function testRoutinesNamesTheInformationFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Introspection())->routines());

        self::assertSame(['DATABASE', 'SCHEMA', 'USER', 'SESSION_USER', 'SYSTEM_USER', 'CURRENT_USER', 'CURRENT_ROLE', 'VERSION', 'CONNECTION_ID', 'LAST_INSERT_ID', 'ROW_COUNT', 'FOUND_ROWS', 'CHARSET', 'COLLATION', 'COERCIBILITY'], $names);
    }

    public function testRoutinesAnswerTheRolesActiveInTheSession(): void
    {
        $session = (new Instance())->connect();
        $before = $session->query('SELECT CURRENT_ROLE()')[0];
        $session->query('CREATE ROLE r2, r1; GRANT r1, r2 TO root; SET ROLE ALL');
        $after = $session->query('SELECT CURRENT_ROLE()')[0];

        self::assertInstanceOf(ResultSet::class, $before);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([[['NONE']], [['`r1`@`%`,`r2`@`%`']]], [$before->rows, $after->rows]);
    }

    public function testRoutinesAnswerNullForTheDatabaseBeforeOneIsChosen(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT DATABASE(), SCHEMA()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
    }

    public function testRoutinesAnswerTheDefaultDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $result = $session->query('SELECT DATABASE(), SCHEMA()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d', 'd']], $result->rows);
    }

    public function testRoutinesAnswerTheAccountOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT USER(), SESSION_USER(), SYSTEM_USER(), CURRENT_USER()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['root@localhost', 'root@localhost', 'root@localhost', 'root@%']], $result->rows);
    }

    public function testRoutinesAnswerTheServerVersion(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT VERSION()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['8.4.7']], $result->rows);
    }

    public function testRoutinesAnswerTheConnectionIdAsABigint(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CONNECTION_ID()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
    }

    public function testRoutinesAnswerTheRowsTheLastUpdateChanged(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t (v) VALUES (1), (2), (3)');
        $session->query('UPDATE t SET v = v + 1 WHERE id > 1');
        $result = $session->query('SELECT ROW_COUNT()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testRoutinesAnswerTheRowsTheLastSelectFound(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t (v) VALUES (1), (2), (3)');
        $session->query('SELECT * FROM t');
        $result = $session->query('SELECT FOUND_ROWS()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3']], $result->rows);
    }

    public function testRoutinesAnswerTheCharacterSetAndCollationOfAString(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CHARSET('abc'), COLLATION('abc'), CHARSET(_latin1 'a'), COLLATION(_latin1 'a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['utf8mb4', 'utf8mb4_0900_ai_ci', 'latin1', 'latin1_swedish_ci']], $result->rows);
    }

    public function testRoutinesAnswerBinaryForTheCollationOfANumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CHARSET(1), COLLATION(1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['binary', 'binary']], $result->rows);
    }

    public function testRoutinesAnswerTheMetadataNamesInUtf8mb3(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $result = $session->query("SELECT COLLATION(VERSION()), COLLATION(USER()), COLLATION(DATABASE()), COERCIBILITY(DATABASE()), COLLATION(CHARSET('a')), COERCIBILITY(CHARSET('a')), VERSION() = @@version, CHARSET('a') = 'utf8mb4'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['utf8mb3_general_ci', 'utf8mb3_general_ci', 'utf8mb3_general_ci', '3', 'utf8mb3_general_ci', '4', '1', '1']], $result->rows);
    }

    public function testRoutinesAnswerTheCoercibilityOfEachKindOfValue(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT COERCIBILITY('abc' COLLATE utf8mb4_bin), COERCIBILITY(USER()), COERCIBILITY('abc'), COERCIBILITY(1000), COERCIBILITY(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '3', '4', '5', '6']], $result->rows);
    }

    public function testLastInsertIdAnswersTheFirstValueTheLastInsertGenerated(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t (v) VALUES (1)');
        $session->query('INSERT INTO t (v) VALUES (2), (3), (4)');
        $result = $session->query('SELECT LAST_INSERT_ID()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testLastInsertIdSetsTheValueTheNextCallAnswers(): void
    {
        $session = (new Instance())->connect();
        $set = $session->query('SELECT LAST_INSERT_ID(42)')[0];
        $read = $session->query('SELECT LAST_INSERT_ID()')[0];

        self::assertInstanceOf(ResultSet::class, $set);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([['42']], $set->rows);
        self::assertSame([['42']], $read->rows);
    }

    public function testLastInsertIdTruncatesAStringWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LAST_INSERT_ID('7x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['7']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect INTEGER value: '7x'"]], $warnings->rows);
    }

    public function testLastInsertIdRecordsThatAFunctionSetTheValue(): void
    {
        $instance = new Instance();
        $variables = new Variables($instance->catalog, $instance->globals);
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), $variables, 0.0));
        $integer = new Domain(Kind::Integer, Field::LongLong, 21);

        self::assertSame(9, (new Introspection())->lastInsertId($frame, [new Constant($integer, 9)], $integer));
        self::assertSame(9, $variables->lastInsertId);
        self::assertTrue($variables->setByFunction);
        self::assertSame(9, (new Introspection())->lastInsertId($frame, [], $integer));
    }

    public function testRoutinesReadTheRowCountAndFoundRowsOfThePreviousStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2), (3)');
        $session->query('SELECT SQL_CALC_FOUND_ROWS * FROM t LIMIT 1');
        $result = $session->query('SELECT FOUND_ROWS(), ROW_COUNT()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '-1']], $result->rows);
    }
}
