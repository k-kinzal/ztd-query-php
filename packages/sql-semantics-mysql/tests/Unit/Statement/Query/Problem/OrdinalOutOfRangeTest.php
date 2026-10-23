<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;

#[CoversClass(OrdinalOutOfRange::class)]
#[Small]
final class OrdinalOutOfRangeTest extends TestCase
{
    public function testNamePreservesTheFullWrittenInteger(): void
    {
        $ordinal = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('18446744073709551615'));
        self::assertSame('18446744073709551615', (new OrdinalOutOfRange(PHP_INT_MAX, 2, $ordinal))->name());
        self::assertSame('2', (new OrdinalOutOfRange(2, 1))->name());
    }

    public function testMessageNamesThePositionAndTheLength(): void
    {
        self::assertSame("Unknown column '4' in 'order clause': the select list has 2 items.", (new OrdinalOutOfRange(4, 2))->message());
    }
}
