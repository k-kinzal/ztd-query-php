<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\TableExpressions;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableExpressions::class)]
#[Small]
final class TableExpressionsTest extends TestCase
{
    public function testComputedCompilesTheGeneratedColumnsOverTheRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT AS (a * 2), c INT AS (b + 1) STORED); INSERT INTO t (a) VALUES (1), (2)');

        $result1 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['1', '2', '3'], ['2', '4', '5']], $result1->rows);
    }

    public function testComputedComputesExpressionDefaultsFromTheRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT DEFAULT (c * 10), c INT DEFAULT 5, e INT DEFAULT (a + b)); INSERT INTO t (a) VALUES (1); INSERT INTO t (a, c) VALUES (2, 7)');

        $result2 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([['1', '50', '5', '51'], ['2', '70', '7', '72']], $result2->rows);
    }

    public function testElementsAnswersTheColumnDefinitions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT AS (a), KEY (a))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(2, count($table->definition->statement->elements ?? []) - 1);
    }

    public function testForbiddenComesBeforeTheColumnsAreResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3763);
        $this->expectExceptionMessage("Expression of generated column 'c' contains a disallowed function: rand.");

        $session->query('CREATE TABLE t1 (a INT, b INT AS (x), c INT AS (rand()))');
    }

    public function testKeyedRefusesAVirtualGeneratedColumnInThePrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3106);
        $this->expectExceptionMessage("'Defining a virtual generated column as primary key' is not supported for generated columns.");

        $session->query('CREATE TABLE t1 (a INT, b INT AS (a) VIRTUAL, PRIMARY KEY (b))');
    }

    public function testGeneratedReferencesRefusesALaterGeneratedColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3107);
        $this->expectExceptionMessage('Generated column can refer only to generated columns defined prior to it.');

        $session->query('CREATE TABLE t1 (a INT, b INT AS (c), c INT AS (a))');
    }

    public function testGeneratedReferencesNamesTheContextOfAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'x' in 'generated column function'");

        $session->query('CREATE TABLE t1 (a INT, b INT AS (x))');
    }

    public function testDefaultReferencesRefusesAnAutoIncrementColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3768);
        $this->expectExceptionMessage("Default value expression of column 'b' cannot refer to an auto-increment column.");

        $session->query('CREATE TABLE t1 (a INT AUTO_INCREMENT KEY, b INT DEFAULT (a))');
    }

    public function testDefaultedAnswersTheExpressionDefaultOfAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT DEFAULT (a + 1))');

        $result3 = $session->query('SHOW COLUMNS FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([['a', 'int', 'YES', '', null, ''], ['b', 'int', 'YES', '', '(`a` + 1)', 'DEFAULT_GENERATED']], $result3->rows);
    }

    public function testScopePlacesTheTableAtTheStartOfTheRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT AS (a + 1) STORED); INSERT INTO t (a) VALUES (4)');

        $result4 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertSame([['4', '5']], $result4->rows);
    }

    public function testCompiledRefusesWhatCannotBeCompiled(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3109);

        $session->query('CREATE TABLE t1 (a INT AUTO_INCREMENT PRIMARY KEY, b INT AS (a))');
    }

    public function testReferencesAnswersTheNameOfAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'x' in 'default value expression'");

        $session->query('CREATE TABLE t1 (a INT, b INT DEFAULT (x))');
    }
}
