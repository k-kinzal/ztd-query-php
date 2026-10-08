<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Operators;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Scalar;

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

    public function testLikeRefusesAnEscapeOfMoreThanOneCharacter(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to ESCAPE');

        $session->query("SELECT 'a' LIKE 'a' ESCAPE 'ab'");
    }

    public function testLikeRefusesAnEscapeBeforeAnyRowIsRead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (c CHAR(2))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);

        $session->query("SELECT NULL LIKE 'a' ESCAPE 'ab' FROM t WHERE 0");
    }

    public function testEscapeRefusesAnEscapeThatReadsAColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (c CHAR(2)); INSERT INTO t VALUES ('|')");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);

        $session->query("SELECT 'a' LIKE 'a' ESCAPE c FROM t");
    }

    public function testEscapeAcceptsOneConstantCharacter(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (c CHAR(2)); INSERT INTO t VALUES ('|')");
        $session->query("SET @v = '|'");
        $result = $session->query("SELECT 'x%' LIKE 'x|%' ESCAPE (SELECT c FROM t), 'x_y' LIKE 'x|_y' ESCAPE @v, 'a%' LIKE 'a|%' ESCAPE '', 'a%' LIKE 'aé%' ESCAPE 'é', 'a1' LIKE 'a11' ESCAPE 1")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0', '1', '1']], $result->rows);
    }

    public function testLikeChecksAnEscapeKnownOnlyWhenTheStatementRunsAtTheFirstRow(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (c CHAR(2)); INSERT INTO t VALUES ('|')");
        $result = $session->query("SELECT c LIKE 'a' ESCAPE CONCAT('a', USER()) FROM t WHERE 0")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $session->query("SELECT c LIKE 'a' ESCAPE ROW_COUNT() FROM t WHERE 0");
    }

    public function testCompareReadsAConstantOperandComparedAsANumberOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT); INSERT INTO t VALUES (1), (2), (3)');
        $session->query("SELECT 'a' = id, id IN ('b'), DATABASE() < id, 'c' = 'd' + 0 FROM t");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(["'a'", "'b'", "'d'", "'c'", "'d'", "'d'", "'d'"], array_map(static fn (array $row): string => (string) strstr((string) $row[2], "'"), $warnings->rows));
    }

    public function testNumericReadsAVaryingOperandWhenItIsEvaluated(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (s CHAR(2)); INSERT INTO t VALUES ('a'), ('b')");
        $session->query("SELECT s = ('c' + 0) FROM t");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(["'a'", "'c'", "'b'", "'c'"], array_map(static fn (array $row): string => (string) strstr((string) $row[2], "'"), $warnings->rows));
    }

    public function testTruthOperandReadsAConstantStringOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT); INSERT INTO t VALUES (1), (2), (3)');
        $session->query("SELECT NOT 'a', 'b' AND id, 'c' IS TRUE, IF('d', 1, 2) FROM t");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(["'a'", "'b'", "'c'", "'d'", "'d'", "'d'"], array_map(static fn (array $row): string => (string) strstr((string) $row[2], "'"), $warnings->rows));
    }

    public function testVariesTellsWhetherAComparisonOfAStringIsEvaluatedWholeForIsNull(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (id INT, s CHAR(2)); INSERT INTO t VALUES (1, 'a'), (2, NULL)");
        $session->query('SELECT (s = id) IS NULL, (s = (SELECT u.id FROM t AS u WHERE u.id = t.id)) IS NULL, (s = 0) IS NULL FROM t');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testNegationOfATestOfNullIsTheOppositeTest(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (s CHAR(2)); INSERT INTO t VALUES ('a'), (NULL)");
        $result = $session->query('SELECT NOT ((s IS TRUE) IS NULL), NOT ((s IS TRUE) IS NOT NULL), NOT ISNULL(s) FROM t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1'], ['1', '0', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testInListOfOneValueIsAComparison(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (id INT, s CHAR(2)); INSERT INTO t VALUES (1, 'a'), (2, NULL)");
        $result = $session->query("SELECT id IN ('a'), (s NOT IN (0)) IS NULL FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0'], ['0', '1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testTestedAnswersTheOperandOfATestOfNull(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT ((1 IS NULL)), ISNULL(2), 3 IS NOT UNKNOWN, 4 IS TRUE');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $isNull = $operation->field(0)->expression;
        $function = $operation->field(1)->expression;
        $isNotUnknown = $operation->field(2)->expression;
        $isTrue = $operation->field(3)->expression;
        self::assertNotNull($isNull);
        self::assertNotNull($function);
        self::assertNotNull($isNotUnknown);
        self::assertNotNull($isTrue);
        $operators = $planner->compiler->operators;

        self::assertSame([NumberLiteral::class, false], array_map(static fn (Scalar|bool $part): string|bool => is_bool($part) ? $part : $part::class, $operators->tested($isNull) ?? []));
        self::assertSame([NumberLiteral::class, false], array_map(static fn (Scalar|bool $part): string|bool => is_bool($part) ? $part : $part::class, $operators->tested($function) ?? []));
        self::assertSame([NumberLiteral::class, true], array_map(static fn (Scalar|bool $part): string|bool => is_bool($part) ? $part : $part::class, $operators->tested($isNotUnknown) ?? []));
        self::assertNull($operators->tested($isTrue));
    }

    public function testNullnessEvaluatesIsNullOfAResolvedOperandWhenTheStatementIsResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT); INSERT INTO t VALUES (1), (2)');
        $none = $session->query("SELECT CONCAT('a', 1/0) IS NULL FROM t WHERE 0")[0];
        $once = $session->query('SHOW WARNINGS')[0];
        $session->query("SELECT NOT (CONCAT('a', 1/0) IS NULL), CONCAT('a', USER() / 0) IS NULL FROM t");
        $each = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $none);
        self::assertSame([], $none->rows);
        self::assertInstanceOf(ResultSet::class, $once);
        self::assertSame([['Warning', '1365', 'Division by 0']], $once->rows);
        self::assertInstanceOf(ResultSet::class, $each);
        self::assertSame(['1365', '1292', '1365', '1365', '1292', '1365'], array_column($each->rows, 1));
    }
}
