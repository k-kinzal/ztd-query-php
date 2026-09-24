<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormatKind;

#[CoversClass(TemporalFormatKind::class)]
final class TemporalFormatKindTest extends TestCase
{
    public function testCasesAreSpelledAsTheirKeywords(): void
    {
        self::assertSame(['DATE', 'TIME', 'DATETIME'], array_map(static fn (TemporalFormatKind $kind): string => $kind->value, TemporalFormatKind::cases()));
    }
}
