<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;

#[CoversClass(IndexRebuildMode::class)]
#[Small]
final class IndexRebuildModeTest extends TestCase
{
    public function testDistinguishesTheExplicitNativeRequest(): void
    {
        self::assertSame('standard', IndexRebuildMode::Standard->value);
        self::assertSame('concurrently', IndexRebuildMode::Concurrent->value);
    }
}
