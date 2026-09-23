<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
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

}
