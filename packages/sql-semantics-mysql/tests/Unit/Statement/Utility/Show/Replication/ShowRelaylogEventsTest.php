<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowRelaylogEvents::class)]
#[Medium]
final class ShowRelaylogEventsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.7.44'))->inspect("SHOW RELAYLOG EVENTS IN 'r' LIMIT 1");
        self::assertInstanceOf(ShowRelaylogEvents::class, $show->statement);
        self::assertSame('Info', $show->field(5)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame("SHOW RELAYLOG EVENTS IN 'r' LIMIT 1", (new Inspection('mysql-5.7.44'))->inspect("SHOW RELAYLOG EVENTS IN 'r' LIMIT 1")->toString());
    }
}
