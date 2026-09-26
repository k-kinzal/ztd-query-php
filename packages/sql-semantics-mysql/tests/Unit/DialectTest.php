<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(Dialect::class)]
#[UsesClass(Platform::class)]
final class DialectTest extends TestCase
{
    public function testPlatformBelongsToThisDatabase(): void
    {
        self::assertSame('mysql', Dialect::MySql->value);
        self::assertInstanceOf(Platform::class, Dialect::MySql->platform());
    }
}
