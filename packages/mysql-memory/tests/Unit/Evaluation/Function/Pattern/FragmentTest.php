<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Fragment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fragment::class)]
#[Small]
final class FragmentTest extends TestCase
{
    public function testEmptyMatchesNoCharacter(): void
    {
        $fragment = Fragment::empty('\A');

        self::assertSame(['\A', 0, 0, false], [$fragment->source, $fragment->minimum, $fragment->maximum, $fragment->quantifiable]);
    }

    public function testSequenceAddsTheLengths(): void
    {
        $fragment = Fragment::sequence([new Fragment('a'), new Fragment('b+', 1, null), Fragment::empty('\z')]);

        self::assertSame(['ab+\z', 2, null], [$fragment->source, $fragment->minimum, $fragment->maximum]);
    }

    public function testChoiceTakesTheShortestAndTheLongestBranch(): void
    {
        $fragment = Fragment::choice([new Fragment('a'), new Fragment('bcd', 3, 3)]);

        self::assertSame(['a|bcd', 1, 3], [$fragment->source, $fragment->minimum, $fragment->maximum]);
    }
}
