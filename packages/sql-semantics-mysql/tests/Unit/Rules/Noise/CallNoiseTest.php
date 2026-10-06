<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\CallNoise;

#[CoversClass(CallNoise::class)]
#[Small]
final class CallNoiseTest extends TestCase
{
    public function testPositionsListsNoParenthesesSinceTheModelKeepsThem(): void
    {
        self::assertSame([], CallNoise::positions());
    }

    public function testSynonymsMapSubstringAndTheIntervalFormsOfAdddateAndSubdate(): void
    {
        $synonyms = CallNoise::synonyms();

        self::assertSame([3 => ',', 5 => ','], $synonyms['function_call_nonkeyword: SUBSTRING ( expr FROM expr FOR_SYM expr )']);
        self::assertSame([0 => 'DATE_ADD_INTERVAL'], $synonyms['function_call_nonkeyword: ADDDATE_SYM ( expr , INTERVAL_SYM expr interval )']);
        self::assertCount(4, $synonyms);
    }
}
