<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Key::class)]
#[Small]
final class KeyTest extends TestCase
{
    public function testUniqueHoldsForPrimaryAndUniqueKeys(): void
    {
        $primary = new Key('PRIMARY', KeyKind::Primary, [0]);
        $unique = new Key('u', KeyKind::Unique, [1]);

        self::assertSame([true, true], [$primary->unique(), $unique->unique()]);
    }

    public function testUniqueFailsForOtherIndexes(): void
    {
        $index = new Key('i', KeyKind::Index, [0]);
        $text = new Key('f', KeyKind::FullText, [1]);
        $spatial = new Key('s', KeyKind::Spatial, [2]);

        self::assertSame([false, false, false], [$index->unique(), $text->unique(), $spatial->unique()]);
    }

    public function testDuplicatesHoldsForAKeyOfTheSameKindOverTheSameColumnsInTheSameWay(): void
    {
        $key = new Key('a', KeyKind::Index, [0, 1], [null, 3], [false, true]);

        self::assertTrue($key->duplicates(new Key('b', KeyKind::Index, [0, 1], [null, 3], [false, true])));
        self::assertTrue((new Key('a', KeyKind::Unique, [0]))->duplicates(new Key('b', KeyKind::Unique, [0], [null], [false])));
        self::assertFalse($key->duplicates(new Key('b', KeyKind::Unique, [0, 1], [null, 3], [false, true])));
        self::assertFalse($key->duplicates(new Key('b', KeyKind::Index, [0, 1], [null, 4], [false, true])));
        self::assertFalse($key->duplicates(new Key('b', KeyKind::Index, [0, 1], [null, 3])));
        self::assertFalse($key->duplicates(new Key('b', KeyKind::Index, [1, 0], [null, 3], [false, true])));
    }
}
