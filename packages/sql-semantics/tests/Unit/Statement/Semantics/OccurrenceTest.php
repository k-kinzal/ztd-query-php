<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Semantics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Semantics\Occurrence::class)]
#[Medium]
final class OccurrenceTest extends TestCase
{
    public function testSourceReturnsTheRangeOwnedByTheOccurrence(): void
    {
        $source = new \SqlSemantics\Statement\Source\Span(7, 2);
        $literal = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('12', inputSource: $source);
        self::assertSame($source, $literal->source());
        self::assertNull((new \SqlSemantics\Statement\Script([]))->source());
    }

    public function testHasBindingFollowsEachOccurrenceRole(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t');
        self::assertTrue($statement->hasBinding());
        self::assertNotNull($statement->field(0)->expression);
        self::assertTrue($statement->field(0)->expression->hasBinding());
        self::assertTrue($statement->singleNamedInput()->hasBinding());
        self::assertFalse((new \SqlSemantics\Construction\Occurrences())->copy($statement)->hasBinding());
    }
}
