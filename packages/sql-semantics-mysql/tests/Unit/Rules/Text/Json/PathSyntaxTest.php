<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Text\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Text\Json\PathSyntax;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind;

#[CoversClass(PathSyntax::class)]
#[Small]
final class PathSyntaxTest extends TestCase
{
    public function testParseReadsTheRootAndSteps(): void
    {
        $path = PathSyntax::parse('$.a[0]');
        self::assertSame([Kind::Member, Kind::Cell], array_map(static fn ($leg): Kind => $leg->kind, $path->legs));
    }

    public function testMemberReadsANameQuotedOrNot(): void
    {
        $at = 0;
        $quoted = PathSyntax::member('."a\"b".c', $at);
        $plain = PathSyntax::member('."a\"b".c', $at);

        self::assertSame(['a"b', 'c', 9], [$quoted->name, $plain->name, $at]);
    }

    public function testCellReadsACellARangeOrEveryCell(): void
    {
        $at = 0;
        $range = PathSyntax::cell('[1 to 2][*]', $at);
        $every = PathSyntax::cell('[1 to 2][*]', $at);

        self::assertNotNull($range->from);
        self::assertNotNull($range->to);
        self::assertSame([Kind::Range, [false, 1], [false, 2], Kind::AnyCell, 11], [$range->kind, [$range->from->last, $range->from->offset], [$range->to->last, $range->to->offset], $every->kind, $at]);
    }

    public function testIndexReadsANumberOrLast(): void
    {
        $at = 0;
        $last = PathSyntax::index('last-3]', $at);
        $position = $at;
        $at = 0;

        $first = PathSyntax::index('07', $at);
        self::assertSame([true, 3, 6, false, 7, 2], [$last->last, $last->offset, $position, $first->last, $first->offset, $at]);
    }

    public function testCloseReadsTheClosingBracket(): void
    {
        $at = 1;
        PathSyntax::close(' ]', $at);

        self::assertSame(2, $at);
    }

    public function testDescendantsReadsTwoStarsBeforeALeg(): void
    {
        $at = 0;

        self::assertSame([Kind::Descendants, 2], [PathSyntax::descendants('**.a', $at)->kind, $at]);
    }

}
