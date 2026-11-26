<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexDropMode;

#[CoversClass(IndexDropMode::class)]
#[Small]
final class IndexDropModeTest extends TestCase
{
    public function testConcurrentAndOrdinaryAreDistinctPolicies(): void
    {
        self::assertSame('concurrently', IndexDropMode::Concurrently->value);
        self::assertSame('ordinary', IndexDropMode::Ordinary->value);
    }
}
