<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Identity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identity::class)]
#[Small]
final class IdentityTest extends TestCase
{
    public function testOfFoldsTheHostAndDefaultsItToEveryHost(): void
    {
        self::assertSame(['App', 'localhost'], [Identity::of('App', 'LocalHost')->user, Identity::of('App', 'LocalHost')->host]);
        self::assertSame('%', Identity::of('app', null)->host);
    }

    public function testKeyJoinsTheUserAndTheHost(): void
    {
        self::assertSame("a\0h", Identity::of('a', 'h')->key());
    }

    public function testQuotedEscapesAsTheMessagesOfAccountStatements(): void
    {
        self::assertSame("'a\\'b'@'%'", Identity::of("a'b", null)->quoted());
    }

    public function testBackquotedDoublesBackticks(): void
    {
        self::assertSame('`a``b`@`%`', Identity::of('a`b', null)->backquoted());
    }

    public function testTextJoinsTheNamesPlain(): void
    {
        self::assertSame('a@%', Identity::of('a', null)->text());
    }

    public function testEscapeWritesBackslashEscapes(): void
    {
        self::assertSame('a\\"\\\'\\\\\\Z\\0\\n\\r', Identity::escape("a\"'\\\x1A\0\n\r"));
    }
}
