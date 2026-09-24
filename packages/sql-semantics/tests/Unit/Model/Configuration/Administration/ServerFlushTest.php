<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\ServerFlush;

#[CoversClass(ServerFlush::class)]
#[Small]
final class ServerFlushTest extends TestCase
{
    public function testAvailableInFollowsTheReleaseHistory(): void
    {
        self::assertTrue(ServerFlush::QueryCache->availableIn(50744));
        self::assertFalse(ServerFlush::DesKeyFile->availableIn(80044));
        self::assertTrue(ServerFlush::Hosts->availableIn(80300));
        self::assertFalse(ServerFlush::Hosts->availableIn(80407));
        self::assertFalse(ServerFlush::OptimizerCosts->availableIn(50651));
        self::assertTrue(ServerFlush::Privileges->availableIn(50651));
    }
}
