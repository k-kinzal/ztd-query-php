<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ResourceGroupName::class)]
#[Small]
final class ResourceGroupNameTest extends TestCase
{
    public function testAcceptsCountsBytesRatherThanCharacters(): void
    {
        self::assertTrue(ResourceGroupName::accepts(new Name(str_repeat('a', 64))));
        self::assertFalse(ResourceGroupName::accepts(new Name(str_repeat('a', 65))));
        self::assertTrue(ResourceGroupName::accepts(new Name(str_repeat('猫', 21))));
        self::assertFalse(ResourceGroupName::accepts(new Name(str_repeat('猫', 22))));
        self::assertTrue(ResourceGroupName::accepts(new Name(str_repeat('😀', 64))));
        self::assertFalse(ResourceGroupName::accepts(new Name(str_repeat('😀', 65))));
    }

    public function testDecodedReplacesSupplementaryCharactersAndRetainsWhitespace(): void
    {
        $spelling = new Name(' 猫😀 ');
        $name = new ResourceGroupName($spelling);

        self::assertSame(' 猫? ', ResourceGroupName::decoded($spelling));
        self::assertSame(' 猫? ', $name->value);
        self::assertSame($spelling, $name->spelling);
        self::assertSame('', (new ResourceGroupName(new Name('')))->value);
    }

    public function testRejectsAnIdentifierOutsideTheByteDomain(): void
    {
        $this->expectExceptionMessage('A resource-group name occupies at most 64 utf8mb3 bytes.');

        new ResourceGroupName(new Name(str_repeat('é', 33)));
    }

    public function testDeriveReportsTheCharacterConversion(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP RESOURCE GROUP `😀`');

        self::assertSame("Cannot convert string '\\xF0\\x9F\\x98\\x80' from utf8mb4 to utf8mb3", $operation->facts->warnings[0]->message());
    }

    public function testRenderRetainsTheOriginalDecodedOperand(): void
    {
        self::assertSame('DROP RESOURCE GROUP `猫😀`', (new Semantics(Dialect::MySql))->analyze('DROP RESOURCE GROUP `猫😀`')->toString());
    }
    public function testRejectsNulInTheIdentifier(): void
    {
        $this->expectExceptionMessage('A resource-group identifier cannot contain NUL.');

        new ResourceGroupName(new Name("zero\0tail"));
    }

}
