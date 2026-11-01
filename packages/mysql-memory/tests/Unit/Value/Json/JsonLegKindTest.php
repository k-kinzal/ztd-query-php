<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonLegKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonLegKind::class)]
#[Small]
final class JsonLegKindTest extends TestCase
{
    public function testWildHoldsForTheLegsThatSelectSeveralValues(): void
    {
        self::assertSame([false, true, false, true, true, true], array_map(static fn (JsonLegKind $kind): bool => $kind->wild(), JsonLegKind::cases()));
    }
}
