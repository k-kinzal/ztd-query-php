<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;

#[CoversClass(InnoDbKeyLimit::class)]
#[Small]
final class InnoDbKeyLimitTest extends TestCase
{
    public function testBytesDistinguishesPhysicalTermCapacity(): void
    {
        self::assertSame(767, InnoDbKeyLimit::Compact->bytes());
        self::assertSame(3072, InnoDbKeyLimit::LargePrefix->bytes());
    }

    public function testStockMatchesTheDefaultPhysicalLayoutOfTheRelease(): void
    {
        self::assertSame(InnoDbKeyLimit::Compact, InnoDbKeyLimit::stock(GrammarRelease::MySql5651));
        self::assertSame(InnoDbKeyLimit::LargePrefix, InnoDbKeyLimit::stock(GrammarRelease::MySql5744));
    }
}
