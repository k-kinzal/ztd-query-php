<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Scanner\Directives;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Directives::class)]
#[Small]
final class DirectivesTest extends TestCase
{
    public function testCanonical(): void
    {
        self::assertSame('token', Directives::canonical('token'));
        self::assertSame('token', Directives::canonical('term'));
        self::assertSame('nonassoc', Directives::canonical('binary'));
        self::assertSame('pure-parser', Directives::canonical('pure_parser'));
        self::assertSame('header', Directives::canonical('defines'));
        self::assertSame('expect-rr', Directives::canonical('expect_rr'));
        self::assertSame('name-prefix', Directives::canonical('name_prefix'));
        self::assertNull(Directives::canonical('tokens'));
        self::assertNull(Directives::canonical(''));
        self::assertContains('glr-parser', Directives::FLAGS);
        self::assertArrayHasKey('header', Directives::OPTIONS);
        self::assertContains('output', Directives::EQUAL_OPTIONAL);
    }
}
