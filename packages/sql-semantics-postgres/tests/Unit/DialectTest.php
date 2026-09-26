<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Dialect::class)]
#[UsesClass(Platform::class)]
final class DialectTest extends TestCase
{
    public function testPlatformBelongsToThisDatabase(): void
    {
        self::assertSame('postgresql', Dialect::PostgreSql->value);
        self::assertInstanceOf(Platform::class, Dialect::PostgreSql->platform());
    }
}
