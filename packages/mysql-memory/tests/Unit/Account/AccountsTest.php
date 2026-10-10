<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Accounts;
use MySqlMemory\Account\Identity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Accounts::class)]
#[Small]
final class AccountsTest extends TestCase
{
    public function testInstalledHoldsTheAccountsOfANewServer(): void
    {
        self::assertSame(["root\0localhost", "root\0%", "mysql.infoschema\0localhost", "mysql.session\0localhost", "mysql.sys\0localhost"], array_keys(Accounts::installed()->accounts));
    }

    public function testFindAnswersNullForAMissingAccount(): void
    {
        self::assertNull(Accounts::installed()->find(new Identity('nobody', '%')));
    }

    public function testAddKeepsTheAccount(): void
    {
        $accounts = new Accounts();
        $accounts->add(new Account(new Identity('u', '%')));

        self::assertSame('u', $accounts->find(new Identity('u', '%'))?->identity->user);
    }

    public function testDropRevokesTheAccountAsARole(): void
    {
        $accounts = new Accounts();
        $accounts->add(new Account(new Identity('u', '%')));
        $accounts->add(Account::role(new Identity('r', '%')));
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), false);
        $accounts->defaults["u\0%"]["r\0%"] = new Identity('r', '%');
        $accounts->drop(new Identity('r', '%'));

        self::assertSame([[], []], [$accounts->edges, $accounts->defaults]);
    }

    public function testRenameKeepsTheRoles(): void
    {
        $accounts = new Accounts();
        $accounts->add(new Account(new Identity('u', '%')));
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), true);
        $accounts->rename(new Identity('u', '%'), new Identity('v', '%'));

        self::assertSame([["v\0%"], ["v\0%"]], [array_keys($accounts->accounts), array_keys($accounts->edges)]);
    }

    public function testGrantKeepsAnAdminOptionOnceHeld(): void
    {
        $accounts = new Accounts();
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), true);
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), false);

        self::assertTrue($accounts->roles(new Identity('u', '%'))["r\0%"][1]);
    }

    public function testRevokeRemovesTheRoleAndTheDefault(): void
    {
        $accounts = new Accounts();
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), false);
        $accounts->defaults["u\0%"]["r\0%"] = new Identity('r', '%');
        $accounts->revoke(new Identity('r', '%'), new Identity('u', '%'));

        self::assertSame([[], []], [$accounts->edges, $accounts->defaults]);
    }

    public function testRolesAnswersTheRolesGranted(): void
    {
        $accounts = new Accounts();
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), false);

        self::assertSame(["r\0%"], array_keys($accounts->roles(new Identity('u', '%'))));
    }

    public function testGrantedTellsWhetherAnAccountIsARoleOfAnother(): void
    {
        $accounts = new Accounts();
        $accounts->grant(new Identity('r', '%'), new Identity('u', '%'), false);

        self::assertSame([true, false], [$accounts->granted(new Identity('r', '%')), $accounts->granted(new Identity('u', '%'))]);
    }

    public function testReachesFollowsTheRolesOfRoles(): void
    {
        $accounts = new Accounts();
        $accounts->grant(new Identity('r1', '%'), new Identity('u', '%'), false);
        $accounts->grant(new Identity('r2', '%'), new Identity('r1', '%'), false);

        self::assertSame([true, false], [$accounts->reaches(new Identity('u', '%'), new Identity('r2', '%')), $accounts->reaches(new Identity('r2', '%'), new Identity('u', '%'))]);
    }

    public function testForgetRevokesTheGrantsOfARoutine(): void
    {
        $accounts = Accounts::installed();
        $accounts->find(new Identity('root', '%'))?->grants->routine('PROCEDURE', 'd', 'p')->add(['EXECUTE']);
        $accounts->forget('PROCEDURE', 'd', 'P');

        self::assertSame([], $accounts->find(new Identity('root', '%'))->grants->routines ?? null);
    }

    public function testCopyChangesApart(): void
    {
        $accounts = Accounts::installed();
        $copy = $accounts->copy();
        $copy->find(new Identity('root', '%'))?->grants->clear();

        self::assertSame(30, count($accounts->find(new Identity('root', '%'))->grants->global->names ?? []));
    }

    public function testRestoreTakesTheStateOfACopyBack(): void
    {
        $accounts = Accounts::installed();
        $copy = $accounts->copy();
        $accounts->drop(new Identity('root', '%'));
        $accounts->restore($copy);

        self::assertNotNull($accounts->find(new Identity('root', '%')));
    }

    public function testInstalledHoldsTheRootAccountsOnlyInMySql56(): void
    {
        $accounts = Accounts::installed(\SqlSemantics\Contract\GrammarRelease::MySql5651);

        self::assertSame(["root\0localhost", "root\0%"], array_keys($accounts->accounts));
        self::assertSame('mysql_native_password', $accounts->accounts["root\0%"]->plugin);
    }

    public function testLegacySystemAddsTheLockedAccountsOfMySql57(): void
    {
        $accounts = (new Accounts())->legacySystem();

        self::assertSame(["mysql.session\0localhost", "mysql.sys\0localhost"], array_keys($accounts->accounts));
        self::assertSame(['*THISISNOTAVALIDPASSWORDTHATCANBEUSEDHERE', true], [$accounts->accounts["mysql.sys\0localhost"]->hash, $accounts->accounts["mysql.sys\0localhost"]->locked]);
    }
}
