<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountRename;

#[CoversClass(AccountRename::class)]
#[Medium]
final class AccountRenameTest extends TestCase
{
    public function testKeepsTheSourceAndDestinationIdentities(): void
    {
        $rename = new AccountRename(CurrentAccount::Authenticated, new AccountName('b', '%'));
        self::assertSame(CurrentAccount::Authenticated, $rename->from);
        self::assertEquals(new AccountName('b', '%'), $rename->to);
    }
}
