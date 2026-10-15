<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Account\AccountNames;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AccountNames::class)]
#[Small]
final class AccountNamesTest extends TestCase
{
    public function testLimitsDistinguishesTheTwoLegacyDomains(): void
    {
        $names = new AccountNames();
        self::assertSame([[16, 60], [32, 60], [32, 255]], [$names->limits(GrammarRelease::MySql5651), $names->limits(GrammarRelease::MySql5744), $names->limits(GrammarRelease::MySql847)]);
    }

    public function testProblemMeasuresUserCharactersAndHostBytes(): void
    {
        $names = new AccountNames();
        self::assertNull($names->problem(new Name(str_repeat('猫', 32)), new Name(str_repeat('猫', 85))));
        self::assertEquals(new AccountNameFailure(str_repeat('猫', 33), false, 32), $names->problem(new Name(str_repeat('猫', 33)), null));
        self::assertEquals(new AccountNameFailure(str_repeat('猫', 86), true, 255), $names->problem(new Name('u'), new Name(str_repeat('猫', 86))));
        self::assertEquals(new AccountNameFailure('a@b', true), $names->problem(new Name('u'), new Name('a@b')));
    }

    public function testProblemChecksOriginalQualifiedOperandsOnlyInMySql56(): void
    {
        $names = new AccountNames();
        $user = new Name(' ' . str_repeat('u', 16) . ' ');
        self::assertNull($names->problem($user, null, GrammarRelease::MySql5651));
        self::assertEquals(new AccountNameFailure($user->value, false, 16), $names->problem($user, new Name('%'), GrammarRelease::MySql5651));
        self::assertNull($names->problem($user, new Name('%'), GrammarRelease::MySql5744));
        self::assertEquals(new AccountNameFailure(str_repeat('h', 61), true, 60), $names->problem(new Name('u'), new Name(str_repeat('h', 61)), GrammarRelease::MySql5744));
    }
}
