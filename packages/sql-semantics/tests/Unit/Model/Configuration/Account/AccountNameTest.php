<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;

#[CoversClass(AccountName::class)]
#[Medium]
final class AccountNameTest extends TestCase
{
    public function testKeepsExplicitAndOmittedHostsDistinct(): void
    {
        $omitted = new AccountName('reader');
        $explicit = new AccountName('reader', '%');
        self::assertNull($omitted->host);
        self::assertSame('%', $explicit->host);
        self::assertSame('reader', $explicit->username);
    }
}
