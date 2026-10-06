<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Introducers;

#[CoversClass(Introducers::class)]
#[Small]
final class IntroducersTest extends TestCase
{
    public function testCharsetDropsTheUnderscoreAndLowersTheCase(): void
    {
        self::assertSame('utf8mb4', (new Introducers())->charset('_utf8mb4'));
        self::assertSame('utf8mb4', (new Introducers())->charset('_UTF8MB4'));
        self::assertSame('latin1', (new Introducers())->charset('_Latin1'));
        self::assertSame('binary', (new Introducers())->charset('_BINARY'));
    }

    public function testKnownAcceptsTheCompiledCharacterSetsInLowerCase(): void
    {
        self::assertTrue((new Introducers())->known('utf8mb4'));
        self::assertTrue((new Introducers())->known('binary'));
        self::assertTrue((new Introducers())->known('latin1'));
        self::assertTrue((new Introducers())->known('gb18030'));
        self::assertTrue((new Introducers())->known((new Introducers())->charset('_UTF8')));
    }

    public function testKnownRejectsAnUnknownOrUpperCaseName(): void
    {
        self::assertFalse((new Introducers())->known('foo'));
        self::assertFalse((new Introducers())->known('UTF8MB4'));
        self::assertFalse((new Introducers())->known('_utf8mb4'));
        self::assertFalse((new Introducers())->known(''));
    }
}
