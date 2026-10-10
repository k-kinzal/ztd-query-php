<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Statement\Source\Origin;

#[CoversClass(Origin::class)]
#[Small]
final class OriginTest extends TestCase
{
    public function testRangeKeepsTheOccurrenceAndBytePositions(): void
    {
        $node = new IntegerLiteral('1');
        $origin = new Origin($node, 12, 1);
        self::assertSame([$node, 12, 1], [$origin->node, $origin->offset, $origin->length]);
    }

}
