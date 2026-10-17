<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use MySqlMemory\Protocol\Capability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Capability::class)]
#[Small]
final class CapabilityTest extends TestCase
{
    public function testFlagsHoldTheBitsOfTheProtocol(): void
    {
        self::assertSame(1, Capability::LONG_PASSWORD);
        self::assertSame(2, Capability::FOUND_ROWS);
        self::assertSame(4, Capability::LONG_FLAG);
        self::assertSame(8, Capability::CONNECT_WITH_DB);
        self::assertSame(1 << 9, Capability::PROTOCOL_41);
        self::assertSame(1 << 11, Capability::SSL);
        self::assertSame(1 << 13, Capability::TRANSACTIONS);
        self::assertSame(1 << 15, Capability::SECURE_CONNECTION);
        self::assertSame(1 << 16, Capability::MULTI_STATEMENTS);
        self::assertSame(1 << 17, Capability::MULTI_RESULTS);
        self::assertSame(1 << 18, Capability::PS_MULTI_RESULTS);
        self::assertSame(1 << 19, Capability::PLUGIN_AUTH);
        self::assertSame(1 << 20, Capability::CONNECT_ATTRS);
        self::assertSame(1 << 21, Capability::PLUGIN_AUTH_LENENC_CLIENT_DATA);
        self::assertSame(1 << 24, Capability::DEPRECATE_EOF);
    }
}
