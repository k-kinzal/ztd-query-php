<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NameConversion::class)]
#[Small]
final class NameConversionTest extends TestCase
{
    public function testCodeReturnsTheCharacterConversionWarning(): void
    {
        self::assertSame(1300, (new NameConversion(new Name('😀')))->code());
    }

    public function testMessageBoundsTheDisplayButRetainsTheOperand(): void
    {
        $name = new Name('ab😀cd');
        $warning = new NameConversion($name);

        self::assertSame("Cannot convert string 'ab\\xF0\\x9F\\x98\\x80...' from utf8mb4 to utf8mb3", $warning->message());
        self::assertSame($name, $warning->name);
    }

    public function testMessageUsesTheLegacyCharacterStringDiagnostic(): void
    {
        self::assertSame("Invalid utf8mb4 character string: '\\xF0\\x9F\\x98\\x80'", (new NameConversion(new Name('😀'), true))->message());
    }
}
