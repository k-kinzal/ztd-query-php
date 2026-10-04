<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Rules\Noise\QueryNoise;

#[CoversClass(QueryNoise::class)]
#[Medium]
final class QueryNoiseTest extends TestCase
{
    public function testPositionsDeclareTheOptionalAliasKeywordAsNoise(): void
    {
        self::assertSame([0], QueryNoise::positions()['select_alias: AS ident']);
        self::assertSame([0], QueryNoise::positions()['select_alias: AS TEXT_STRING_sys']);
        self::assertSame([0], QueryNoise::positions()['select_alias: AS TEXT_STRING_validated']);
        self::assertSame([0], QueryNoise::positions()['table_alias: AS']);
        self::assertCount(4, QueryNoise::positions());
        self::assertArrayNotHasKey('select_alias: ident', QueryNoise::positions());
    }

    public function testPositionsNameOnlyTerminalPositionsOfProductionsOfTheGrammars(): void
    {
        $productions = array_merge(
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-5.7.44.php')->all(),
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-8.0.44.php')->all(),
        );
        $arity = array_map(static fn (string $signature): int => count(explode(' ', trim(substr($signature, (int) strpos($signature, ':') + 1)))), array_combine(array_keys(QueryNoise::positions()), array_keys(QueryNoise::positions())));

        self::assertSame([], array_values(array_diff(array_keys(QueryNoise::positions()), $productions)));
        self::assertSame([], array_filter(QueryNoise::positions(), static fn (array $positions, string $signature): bool => max(-1, ...$positions) >= $arity[$signature], ARRAY_FILTER_USE_BOTH));
    }

    public function testSynonymsListsNothingForTheQueryProductions(): void
    {
        self::assertSame([], QueryNoise::synonyms());
    }
}
