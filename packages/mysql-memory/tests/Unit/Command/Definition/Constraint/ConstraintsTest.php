<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\Constraints;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Constraints::class)]
#[Small]
final class ConstraintsTest extends TestCase
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

    public function testGeneratedTellsTheNamesTheServerGives(): void
    {
        self::assertSame([true, true, false], [Constraints::generated('t_chk_3', 't', '_chk_'), Constraints::generated('T_IBFK_12', 't', '_ibfk_'), Constraints::generated('t_chk_x', 't', '_chk_')]);
    }

    public function testHighestAnswersTheHighestNumberTheServerGave(): void
    {
        self::assertSame(9, Constraints::highest(['t_chk_2', 'x', 't_chk_9', 'u_chk_12'], 't', '_chk_'));
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

    public function testUniqueRefusesANameAnotherTableOfTheDatabaseHas(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE m (a INT, CONSTRAINT a1 CHECK (a > 10))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3822);
        $this->expectExceptionMessage("Duplicate check constraint name 'a1'.");

        $session->query('CREATE TABLE m2 (a INT, CONSTRAINT a1 CHECK (a > 10))');
    }

    public function testConstrainedRefusesAColumnConstraintThatReadsAnotherColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3813);
        $this->expectExceptionMessage("Column check constraint 't1_chk_1' references other column.");

        $session->query('CREATE TABLE t1 (a INT CHECK (b > 0))');
    }

    public function testConstrainedRefusesAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3820);
        $this->expectExceptionMessage("Check constraint 't1_chk_1' refers to non-existing column 'b'.");

        $session->query('CREATE TABLE t1 (a INT, CHECK (b > 0))');
    }

    public function testActedRefusesACheckOnAColumnAReferentialActionChanges(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c1 (pid INT, CHECK (pid > 3), FOREIGN KEY (pid) REFERENCES p(id) ON DELETE CASCADE)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3823);
        $this->expectExceptionMessage("Column 'pid' cannot be used in a check constraint 'c3_chk_1': needed in a foreign key constraint 'c3_ibfk_1' referential action.");

        $session->query('CREATE TABLE c3 (pid INT, CHECK (pid > 3), FOREIGN KEY (pid) REFERENCES p(id) ON DELETE SET NULL)');
    }
}
