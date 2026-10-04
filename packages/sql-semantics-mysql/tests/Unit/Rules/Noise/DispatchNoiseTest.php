<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Rules\Noise\DispatchNoise;

#[CoversClass(DispatchNoise::class)]
#[Medium]
final class DispatchNoiseTest extends TestCase
{
    public function testPositionsDeclareTheStatementTerminatorAsNoise(): void
    {
        self::assertSame([1], DispatchNoise::positions()['sql_statement: simple_statement_or_begin ; opt_end_of_input']);
        self::assertSame([1], DispatchNoise::positions()['query: verb_clause ; opt_end_of_input']);
        self::assertCount(2, DispatchNoise::positions());
    }

    public function testPositionsNameOnlyTerminalPositionsOfProductionsOfTheGrammars(): void
    {
        $productions = array_merge(
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-5.7.44.php')->all(),
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-8.0.44.php')->all(),
        );
        $arity = array_map(static fn (string $signature): int => count(explode(' ', trim(substr($signature, (int) strpos($signature, ':') + 1)))), array_combine(array_keys(DispatchNoise::positions()), array_keys(DispatchNoise::positions())));

        self::assertSame([], array_values(array_diff(array_keys(DispatchNoise::positions()), $productions)));
        self::assertSame([], array_filter(DispatchNoise::positions(), static fn (array $positions, string $signature): bool => max(-1, ...$positions) >= $arity[$signature], ARRAY_FILTER_USE_BOTH));
    }

    public function testSynonymsListsNothingForTheRootProductions(): void
    {
        self::assertSame([], DispatchNoise::synonyms());
    }
}
