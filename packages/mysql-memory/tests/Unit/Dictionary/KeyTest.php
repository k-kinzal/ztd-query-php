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
}
