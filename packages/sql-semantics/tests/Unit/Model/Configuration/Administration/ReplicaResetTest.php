<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\ReplicaReset;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ReplicaReset::class)]
#[Small]
final class ReplicaResetTest extends TestCase
{
    public function testAvailableInRequiresMySql57ForAChannel(): void
    {
        self::assertTrue((new ReplicaReset(true))->availableIn(50651));
        self::assertFalse((new ReplicaReset(false, 'c'))->availableIn(50651));
        self::assertTrue((new ReplicaReset(false, 'c'))->availableIn(50744));
    }

    public function testRejectsAChannelWithALineFeed(): void
    {
        $this->expectException(InvalidStructure::class);
        new ReplicaReset(false, "a\nb");
    }
}
