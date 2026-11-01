<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Catalog::class)]
#[Small]
final class CatalogTest extends TestCase
{
    public function testRegisteredKnowsTheDynamicPrivilegesInAnyCase(): void
    {
        self::assertTrue((new Catalog())->registered('backup_admin'));
        self::assertFalse((new Catalog())->registered('FOO_BAR'));
    }

    public function testOrderedAnswersThePrivilegesInTheOrderOfShowGrants(): void
    {
        self::assertSame(['SELECT', 'RELOAD', 'DROP ROLE'], (new Catalog())->ordered(['DROP ROLE' => true, 'RELOAD' => true, 'SELECT' => true]));
    }

    public function testStaticsLeavesOutTheRolePrivilegesInMySql57(): void
    {
        self::assertSame([28, 30], [count((new Catalog(\SqlSemantics\Contract\GrammarRelease::MySql5744))->statics()), count((new Catalog())->statics())]);
    }

    public function testDynamicsAnswersNoneInMySql56(): void
    {
        self::assertSame([], (new Catalog(\SqlSemantics\Contract\GrammarRelease::MySql5651))->dynamics());
    }

    public function testDynamicsKnowsTheDefinerPrivilegeOfMySql80(): void
    {
        $catalog = new Catalog(\SqlSemantics\Contract\GrammarRelease::MySql8044);

        self::assertTrue($catalog->registered('SET_USER_ID'));
        self::assertFalse($catalog->registered('SET_ANY_DEFINER'));
        self::assertFalse($catalog->registered('TRANSACTION_GTID_TAG'));
    }

    public function testLegacyTellsMySql56And57(): void
    {
        self::assertSame([true, false], [(new Catalog(\SqlSemantics\Contract\GrammarRelease::MySql5651))->legacy(), (new Catalog())->legacy()]);
    }
}
