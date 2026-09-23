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
use SqlSemantics\Type\Identity\TemporalStorage;
use SqlSemantics\Type\Identity\TimeZoneMode;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(TemporalStorage::class)]
#[Medium]
final class TemporalStorageTest extends TestCase
{
    #[TestWith([BuiltinIdentity::Time, 'timetz'])]
    #[TestWith([BuiltinIdentity::Timestamp, 'timestamptz'])]
    public function testNameSelectsTheZonedFamilyWithAnIdentifierPrecision(BuiltinIdentity $base, string $expected): void
    {
        $type = new TemporalStorage($base, new IdentifierParameter('3'), TimeZoneMode::With);
        self::assertSame($expected, $type->name());
        self::assertSame($expected . '("3")', \SqlSemantics\Serialization\TypeDeclaration::write(new TypeDescriptor(Dialect::PostgreSql, $type))->toString());
    }

    public function testRejectsIdentifierPrecisionForAnUnzonedKeywordDeclaration(): void
    {
        $this->expectException(InvalidStructure::class);
        new TemporalStorage(BuiltinIdentity::Time, new IdentifierParameter('3'));
    }

}
