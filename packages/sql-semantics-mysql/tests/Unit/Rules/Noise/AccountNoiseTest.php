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
    public function testPositionsListsTheOptionalWords(): void
    {
        self::assertSame(['opt_privileges: PRIVILEGES' => [0], 'opt_and: AND_SYM' => [0], 'opt_acl_type: TABLE_SYM' => [0]], AccountNoise::positions());
    }

    public function testSynonymsListsNothing(): void
    {
        self::assertSame([], AccountNoise::synonyms());
    }
}
