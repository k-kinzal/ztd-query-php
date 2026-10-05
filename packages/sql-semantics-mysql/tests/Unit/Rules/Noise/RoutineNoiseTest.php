<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\RoutineNoise;

#[CoversClass(RoutineNoise::class)]
#[Small]
final class RoutineNoiseTest extends TestCase
{
    public function testPositionsListsTheFetchWordsAndTheSqlstateValueWord(): void
    {
        self::assertSame([
            'sp_opt_fetch_noise: NEXT_SYM FROM' => [0, 1],
            'sp_opt_fetch_noise: FROM' => [0],
            'opt_value: VALUE_SYM' => [0],
        ], RoutineNoise::positions());
    }

    public function testSynonymsListsNothing(): void
    {
        self::assertSame([], RoutineNoise::synonyms());
    }
}
