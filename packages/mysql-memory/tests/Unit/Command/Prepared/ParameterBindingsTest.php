<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(ParameterBindings::class)]
#[Small]
final class ParameterBindingsTest extends TestCase
{
    public function testCaptureDistinguishesStandaloneAndCastMarkers(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT ?, CAST(? AS SIGNED)', true);
        $types = ParameterBindings::capture($operation);

        self::assertSame([[Kind::String, false], [Kind::String, true]], array_map(static fn (array $entry): array => [$entry[0]->kind, $entry[1]], $types->types));
    }

    public function testOperandUsesTheResolvedSiblingType(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT ?+1, ?=1.5', true);
        $types = ParameterBindings::capture($operation);

        self::assertSame([[Kind::Integer, false], [Kind::Decimal, false]], array_map(static fn (array $entry): array => [$entry[0]->kind, $entry[1]], $types->types));
    }

    public function testChangedRetainsTypesUntilAnIncompatibleArgumentArrives(): void
    {
        $types = new ParameterBindings([[Domain::string(16383, Collation::binary()), false]]);

        self::assertTrue($types->changed([[1, Domain::integer()]]));
        self::assertFalse($types->changed([[2, Domain::integer()]]));
        self::assertFalse($types->changed([['3', Domain::string(1, Collation::binary())]]));
        self::assertTrue($types->changed([['3.5', Domain::decimal(2, 1)]]));
        self::assertSame([[Kind::Decimal, false]], array_map(static fn (array $entry): array => [$entry[0]->kind, $entry[1]], $types->types));
        self::assertFalse($types->changed([[null, Domain::null()]]));
    }

    public function testAcceptsKeepsWideningNumericAndTemporalTypes(): void
    {
        self::assertTrue(ParameterBindings::accepts(Kind::Double, false, Domain::integer()));
        self::assertTrue(ParameterBindings::accepts(Kind::Decimal, false, Domain::integer()));
        self::assertTrue(ParameterBindings::accepts(Kind::DateTime, false, Domain::integer()));
        self::assertFalse(ParameterBindings::accepts(Kind::Integer, true, Domain::integer()));
    }

    public function testDomainsKeepsTheDerivedTypeForNullAndLeavesCastConversionToItsExpression(): void
    {
        $types = ParameterBindings::capture((new Instance())->connect()->analyze('SELECT ?, CAST(? AS SIGNED)', true));
        $types->changed([[1, Domain::integer()], [2, Domain::integer()]]);

        self::assertSame([0], array_keys($types->domains()));
        self::assertSame(Kind::Integer, $types->domains()[0]->kind);
    }
}
