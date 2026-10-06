<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(TypeClass::class)]
#[Small]
final class TypeClassTest extends TestCase
{
    public function testOfClassifiesEveryDescriptorKind(): void
    {
        self::assertSame(TypeClass::Integer, TypeClass::of(new Integral(IntegralKind::Int)));
        self::assertSame(TypeClass::Unsigned, TypeClass::of(new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned])));
        self::assertSame(TypeClass::Character, TypeClass::of(new Character(CharacterKind::Text)));
        self::assertSame(TypeClass::Character, TypeClass::of(new Enumeration(EnumerationKind::Set, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')])));
        self::assertSame(TypeClass::DateTime, TypeClass::of(new Temporal(TemporalKind::Timestamp)));
        self::assertSame(TypeClass::Json, TypeClass::of(new Elementary(ElementaryKind::Json)));
        self::assertSame(TypeClass::Unsigned, TypeClass::of(new CastTarget(CastKind::Unsigned)));
    }

    public function testOfRejectsADescriptorOfAnotherDatabase(): void
    {
        $this->expectExceptionMessage('A MySQL fact holds a MySQL type descriptor.');

        TypeClass::of(new class () implements \SqlSemantics\Statement\Type\TypeDescriptor {
            public function name(): string
            {
                return 'TEXT';
            }
        });
    }

    public function testTemporalClassifiesEveryTemporalKind(): void
    {
        self::assertSame([TypeClass::Date, TypeClass::Time, TypeClass::DateTime, TypeClass::DateTime, TypeClass::Year], array_map(TypeClass::temporal(...), TemporalKind::cases()));
    }

    public function testElementaryClassifiesEveryElementaryKind(): void
    {
        self::assertSame([TypeClass::Integer, TypeClass::Integer, TypeClass::Json, TypeClass::Bit, TypeClass::Vector], array_map(TypeClass::elementary(...), ElementaryKind::cases()));
    }

    public function testCastClassifiesTheTargetsOfCast(): void
    {
        self::assertSame(TypeClass::Binary, TypeClass::cast(CastKind::Binary));
        self::assertSame(TypeClass::Floating, TypeClass::cast(CastKind::Double));
        self::assertSame(TypeClass::Spatial, TypeClass::cast(CastKind::MultiPolygon));
        self::assertSame(TypeClass::Year, TypeClass::cast(CastKind::Year));
    }

    public function testForeignAlwaysFails(): void
    {
        $this->expectExceptionMessage('A MySQL fact holds a MySQL type descriptor.');

        TypeClass::foreign();
    }

    public function testNumericTellsTheNumberClasses(): void
    {
        self::assertTrue(TypeClass::Year->numeric());
        self::assertFalse(TypeClass::Character->numeric());
    }

    public function testTemporalClassTellsTheDateAndTimeClasses(): void
    {
        self::assertTrue(TypeClass::Time->temporalClass());
        self::assertFalse(TypeClass::Year->temporalClass());
    }

    public function testDescriptorNamesTheResultType(): void
    {
        self::assertSame(['BIGINT', 'BIGINT', 'DECIMAL', 'DOUBLE', 'VARCHAR', 'VARBINARY', 'DATE', 'TIME', 'DATETIME', 'YEAR', 'JSON', 'GEOMETRY', 'BIT', 'VECTOR'], array_map(static fn (TypeClass $class): string => $class->descriptor()->name(), TypeClass::cases()));
    }
}
