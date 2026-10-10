<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Storage\Constrained;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Constrained::class)]
#[Small]
final class ConstrainedTest extends TestCase
{
    public function testUpdatedChecksTheCheckConstraintsBeforeTheUniqueKeys(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE c (id INT PRIMARY KEY, CHECK (id > 0)); INSERT INTO c VALUES (1), (2)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3819);
        $this->expectExceptionMessage("Check constraint 'c_chk_1' is violated.");

        $session->query('UPDATE c SET id = 0');
    }

    public function testUpdatedChecksTheUniqueKeysBeforeTheForeignKeys(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (id INT PRIMARY KEY, pid INT, FOREIGN KEY (pid) REFERENCES p(id)); INSERT INTO c VALUES (1, NULL), (2, NULL)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1062);

        $session->query('UPDATE c SET id = 2, pid = 5 WHERE id = 1');
    }

    public function testRefusedRecordsTheErrorAsAWarningWithIgnore(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, CHECK (b > 0)); INSERT INTO t VALUES (1, 1); UPDATE IGNORE t SET b = -5');

        $result1 = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        $result2 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([[['Warning', '3819', "Check constraint 't_chk_1' is violated."]], [['1', '1']]], [$result1->rows, $result2->rows]);
    }
}
