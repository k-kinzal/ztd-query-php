<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\ShadowTableState;

#[CoversClass(ShadowTableState::class)]
final class ShadowTableStateTest extends TestCase
{
    public function testDefinesAllPresenceStates(): void
    {
        self::assertSame(
            [ShadowTableState::Missing, ShadowTableState::Materialized, ShadowTableState::Initialized],
            ShadowTableState::cases(),
        );
    }
}
