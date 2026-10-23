<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Patterns;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patterns::class)]
#[Small]
final class PatternsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerPreparedPatterns(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            $extra = str_starts_with($release, '5.') ? 1 : 0;
            foreach (["'x' LIKE CAST('x' AS SIGNED)" => 1, "NULL LIKE CAST('x' AS SIGNED)" => 0, "IF(0, 'x' LIKE CAST('x' AS SIGNED), 1)" => 0, 'NULL LIKE CAST(@v AS SIGNED)' => 0] as $expression => $warnings) {
                yield $release . ' ' . $expression => [$release, $expression, $extra + $warnings];
            }
        }
    }

    #[DataProvider('providerPreparedPatterns')]
    public function testLikePreparesLegacyConstantPatternsEvenWhenNoMatchIsEvaluated(string $release, string $expression, int $count): void
    {
        $session = (new Instance($release))->connect();
        $session->query("SET @v='x'");
        $session->query('SELECT ' . $expression);
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(array_fill(0, $count, ['Warning', '1292', "Truncated incorrect INTEGER value: 'x'"]), $warnings->rows);
    }

    public function testLikeMatchesAPatternInTheCollationOfTheOperands(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'abc' LIKE 'a%', 'abc' LIKE 'A_C', 'abc' NOT LIKE 'b%', 'a%c' LIKE 'a|%c' ESCAPE '|', 'abc' LIKE 'a|%c' ESCAPE '|', NULL LIKE 'a'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '1', '0', null]], $result->rows);
    }

    public function testLikeRefusesAnEscapeOfMoreThanOneCharacter(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to ESCAPE');

        $session->query("SELECT 'a' LIKE 'a' ESCAPE 'ab'");
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
}
