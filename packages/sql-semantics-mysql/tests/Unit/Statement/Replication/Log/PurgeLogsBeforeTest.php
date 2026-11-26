<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PurgeLogsBefore::class)]
#[Medium]
final class PurgeLogsBeforeTest extends TestCase
{
    public function testDeriveStatementDerivesTheMoment(): void
    {
        $purge = (new Inspection())->inspect("PURGE BINARY LOGS BEFORE '2024-01-01'");

        self::assertInstanceOf(PurgeLogsBefore::class, $purge->statement);
        self::assertSame([], $purge->facts->diagnostics);
    }

    public function testRenderWritesTheMoment(): void
    {
        self::assertSame("PURGE BINARY LOGS BEFORE '2024-01-01'", (new Inspection('mysql-5.6.51'))->inspect("purge master logs before '2024-01-01'")->toString());
    }
}
