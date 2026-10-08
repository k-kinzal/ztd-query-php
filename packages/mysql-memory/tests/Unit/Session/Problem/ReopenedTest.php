<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Reopened;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reopened::class)]
#[Small]
final class ReopenedTest extends TestCase
{
    public function testCheckRefusesATemporaryTableNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE x1 (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1137);
        $this->expectExceptionMessage("Can't reopen table: 'a'");

        $session->query('SELECT * FROM x1 AS a JOIN x1 AS b');
    }

    public function testTemporaryAnswersTheTemporaryTableAReferenceNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE x2 (a INT); CREATE TEMPORARY TABLE x1 (a INT)');

        $result1 = $session->query('SELECT count(*) FROM x2 a JOIN x2 b')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['0']], $result1->rows);
    }
}
