<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity\Numeric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Identity\Numeric\NumericStorage;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(NumericStorage::class)]
#[Medium]
final class NumericStorageTest extends TestCase
{
    public function testNameIdentifiesNumericStorageIndependentlyOfItsInputForms(): void
    {
        $type = new NumericStorage(BuiltinIdentity::Numeric, new IdentifierParameter('12'), new NegatedParameter(new NumericParameter('2')));
        self::assertSame('numeric', $type->name());
        self::assertSame('numeric("12", - (2))', \SqlSemantics\Serialization\TypeDeclaration::write(new TypeDescriptor(Dialect::PostgreSql, $type))->toString());
    }

    public function testRejectsScaleWithoutPrecision(): void
    {
        $this->expectException(InvalidStructure::class);
        new NumericStorage(BuiltinIdentity::Numeric, scale: new NumericParameter('2'));
    }

    public function testRejectsTextualInputsForFloatingPointSyntax(): void
    {
        $this->expectException(InvalidStructure::class);
        new NumericStorage(BuiltinIdentity::Float, new IdentifierParameter('12'));
    }

}
