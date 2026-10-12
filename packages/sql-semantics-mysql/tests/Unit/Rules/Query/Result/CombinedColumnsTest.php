<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Result\CombinedColumns;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(CombinedColumns::class)]
#[Small]
final class CombinedColumnsTest extends TestCase
{
    public function testFloatingPreservesFixedScaleAndLegacyUnfixedWidths(): void
    {
        $rules = new CombinedColumns();
        $fixed = new Domain(Kind::Double, Field::Float, 8, 2);
        $wider = new Domain(Kind::Double, Field::Double, 9, 3);
        $unfixed = Domain::double(23);

        self::assertEquals(new Domain(Kind::Double, Field::Float, 8, 2, coercibility: Coercibility::Numeric), $rules->floating($fixed, [$fixed, $fixed], false));
        self::assertEquals(new Domain(Kind::Double, Field::Double, 9, 3, coercibility: Coercibility::Numeric), $rules->floating($wider, [$fixed, $wider], true));
        self::assertEquals(Domain::double(22), $rules->floating($unfixed, [Domain::double(12), Domain::double(22)], true));
        self::assertSame($unfixed, $rules->floating($unfixed, [Domain::double(12), Domain::double(22)], false));
        self::assertSame($unfixed, $rules->floating($unfixed, [$fixed, Domain::integer()], true));
    }

    public function testResolveRetainsBitStorageAcrossOperands(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $result = (new CombinedColumns())->resolve([new Domain(Kind::Bit, Field::Bit, 8), new Domain(Kind::Bit, Field::Bit, 64)], $derivation);

        self::assertEquals(new Domain(Kind::Bit, Field::Bit, 64, 0, true), $result);
    }

    public function testResolveUsesLegacyBigintStorageWidthOnlyForMatchingFields(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context());
        $rules = new CombinedColumns();

        self::assertEquals(Domain::integer(Field::LongLong, 20), $rules->resolve([Domain::integer(Field::LongLong, 2), Domain::integer(Field::LongLong, 3)], $derivation));
        self::assertEquals(Domain::integer(Field::LongLong, 4), $rules->resolve([Domain::integer(Field::LongLong, 2), Domain::integer(Field::Tiny, 4)], $derivation));
    }

    public function testResolvePreservesWiderLegacyIntegerExpressionBounds(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context());
        $result = (new CombinedColumns())->resolve([Domain::integer(Field::LongLong, 21), Domain::integer(Field::LongLong, 20)], $derivation);

        self::assertEquals(Domain::integer(Field::LongLong, 21), $result);
    }

    public function testResolveAllocatesIntegerStorageWhenMySql56ReplacesALeadingNull(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context());
        $rules = new CombinedColumns();
        $null = Domain::null();
        $integer = Domain::integer(Field::LongLong, 21);

        self::assertEquals(Domain::integer(Field::LongLong, 20), $rules->resolve([$null, $integer], $derivation));
        self::assertEquals($integer, $rules->resolve([$integer, $null], $derivation));
    }

    public function testResolveAllocatesIntegerStorageWhenMySql57ReplacesALeadingNull(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context());
        $integer = Domain::integer(Field::LongLong, 21);
        $result = (new CombinedColumns())->resolve([Domain::null(), $integer], $derivation);

        self::assertEquals(Domain::integer(Field::LongLong, 20), $result);
    }

    public function testOperandUsesTheReportedStringFieldForTemporalValues(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $domain = new Domain(Kind::Time, Field::VarString, 14, 3, collation: Collation::known('latin1_swedish_ci'), coercibility: Coercibility::Numeric, displayDecimals: 31);
        $rules = new CombinedColumns();
        $legacy = $rules->operand($domain, $derivation, true);
        $modern = $rules->operand($domain, $derivation, false);

        self::assertSame(Kind::String, $legacy->kind);
        self::assertSame('latin1_swedish_ci', $legacy->collation->name);
        self::assertSame(Coercibility::Numeric, $legacy->coercibility);
        self::assertSame(Kind::String, $modern->kind);
        self::assertSame('utf8mb4_0900_ai_ci', $modern->collation->name);
        self::assertSame(Coercibility::Coercible, $modern->coercibility);
    }

    public function testOperandReadsLegacyYearAndBitFieldsAsIntegers(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context());
        $rules = new CombinedColumns();

        self::assertEquals(Domain::integer(Field::LongLong, 4, true), $rules->operand(new Domain(Kind::Year, Field::LongLong, 4, 0, true), $derivation, true));
        self::assertEquals(Domain::integer(Field::LongLong, 8, true), $rules->operand(new Domain(Kind::Bit, Field::LongLong, 8, 0, true), $derivation, true));
    }
}
