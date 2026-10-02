<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\Sqlite\Rules\DefinitionNoise;

#[CoversClass(DefinitionNoise::class)]
#[Small]
final class DefinitionNoiseTest extends TestCase
{
    public function testPositionsNameOnlyProductionsOfTheGrammar(): void
    {
        $productions = Productions::load(dirname(__DIR__, 3) . '/resources/productions/sqlite-3.47.2.php')->all();

        self::assertSame([], array_values(array_diff(array_keys(DefinitionNoise::positions()), $productions)));
    }

    public function testPositionsDeclareTheOptionalTransactionKeywordAsNoise(): void
    {
        self::assertSame([0], DefinitionNoise::positions()['trans_opt: TRANSACTION']);
        self::assertArrayNotHasKey('trans_opt: TRANSACTION nm', DefinitionNoise::positions());
    }
}
