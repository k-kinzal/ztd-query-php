<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;

#[CoversClass(IndexPresence::class)]
#[Small]
final class IndexPresenceTest extends TestCase
{
    public function testNamePresenceHasDistinctCases(): void
    {
        self::assertSame('vacant', IndexPresence::Vacant->value);
        self::assertSame('occupied', IndexPresence::Occupied->value);
    }
}
