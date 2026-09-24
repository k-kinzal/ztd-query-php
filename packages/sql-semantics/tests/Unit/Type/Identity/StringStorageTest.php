<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\StringStorage;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(StringStorage::class)]
#[Medium]
final class StringStorageTest extends TestCase
{
    public function testNameRetainsTheBitStringFamilyWithAnIdentifierLength(): void
    {
        $type = new StringStorage(BuiltinIdentity::Varbit, new IdentifierParameter('12'));
        self::assertSame('varbit', $type->name());
        self::assertSame('varbit("12")', \SqlSemantics\Serialization\TypeDeclaration::write(new TypeDescriptor(Dialect::PostgreSql, $type))->toString());
    }

    public function testRejectsIdentifierLengthForCharacterKeywordSyntax(): void
    {
        $this->expectException(InvalidStructure::class);
        new StringStorage(BuiltinIdentity::Varchar, new IdentifierParameter('12'));
    }

    #[TestWith([BuiltinIdentity::Char])]
    #[TestWith([BuiltinIdentity::Varchar])]
    public function testAcceptsANationalCharacterType(BuiltinIdentity $base): void
    {
        $type = new StringStorage($base, null, null, false, true);
        self::assertTrue($type->national);
        self::assertSame($base->value, $type->name());
    }

    #[TestWith([BuiltinIdentity::Text, null])]
    #[TestWith([BuiltinIdentity::Char, 'utf8mb4'])]
    #[TestWith([BuiltinIdentity::Blob, null])]
    public function testRejectsANationalTypeOutsideCharOrWithACharacterSet(BuiltinIdentity $base, ?string $characterSet): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A national character type requires CHAR or VARCHAR and owns its character-set selection.');
        new StringStorage($base, null, $characterSet, false, true);
    }

    public function testAcceptsACharacterSetWithoutNationalSpelling(): void
    {
        self::assertSame('utf8mb4', (new StringStorage(BuiltinIdentity::Text, null, 'utf8mb4'))->characterSet);
    }

    public function testAcceptsANonnumericLengthOnABitString(): void
    {
        self::assertSame('bit', (new StringStorage(BuiltinIdentity::Bit, new IdentifierParameter('n')))->name());
    }

    public function testRejectsANonStringFamily(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A string type requires a character, binary or bit storage family.');
        new StringStorage(BuiltinIdentity::Integer);
    }
}
