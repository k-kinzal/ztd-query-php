<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Identifiers;

#[CoversClass(Identifiers::class)]
#[Small]
final class IdentifiersTest extends TestCase
{
    public function testDecodeReadsABacktickWordWithDoubledBackticks(): void
    {
        self::assertSame('order items', (new Identifiers())->decode('`order items`'));
        self::assertSame('a`b', (new Identifiers())->decode('`a``b`'));
        self::assertSame('', (new Identifiers())->decode('``'));
        self::assertSame('select', (new Identifiers())->decode('`select`'));
    }

    public function testDecodeReadsADoubleQuotedWordWithDoubledQuotes(): void
    {
        self::assertSame('Foo', (new Identifiers())->decode('"Foo"'));
        self::assertSame('a"b', (new Identifiers())->decode('"a""b"'));
        self::assertSame('a\\b', (new Identifiers())->decode('"a\\b"'));
    }

    public function testDecodeKeepsABareWordAsWritten(): void
    {
        self::assertSame('Users', (new Identifiers())->decode('Users'));
        self::assertSame('1abc', (new Identifiers())->decode('1abc'));
        self::assertSame('a$b_c', (new Identifiers())->decode('a$b_c'));
        self::assertSame("caf\xC3\xA9", (new Identifiers())->decode("caf\xC3\xA9"));
        self::assertSame("`\xC3\xA9`", (new Identifiers())->decode("``\xC3\xA9``"));
        self::assertSame('`', (new Identifiers())->decode('`'));
        self::assertSame('', (new Identifiers())->decode(''));
    }
}
