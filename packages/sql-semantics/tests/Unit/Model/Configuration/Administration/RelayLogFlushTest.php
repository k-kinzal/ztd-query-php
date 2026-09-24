<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\RelayLogFlush;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(RelayLogFlush::class)]
#[Small]
final class RelayLogFlushTest extends TestCase
{
    public function testAvailableInRequiresMySql57ForAChannel(): void
    {
        self::assertTrue((new RelayLogFlush())->availableIn(50651));
        self::assertFalse((new RelayLogFlush('east'))->availableIn(50651));
        self::assertTrue((new RelayLogFlush('east'))->availableIn(50744));
    }

    public function testRejectsAChannelWithALineFeed(): void
    {
        $this->expectException(InvalidStructure::class);
        new RelayLogFlush("a\nb");
    }
}
