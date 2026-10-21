<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\AccountHistory;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks that account setup preserves operands and existing session identities.
 */
#[CoversNothing]
#[Small]
final class AccountHistoryTest extends TestCase
{
    /**
     * Establishes the account inspected by the unchanged SHOW GRANTS request.
     */
    public function testSetupCreatesTheInspectedAccountUnderItsOriginalIdentity(): void
    {
        $user = new M\Name\AccountName(new Name('ABCDE'));
        $node = new M\Utility\Show\Server\ShowGrants($user);
        $session = (new Instance('5.6.51'))->connect();
        $steps = (new AccountHistory())->steps($node, 1141, $session);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Account\CreateUser::class, $steps[0]);
        self::assertSame($user, $steps[0]->users[0]->user);
        self::assertSame([], (new AccountHistory())->steps($node, 1064, $session));
    }

    /**
     * Preserves the proxied account when supplying the missing grant.
     */
    public function testSetupGrantsTheMissingProxyWithoutReplacingEitherAccount(): void
    {
        $user = new M\Name\CurrentUser();
        $node = new M\Account\Privilege\RevokeProxy(false, $user, [new M\Account\User\UserSpecification(new M\Name\CurrentUser())]);
        $steps = (new AccountHistory())->steps($node, 1141, (new Instance())->connect());
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Account\Privilege\GrantProxy::class, $steps[0]);
        self::assertSame($user, $steps[0]->proxied);
    }

    /**
     * Creates only missing identities without using a modern conditional clause.
     */
    public function testSetupRetainsTheOriginalDropUserAccounts(): void
    {
        $users = [new M\Name\AccountName(new Name('missing')), new M\Name\CurrentUser()];
        $node = new M\Account\DropUser(false, $users);
        $session = (new Instance('5.6.51'))->connect();
        $steps = (new AccountHistory())->steps($node, 1396, $session);
        self::assertCount(1, $steps);
        self::assertInstanceOf(M\Account\CreateUser::class, $steps[0]);
        self::assertFalse($steps[0]->ifNotExists);
        self::assertCount(1, $steps[0]->users);
        self::assertSame($users[0], $steps[0]->users[0]->user);
        self::assertSame($users, $node->users);
        self::assertSame([], (new AccountHistory())->steps($node, 1064, $session));
        self::assertSame([], (new AccountHistory())->steps(new M\Literal\NullLiteral(), 1396, $session));
    }
}
