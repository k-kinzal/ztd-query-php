<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;

#[CoversClass(SourceMap::class)]
#[Small]
final class SourceMapTest extends TestCase
{
    public function testOfDistinguishesEqualExpressionsAtDifferentPositions(): void
    {
        $first = new IntegerLiteral('1');
        $second = new IntegerLiteral('1');
        $origin = new Origin($first, 7, 1);
        $map = new SourceMap([$origin, new Origin($second, 10, 1)]);
        self::assertSame($origin, $map->of($first));
        self::assertSame(10, $map->of($second)?->offset);
        self::assertNull($map->of(new IntegerLiteral('1')));
    }

}
