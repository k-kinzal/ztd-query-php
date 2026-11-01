<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special;

use MySqlMemory\Evaluation\Function\Special\GtidSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(GtidSet::class)]
#[Small]
final class GtidSetTest extends TestCase
{
    public function testParseReadsIntervalsTagsAndBlanks(): void
    {
        self::assertSame([
            '3e11fa47-71ca-11e1-9e33-c80aa9429562:1-5',
            '00000000-0000-0000-0000-000000000001:2,' . "\n" . '3e11fa47-71ca-11e1-9e33-c80aa9429562:1-3',
            '3e11fa47-71ca-11e1-9e33-c80aa9429562:2-4:tag_1:1-3:5',
            '',
            '3e11fa47-71ca-11e1-9e33-c80aa9429562:1-3',
            '',
        ], [
            GtidSet::parse('3E11FA47-71CA-11E1-9E33-C80AA9429562:5:1-3:4', true)?->text(),
            GtidSet::parse(' 3e11fa47-71ca-11e1-9e33-c80aa9429562 : 1 - 3 , 00000000-0000-0000-0000-000000000001 : 2 ', true)?->text(),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:Tag_1:1-3:5,3e11fa47-71ca-11e1-9e33-c80aa9429562:2-4', true)?->text(),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:3-1, ,3e11fa47-71ca-11e1-9e33-c80aa9429562:1-', true)?->text(),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:+1-+3', true)?->text(),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:a', true)?->text(),
        ]);
    }

    public function testParseRefusesMalformedSets(): void
    {
        self::assertSame([null, null, null, null, null, null, null, null, null], [
            GtidSet::parse('x', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:0', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:1-3-5', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:1 3', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:9223372036854775807', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:abcdefghijklmnopqrstuvwxyzabcdefg:1', true),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:a:1', false),
            GtidSet::parse('{3e11fa47-71ca-11e1-9e33-c80aa9429562}:1', true),
        ]);
    }

    public function testIntervalReadsTheNumbersAndThePositionAfter(): void
    {
        self::assertSame([[1, 5, 6], [3, 0, 2], [4, 4, 3], null, null, null], [GtidSet::interval('1 - 5 :', 0), GtidSet::interval('3-', 0), GtidSet::interval(':+4', 1), GtidSet::interval('0', 0), GtidSet::interval('x', 0), GtidSet::interval('1-9223372036854775807', 0)]);
    }

    public function testBlankSkipsWhiteSpace(): void
    {
        self::assertSame(4, GtidSet::blank("a \t\nb", 1));
    }

    public function testNumberReadsDigitsUpToTheLargestTransaction(): void
    {
        self::assertSame([0, 7, 9223372036854775806, null], [GtidSet::number(''), GtidSet::number('007'), GtidSet::number('9223372036854775806'), GtidSet::number('9223372036854775807')]);
    }

    public function testNormalizedMergesAdjacentIntervals(): void
    {
        self::assertSame(['u' => ['' => [[1, 5]]]], (new GtidSet(['u' => ['' => [[4, 5], [1, 3]]], 'v' => ['' => []]]))->normalized()->intervals);
    }

    public function testSubtractRemovesTheTransactionsOfTheOther(): void
    {
        self::assertSame('3e11fa47-71ca-11e1-9e33-c80aa9429562:13-14:16-18', GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:10-20', true)?->subtract(GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:1-12:15:19-30', true) ?? new GtidSet())->text());
    }

    public function testWithinTellsWhetherTheSetIsASubset(): void
    {
        self::assertSame([true, false, true], [
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:3', true)?->within(GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:1-5', true) ?? new GtidSet()),
            GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:a:1', true)?->within(GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:1-5', true) ?? new GtidSet()),
            (new GtidSet())->within(new GtidSet()),
        ]);
    }

    public function testTextListsTheUntaggedIntervalsFirst(): void
    {
        self::assertSame('3e11fa47-71ca-11e1-9e33-c80aa9429562:9:a:2:b:1:c:3', GtidSet::parse('3e11fa47-71ca-11e1-9e33-c80aa9429562:b:1,3e11fa47-71ca-11e1-9e33-c80aa9429562:a:2:c:3,3e11fa47-71ca-11e1-9e33-c80aa9429562:9', true)?->text());
    }
}
