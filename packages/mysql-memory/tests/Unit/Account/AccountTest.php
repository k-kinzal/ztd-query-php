<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Identity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
#[Small]
final class AccountTest extends TestCase
{
    public function testRoleIsLockedWithAnExpiredPassword(): void
    {
        $role = Account::role(new Identity('r', '%'));

        self::assertSame([true, true, '', 'caching_sha2_password'], [$role->locked, $role->expired, $role->hash, $role->plugin]);
    }

    public function testCopyChangesApart(): void
    {
        $account = new Account(new Identity('u', '%'));
        $copy = $account->copy();
        $copy->grants->global->add(['SELECT']);
        $copy->locked = true;

        self::assertSame([[], false], [$account->grants->global->names, $account->locked]);
    }
    public function testStorageIdentityRetainsARowRenamedThroughAnotherHostSpelling(): void
    {
        $account = new Account(new Identity('a', '猫'));
        self::assertSame('?', $account->storageIdentity()->host);
        $account->storedName = new Identity('b', '?');
        $account->identity = new Identity('c', '犬');

        self::assertSame('b', $account->storageIdentity()->user);
        self::assertSame('c', $account->identity->user);
    }

}
