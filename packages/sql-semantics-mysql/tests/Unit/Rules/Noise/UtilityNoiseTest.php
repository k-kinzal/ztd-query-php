<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\UtilityNoise;

#[CoversClass(UtilityNoise::class)]
#[Small]
final class UtilityNoiseTest extends TestCase
{
    public function testPositionsDeclaresNoNoise(): void
    {
        self::assertSame([], UtilityNoise::positions());
    }

    public function testSynonymsMapTheSynonymKeywords(): void
    {
        self::assertSame([0 => 'SESSION_SYM'], UtilityNoise::synonyms()['option_type: LOCAL_SYM']);
        self::assertSame([0 => 'DESCRIBE'], UtilityNoise::synonyms()['describe_command: DESC']);
        self::assertSame([0 => 'BINARY_SYM'], UtilityNoise::synonyms()['master_or_binary: MASTER_SYM']);
    }
}
