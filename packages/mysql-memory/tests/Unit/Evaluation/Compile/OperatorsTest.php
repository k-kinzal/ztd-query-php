<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Operators;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operators::class)]
#[Small]
final class OperatorsTest extends TestCase
{
    public function testTruthTypesTheEqualitiesOfAUsingJoin(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, v INT)');
        $session->query('CREATE TABLE u (id INT, w INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20), (NULL, 30)');
        $session->query('INSERT INTO u VALUES (1, 100), (3, 300), (NULL, 400)');
        $result = $session->query('SELECT * FROM t JOIN u USING (id)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '10', '100']], $result->rows);
    }

    public function testArithmeticCompilesTheArithmeticAndBitOperators(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 7 DIV 2, 7 % 3, 5 & 3, 5 | 3, 5 ^ 3, 1 << 3, 1 + 1.5, 1 / 3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '1', '1', '7', '6', '8', '2.5', '0.3333']], $result->rows);
    }

    public function testArithmeticRefusesAResultOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT value is out of range in '(9223372036854775807 + 1)'");

        $session->query('SELECT 9223372036854775807 + 1');
    }

    public function testUnaryCompilesTheUnaryOperators(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT -5, +5, ~0, -NULL, - -3.5')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-5', '5', '18446744073709551615', null, '3.5']], $result->rows);
    }

    public function testComparisonComparesInTheTypeOfTheOperands(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 1 < 2, 'a' = 'A', NULL = NULL, NULL <=> NULL, 2 >= 3, 1 <> 1, '10' > 9, '10' > '9'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', null, '1', '0', '0', '1', '0']], $result->rows);
    }

    public function testLogicalCompilesAndOrAndXor(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 AND 0, 1 OR NULL, 0 AND NULL, NULL AND 1, 1 XOR 1, NULL XOR 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '0', null, '0', null]], $result->rows);
    }

    public function testNotNegatesATruthValue(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NOT 1, NOT 0, NOT NULL, NOT 0.5')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', null, '0']], $result->rows);
    }

    public function testNullTestTellsWhetherAValueIsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NULL IS NULL, 1 IS NOT NULL, 0 IS NULL, NULL IS NOT NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '0']], $result->rows);
        self::assertSame(1, $result->columns[0]->flags & 1);
    }

    public function testTruthTestComparesWithTrueFalseAndUnknown(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 IS TRUE, 0 IS FALSE, NULL IS UNKNOWN, NULL IS NOT TRUE, 2 IS TRUE, 0 IS NOT FALSE')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '1', '1', '0']], $result->rows);
    }

    public function testBetweenTellsWhetherAValueLiesInARange(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 2 BETWEEN 1 AND 3, 5 NOT BETWEEN 1 AND 3, 'b' BETWEEN 'a' AND 'c', NULL BETWEEN 1 AND 2, 3 BETWEEN 3 AND 1")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', null, '0']], $result->rows);
    }

    public function testInListTellsWhetherAValueIsInTheList(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 2 IN (1, 2), 3 IN (1, 2), 3 IN (1, NULL), 3 NOT IN (1, 2), '1' IN (1.0), 1 IN (NULL, 1)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', null, '1', '1', '1']], $result->rows);
    }

    public function testLikeMatchesAPatternInTheCollationOfTheOperands(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'abc' LIKE 'a%', 'abc' LIKE 'A_C', 'abc' NOT LIKE 'b%', 'a%c' LIKE 'a|%c' ESCAPE '|', 'abc' LIKE 'a|%c' ESCAPE '|', NULL LIKE 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '1', '0', null]], $result->rows);
    }

    public function testCaseOfChoosesTheFirstBranchThatHolds(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE 2 WHEN 1 THEN 'one' WHEN 2 THEN 'two' END, CASE WHEN 0 THEN 'x' ELSE 'y' END, CASE 3 WHEN 1 THEN 'one' END, CASE NULL WHEN NULL THEN 'n' ELSE 'e' END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['two', 'y', null, 'e']], $result->rows);
    }

    public function testCastCompilesTheConversionToTheTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('7' AS UNSIGNED) + 1, CAST(1.5 AS CHAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['8', '1.5']], $result->rows);
    }
}
