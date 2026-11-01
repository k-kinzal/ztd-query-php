<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Function\StoredCall;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredCall::class)]
#[Small]
final class StoredCallTest extends TestCase
{
    public function testDomainAnswersTheTypeTheFunctionReturns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE FUNCTION f() RETURNS VARCHAR(3) DETERMINISTIC RETURN 'ab'");

        $result1 = $session->query('SELECT f()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        $columns = $result1->columns;

        self::assertSame([12, 0], [$columns[0]->length, $columns[0]->decimals]);
    }

    public function testEvaluateRunsTheFunctionForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $session->query('CREATE FUNCTION dbl(x INT) RETURNS INT DETERMINISTIC RETURN x * 2');

        $result2 = $session->query('SELECT a, dbl(a) FROM t WHERE dbl(a) > 2')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertSame([['2', '4'], ['3', '6']], $result2->rows);
    }
}
