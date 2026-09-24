<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Serialization\Definition\Account\Accounts;

#[CoversClass(Accounts::class)]
#[Medium]
final class AccountsTest extends TestCase
{
    public function testWriteQuotesNamesAndKeepsSymbols(): void
    {
        self::assertSame("'o''k'@'h'", Accounts::write(new AccountName("o'k", 'h'))->toString());
        self::assertSame('CURRENT_USER', Accounts::write(CurrentAccount::Authenticated)->toString());
        self::assertSame('USER()', Accounts::write(ClientAccount::Connected)->toString());
    }

    public function testListSeparatesAccountsWithCommas(): void
    {
        self::assertSame("'a', CURRENT_USER", Accounts::list([new AccountName('a'), CurrentAccount::Authenticated])->toString());
    }

    public function testCountWritesAPlainDecimal(): void
    {
        self::assertSame('65535', Accounts::count(65535)->toString());
    }
}
