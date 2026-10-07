<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use MySqlMemory\Protocol\MalformedPacket;
use MySqlMemory\Protocol\PayloadReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(MalformedPacket::class)]
#[Small]
final class MalformedPacketTest extends TestCase
{
    public function testGetMessageTellsWhereThePacketEnds(): void
    {
        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('The packet ends before the field.');

        (new PayloadReader("\x01"))->integer(2);
    }

    public function testGetMessageTellsAnIntegerCannotStartWithTheErrorMarker(): void
    {
        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('An integer cannot start with 0xFF.');

        (new PayloadReader("\xFF"))->lengthEncoded();
    }
}
