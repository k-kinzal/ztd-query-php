<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\PartitionNames;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionNames::class)]
#[Small]
final class PartitionNamesTest extends TestCase
{
    public function testRequiredPrecedesTheColumnlessAndExistingTableConditions(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect();
        $session->query('USE d; CREATE TABLE t(a INT)');
        $session->run('CREATE TABLE IF NOT EXISTS t PARTITION BY RANGE COLUMNS ()');
        self::assertSame([1492], array_column($session->diagnostics->conditions, 1));
    }

    public function testCheckKeepsStoredInstructionsUntilTheirBranchIsReached(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(id INT PRIMARY KEY)');
        $session->query("CREATE PROCEDURE p(IN go INT) BEGIN IF go THEN ALTER TABLE t PARTITION BY RANGE(id) SUBPARTITION BY HASH(id) (PARTITION p0 VALUES LESS THAN (10) (SUBPARTITION '')); END IF; END");
        $session->query('CALL p(0)');
        self::assertSame(0, count($session->diagnostics->conditions));
        $session->run('CALL p(1)');
        self::assertSame(1567, $session->diagnostics->conditions[0][1]);
        $session->query('DROP TABLE t');
        $session->run('CALL p(1)');
        self::assertSame(1146, $session->diagnostics->conditions[0][1]);
    }
}
