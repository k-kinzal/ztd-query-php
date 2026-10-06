<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;

#[CoversClass(ParameterMode::class)]
#[Small]
final class ParameterModeTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['IN', 'OUT', 'INOUT'], array_map(static fn (ParameterMode $case): string => $case->value, ParameterMode::cases()));
    }
}
