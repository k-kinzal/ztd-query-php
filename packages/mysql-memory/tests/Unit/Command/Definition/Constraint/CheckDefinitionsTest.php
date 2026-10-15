<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\CheckDefinitions;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckDefinitions::class)]
#[Small]
final class CheckDefinitionsTest extends TestCase
{
    public function testWrittenNamesTheUnnamedConstraintsInWrittenOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, CONSTRAINT x CHECK (a > 0), CHECK (a < 9), b INT CHECK (b > 0) NOT ENFORCED)');

        $shown1 = $session->query('SHOW CREATE TABLE t')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $shown1);

        self::assertSame(
            "CREATE TABLE `t` (\n  `a` int DEFAULT NULL,\n  `b` int DEFAULT NULL,\n  CONSTRAINT `t_chk_1` CHECK ((`a` < 9)),\n  CONSTRAINT `t_chk_2` CHECK ((`b` > 0)) /*!80016 NOT ENFORCED */,\n  CONSTRAINT `x` CHECK ((`a` > 0))\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            $shown1->rows[0][1],
        );
    }

    public function testWrittenIgnoresCheckConstraintsInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT CHECK (a > 0)); INSERT INTO t VALUES (0)');

        $result1 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['0']], $result1->rows);
    }

    public function testForbiddenRequiresAColumnReferenceInMySql80(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3813);
        $this->expectExceptionMessage("Column check constraint 't_chk_1' references other column.");

        $session->query('CREATE TABLE t(a INT CHECK(TRUE))');
    }

    public function testColumnScopePrecedesTheBooleanConditionCheck(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3813);

        $session->query('CREATE TABLE t(a INT CHECK(b), b INT)');
    }

    public function testForbiddenRefusesANameUsedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3822);
        $this->expectExceptionMessage("Duplicate check constraint name 't1_chk_1'.");

        $session->query('CREATE TABLE t1 (a INT, CONSTRAINT t1_chk_1 CHECK (a > 0), CHECK (a < 9))');
    }

}
