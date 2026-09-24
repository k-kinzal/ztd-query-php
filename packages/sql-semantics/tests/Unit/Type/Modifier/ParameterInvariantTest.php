<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Identity\Numeric\NumericStorage;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(\SqlSemantics\Type\Modifier\ParameterInvariant::class)]
#[Medium]
final class ParameterInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDialectRejectsPostgreSqlModifierInputsForOtherLanguages(Dialect $dialect): void
    {
        $this->expectException(InvalidStructure::class);
        new TypeDescriptor($dialect, new NumericStorage(BuiltinIdentity::Numeric, new IdentifierParameter('precision')));
    }

    public function testDialectRetainsNumericOnlyDeclarationsForMySql(): void
    {
        $identity = new NumericStorage(BuiltinIdentity::Numeric, new NumericParameter('12'), new NumericParameter('2'));
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::MySql, $identity);
        self::assertSame('numeric', (new TypeDescriptor(Dialect::MySql, $identity))->name);
    }

    public function testDialectRejectsNationalCharacterSetsOutsideMySql(): void
    {
        $identity = new \SqlSemantics\Type\Identity\StringStorage(BuiltinIdentity::Varchar, new NumericParameter('3'), national: true);
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::MySql, $identity);
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::PostgreSql, new \SqlSemantics\Type\Identity\StringStorage(BuiltinIdentity::Varchar, new NumericParameter('3')));
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('National character-set selection requires MySQL.');
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::PostgreSql, $identity);
    }

    public function testDialectAcceptsIdentifierInputsInPostgreSql(): void
    {
        $identity = new NumericStorage(BuiltinIdentity::Numeric, new IdentifierParameter('precision'));
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::PostgreSql, $identity);
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::MySql, BuiltinIdentity::Integer);
        self::assertSame('numeric', (new TypeDescriptor(Dialect::PostgreSql, $identity))->name);
    }

    public function testDialectRejectsAnIdentifierLengthOutsidePostgreSql(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This type-modifier operand requires PostgreSQL.');
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::MySql, new \SqlSemantics\Type\Identity\StringStorage(BuiltinIdentity::Bit, new IdentifierParameter('n')));
    }

    public function testDialectRejectsAnIdentifierPrecisionOutsidePostgreSql(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('This type-modifier operand requires PostgreSQL.');
        \SqlSemantics\Type\Modifier\ParameterInvariant::dialect(Dialect::Sqlite, new \SqlSemantics\Type\Identity\TemporalStorage(BuiltinIdentity::Timestamp, new IdentifierParameter('p'), \SqlSemantics\Type\Identity\TimeZoneMode::With));
    }
}
