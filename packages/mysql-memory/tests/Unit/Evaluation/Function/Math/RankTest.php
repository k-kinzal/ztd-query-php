<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Function\Math\Rank;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rank::class)]
#[Small]
final class RankTest extends TestCase
{
    public function testDatelessTellsAValueThatIsNoDate(): void
    {
        self::assertSame([true, false], [(new Rank('', 'abc', false, null))->dateless(), (new Rank('20200101000000000000', '2020-01-01', false, [2020, 1, 1, 0, 0, 0, 0]))->dateless()]);
    }
}
