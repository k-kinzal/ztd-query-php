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

}
