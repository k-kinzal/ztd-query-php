<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure;

#[CoversClass(AccountNameFailure::class)]
#[Small]
final class AccountNameFailureTest extends TestCase
{
    public function testMessageDistinguishesTheNamePartAndTheMalformedHost(): void
    {
        self::assertSame('The account user exceeds its limit of 32.', (new AccountNameFailure(str_repeat('a', 33), false, 32))->message());
        self::assertSame('The account host exceeds its limit of 255.', (new AccountNameFailure(str_repeat('a', 256), true, 255))->message());
        self::assertSame('The account host contains an illegal at sign.', (new AccountNameFailure('bad@host', true))->message());
    }
}
