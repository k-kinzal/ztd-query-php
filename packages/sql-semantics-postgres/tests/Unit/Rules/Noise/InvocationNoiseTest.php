<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\InvocationNoise;

#[CoversClass(InvocationNoise::class)]
#[Small]
final class InvocationNoiseTest extends TestCase
{
    public function testPositionsListsTheCitedNoiseWords(): void
    {
        $positions = InvocationNoise::positions();
        self::assertSame([2], $positions['func_application: func_name ( ALL func_arg_list opt_sort_clause )']);
        self::assertSame([0, 1, 2], $positions['opt_window_exclusion_clause: EXCLUDE NO OTHERS']);
        self::assertSame([2, 3, 4], $positions['json_quotes_clause_opt: OMIT QUOTES ON SCALAR STRING_P']);
        self::assertCount(15, $positions);
    }
}
