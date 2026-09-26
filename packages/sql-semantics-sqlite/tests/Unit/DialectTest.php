<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Platform;

#[CoversClass(Dialect::class)]
#[UsesClass(Platform::class)]
final class DialectTest extends TestCase
{
    public function testPlatformBelongsToThisDatabase(): void
    {
        self::assertSame('sqlite', Dialect::Sqlite->value);
        self::assertInstanceOf(Platform::class, Dialect::Sqlite->platform());
    }
}
