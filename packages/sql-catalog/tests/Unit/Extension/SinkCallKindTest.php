<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\SinkCallKind;

#[CoversClass(SinkCallKind::class)]
final class SinkCallKindTest extends TestCase
{
    public function testEveryFormOfCallHasItsOwnName(): void
    {
        self::assertSame(['method', 'static', 'function'], array_column(SinkCallKind::cases(), 'value'));
    }
}
