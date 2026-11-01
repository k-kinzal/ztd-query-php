<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\Ranking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ranking::class)]
#[Small]
final class RankingTest extends TestCase
{
    public function testNaturalMatchesObservedInnoDbScores(): void
    {
        $ranking = new Ranking();
        self::assertSame(0.45528939366340637, $ranking->natural(['apple' => 2, 'banana' => 1], ['apple' => 2, 'banana' => 3], 6, ['apple' => 1], false));
        self::assertSame(0.5459084510803223, $ranking->natural(['apple' => 2, 'banana' => 1], ['apple' => 2, 'banana' => 3], 6, ['apple' => 1, 'banana' => 1], false));
        self::assertSame(0.062016263604164124, $ranking->natural(['apple' => 2], ['apple' => 2], 6, ['apple' => 2], false));
        self::assertSame(0.000000001885928302414186, $ranking->natural(['apple' => 1], ['apple' => 1], 1, ['apple' => 1], false));
        self::assertSame(0.0, $ranking->natural([], ['apple' => 2], 6, ['apple' => 1], false));
    }

    public function testMyIsamNormalizesLengthAndRejectsCommonTerms(): void
    {
        $ranking = new Ranking();
        self::assertSame(0.9215403199195862, $ranking->single($ranking->myIsam(['apple' => 2, 'banana' => 1, 'orange' => 1], 2, 2, 6)));
        self::assertSame(0.6700310707092285, $ranking->natural(['apple' => 1, 'banana' => 1, 'pear' => 1], ['apple' => 2], 6, ['apple' => 1], true));
        self::assertSame(0.0, $ranking->myIsam(['apple' => 1], 1, 3, 6));
    }

    public function testSingleRetainsBinary32Rounding(): void
    {
        self::assertSame(0.10000000149011612, (new Ranking())->single(0.1));
    }
}
