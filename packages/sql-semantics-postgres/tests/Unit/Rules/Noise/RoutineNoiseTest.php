<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\RoutineNoise;

#[CoversClass(RoutineNoise::class)]
#[Small]
final class RoutineNoiseTest extends TestCase
{
    public function testPositionsListsTheNoiseOfTheFamily(): void
    {
        $positions = RoutineNoise::positions();
        self::assertSame([[0], [0], [2]], [$positions['common_func_opt_item: EXTERNAL SECURITY DEFINER'], $positions['opt_recheck: RECHECK'], $positions['routine_body_stmt_list: routine_body_stmt_list routine_body_stmt ;']]);
        self::assertCount(5, $positions);
    }
}
