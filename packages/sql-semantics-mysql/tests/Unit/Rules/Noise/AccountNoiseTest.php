<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\AccountNoise;

#[CoversClass(AccountNoise::class)]
#[Small]
final class AccountNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], AccountNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], AccountNoise::synonyms());
    }
}
