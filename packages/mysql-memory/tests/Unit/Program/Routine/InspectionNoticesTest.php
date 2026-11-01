<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Routine;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Routine\InspectionNotices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(InspectionNotices::class)]
#[Medium]
final class InspectionNoticesTest extends TestCase
{
    public function testLoadReportsTheRetainedNameOnlyOnFirstLoading(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; CREATE PROCEDURE d.p() SHOW DATABASES LIKE '" . str_repeat('a', 246) . "'");
        $session->query('CALL d.p()');
        self::assertSame([1466], array_column($session->diagnostics->conditions, 1));
        $session->query('CALL d.p()');
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testStartRepeatsWarningsWhenTheComputedReferenceCannotPrepare(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; CREATE PROCEDURE d.p() SHOW TABLES LIKE '" . str_repeat('a', 246) . "'");
        $runs = array_map(static fn (): array => [$session->run('CALL d.p()')[0], array_column($session->diagnostics->conditions, 1)], [0, 1, 2]);
        self::assertContainsOnlyInstancesOf(SqlError::class, array_column($runs, 0));
        self::assertSame([[1466, 1466, 1054], [1466, 1466, 1054], [1466, 1466, 1054]], array_column($runs, 1));
    }
}
