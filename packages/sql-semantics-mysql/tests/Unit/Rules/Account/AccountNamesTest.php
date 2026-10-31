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
    public function testDeriveRetainsTheAccountListAndItsOrderedConditions(): void
    {
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile(GrammarRelease::MySql5651), [new Name('test')]);
        $long = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name(str_repeat('u', 17)));
        $host = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('u'), new Name('bad@host'));
        $statement = \SqlSemantics\Facade\Semantics::fromStatement($context, new \SqlSemantics\Platform\MySql\Statement\Account\DropUser(false, [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), $long, $host]));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\DropUser::class, $statement);
        self::assertCount(3, $statement->users);
        self::assertEquals([new AccountNameFailure(str_repeat('u', 17), false, 16), new AccountNameFailure('bad@host', true)], $statement->diagnostics());
        self::assertSame(['The account user exceeds its limit of 16.', 'The account host contains an illegal at sign.'], array_map(static fn ($warning): string => $warning->message(), $statement->warnings()));
    }

    public function testRolesRetainsCompleteGrantsAndContinuesAfterEachRoleCondition(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("GRANT 'r'@'bad@host', 's'@'other@host' TO CURRENT_USER");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles::class, $statement);
        self::assertCount(2, $statement->roles);
        self::assertCount(2, $statement->statementInformation()->notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $statement->statementInformation()->notices[0]->warning);
        self::assertFalse($statement->statementInformation()->notices[0]->warning->aborts);
        self::assertFalse($statement->statementInformation()->notices[0]->warning->endsProgramStatement);
        self::assertSame("GRANT 'r'@'bad@host', 's'@'other@host' TO CURRENT_USER", $statement->toString());
    }

}
