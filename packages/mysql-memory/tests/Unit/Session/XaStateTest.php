<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Session\XaState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(XaState::class)]
#[Small]
final class XaStateTest extends TestCase
{
    public function testCasesNameTheStatesAsTheServerReportsThem(): void
    {
        self::assertSame(['NON-EXISTING', 'ACTIVE', 'IDLE'], array_map(static fn (XaState $state): string => $state->value, XaState::cases()));
    }
}
