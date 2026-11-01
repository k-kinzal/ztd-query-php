<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Session\State\DatabaseGrants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseGrants::class)]
#[Small]
final class DatabaseGrantsTest extends TestCase
{
    public function testRefusedRetainsTheObservedDatabaseFailureValue(): void
    {
        $history = new DatabaseGrants();

        $history->refused(['DATABASE', 'd', '']);
        $history->refused(['TABLE', 'd', 't']);

        self::assertSame(['TRIGGER' => true], $history->last->names);
    }

    public function testRememberCopiesTheGrantIndependentlyOfLaterAccountChanges(): void
    {
        $history = new DatabaseGrants();
        $account = new Account(new Identity('u', '%'));
        $account->grants->database('d')->add(['SELECT']);
        $account->grants->database('d')->grantOption = true;

        $history->remember($account, ['DATABASE', 'd', '']);
        $account->grants->database('d')->remove(['SELECT']);

        self::assertSame(['SELECT' => true], $history->last->names);
        self::assertTrue($history->last->grantOption);
    }

    public function testReuseCopiesAnAbsentGrantAndPreservesAnExistingOne(): void
    {
        $history = new DatabaseGrants(new Privileges(['SELECT' => true]));
        $account = new Account(new Identity('u', '%'));

        $history->reuse($account, 'd');
        $history->last->add(['UPDATE']);
        $history->reuse($account, 'd');

        self::assertSame(['SELECT' => true], $account->grants->database('d')->names);
        self::assertSame(['SELECT' => true], $history->last->names);
    }

    public function testClearForgetsTheValueWithoutPublishingAnEmptyGrant(): void
    {
        $history = new DatabaseGrants(new Privileges(['SELECT' => true]));
        $account = new Account(new Identity('u', '%'));

        $history->clear();
        $history->reuse($account, 'd');

        self::assertSame([], $account->grants->databases);
        self::assertTrue($history->last->empty());
    }
}
