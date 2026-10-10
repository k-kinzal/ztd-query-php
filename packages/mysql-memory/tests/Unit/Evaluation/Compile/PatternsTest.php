<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Patterns;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patterns::class)]
#[Small]
final class PatternsTest extends TestCase
{
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
