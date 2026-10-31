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


    public function testOfReadsTheOccurrenceLocationAndLetsExplicitOriginsOverrideIt(): void
    {
        $literal = new IntegerLiteral('1', inputSource: new \SqlSemantics\Statement\Source\Span(12, 1));
        self::assertSame(12, (new SourceMap())->of($literal)?->offset);
        $explicit = new Origin($literal, 24, 2);
        self::assertSame($explicit, (new SourceMap([$explicit]))->of($literal));
    }

    public function testRetainingInputNoticesDistinguishesEqualWarningsAtDifferentOccurrences(): void
    {
        $first = new IntegerLiteral('1');
        $second = new IntegerLiteral('1');
        $warning = self::createStub(\SqlSemantics\Statement\Fact\Warning::class);
        $firstNotice = new \SqlSemantics\Statement\Fact\Notice($warning, $first, inputSource: new \SqlSemantics\Statement\Source\Span(7, 0), fromInput: true);
        $secondNotice = new \SqlSemantics\Statement\Fact\Notice($warning, $second, inputSource: new \SqlSemantics\Statement\Source\Span(10, 0), fromInput: true);
        $derived = new \SqlSemantics\Statement\Fact\Notice($warning, $second, inputSource: new \SqlSemantics\Statement\Source\Span(10, 0));
        $statement = self::createStub(\SqlSemantics\Statement\AnalyzedStatement::class);
        $statement->method('hasStatementInformation')->willReturn(true);
        $statement->method('notices')->willReturn([$firstNotice, $secondNotice, $derived]);
        $map = new SourceMap(notices: [new \SqlSemantics\Statement\Source\SourceNotice($first, $warning, 7)]);
        $retained = $map->retainingInputNotices($statement)->retainingInputNotices($statement);
        self::assertCount(2, $retained->notices);
        self::assertSame([$first, $second], array_column($retained->notices, 'subject'));
        self::assertSame([7, 10], array_column($retained->notices, 'offset'));
    }

}
