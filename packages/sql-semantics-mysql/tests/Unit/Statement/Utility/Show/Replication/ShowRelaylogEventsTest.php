<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents;

#[CoversClass(ShowRelaylogEvents::class)]
#[Medium]
final class ShowRelaylogEventsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SHOW RELAYLOG EVENTS IN 'r' LIMIT 1");
        self::assertInstanceOf(ShowRelaylogEvents::class, $show->statement);
        self::assertSame('Info', $show->field(5)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame("SHOW RELAYLOG EVENTS IN 'r' LIMIT 1", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SHOW RELAYLOG EVENTS IN 'r' LIMIT 1")->toString());
    }
}
