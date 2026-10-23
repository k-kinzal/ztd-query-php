<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Result\LegacySetColumns;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field as Projection;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(LegacySetColumns::class)]
#[Small]
final class LegacySetColumnsTest extends TestCase
{
    public function testResolveKeepsTheLeadingIntegerSignedness(): void
    {
        $rules = new LegacySetColumns();
        $signed = Domain::integer(Field::LongLong, 1);
        $unsigned = Domain::integer(Field::LongLong, 20, true);

        self::assertEquals(Domain::integer(Field::LongLong, 20, true), $rules->resolve(Domain::decimal(20, 0), [$unsigned, $signed], []));
        self::assertEquals(Domain::integer(Field::LongLong, 20), $rules->resolve(Domain::decimal(20, 0), [$signed, $unsigned], []));
    }

    public function testIntegerRetainsTheOrderDependentMediumIntegerAllocation(): void
    {
        $rules = new LegacySetColumns();
        $medium = Domain::integer(Field::Int24, 9);
        $small = Domain::integer(Field::LongLong, 1);

        self::assertEquals(Domain::integer(Field::Long, 8), $rules->integer([$small, $medium]));
        self::assertEquals(Domain::integer(Field::LongLong, 8), $rules->integer([Domain::null(), $medium]));
        self::assertEquals($medium, $rules->integer([$medium, Domain::null()]));
        self::assertEquals(Domain::integer(Field::LongLong, 20), $rules->integer([$medium, $small]));
    }

    public function testFirstKeepsComputedMetadataWithoutUsingAColumnsDisplayWidth(): void
    {
        $rules = new LegacySetColumns();
        $domain = new Domain(Kind::Integer, Field::LongLong, 11, display: 10);
        $computed = new Projection(0, new OutputSlot(new Name('x'), new Known($domain), Nullability::NotNull));
        $column = new Column(new Name('x'), $domain, Nullability::NotNull);
        $copied = new Projection(0, new OutputSlot($column->name, new Known($domain), Nullability::NotNull, $column));

        self::assertSame(10, $rules->first($domain->value(), $computed));
        self::assertSame(11, $rules->first($domain->value(), $copied));
    }

    public function testTextDistinguishesAComputedDoubleFromAReferencedField(): void
    {
        $rules = new LegacySetColumns();
        $text = Domain::string(3, Collation::known('latin1_swedish_ci'));
        $double = Domain::double(22);
        $column = new Column(new Name('d'), $double, Nullability::Nullable);
        $field = new Projection(0, new OutputSlot($column->name, new Known($double), Nullability::Nullable, $column));

        self::assertSame(53, $rules->text($text, [$text, Domain::double(3)], [])->length);
        self::assertSame(22, $rules->text($text, [$text, $double], [null, $field])->length);
        self::assertSame(3, $rules->text($text, [Domain::double(3), $text], [])->length);
    }

    public function testFloatingRetainsFixedPrecisionOnlyWithinTheCombinedField(): void
    {
        $rules = new LegacySetColumns();
        $fixed = new Domain(Kind::Double, Field::Float, 8, 2);
        $result = $rules->floating(Domain::double(), [$fixed, Domain::integer(Field::Long, 11)]);
        $single = $rules->floating(Domain::double(), [new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED), Domain::integer(Field::LongLong, 1)]);

        self::assertSame([Field::Double, 13, 2], [$result->field, $result->length, $result->decimals]);
        self::assertSame([Field::Float, 12, Domain::NOT_FIXED], [$single->field, $single->length, $single->decimals]);
    }

    public function testDecimalUsesTheDigitCountOfAnUnsignedSpelling(): void
    {
        $integer = Domain::integer(Field::LongLong, 10);
        $field = new Projection(0, new OutputSlot(new Name('x'), new Known($integer), Nullability::NotNull), new NumberLiteral('2147483648'));
        $result = (new LegacySetColumns())->decimal(Domain::decimal(11, 2), [$integer, Domain::decimal(3, 2)], [$field, null]);

        self::assertSame([14, 2], [$result->length, $result->decimals]);
    }
}
