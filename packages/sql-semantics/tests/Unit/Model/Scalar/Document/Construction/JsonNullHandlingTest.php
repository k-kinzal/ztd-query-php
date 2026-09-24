<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling;

#[CoversClass(JsonNullHandling::class)]
final class JsonNullHandlingTest extends TestCase
{
    public function testCasesAreSpelledAsTheirClause(): void
    {
        self::assertSame(['NULL ON NULL', 'ABSENT ON NULL'], array_map(static fn (JsonNullHandling $handling): string => $handling->value, JsonNullHandling::cases()));
    }
}
