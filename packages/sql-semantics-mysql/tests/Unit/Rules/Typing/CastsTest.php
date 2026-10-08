<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Casts;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Casts::class)]
#[Small]
final class CastsTest extends TestCase
{
    public function testCastResolvesEachTarget(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertEquals(Domain::integer(Field::LongLong, 21, true), $casts->cast(Domain::integer(), new CastTarget(CastKind::Unsigned)));
        self::assertEquals(Domain::decimal(5, 2), $casts->cast(Domain::integer(), new CastTarget(CastKind::Decimal, '5', '2')));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3), $casts->cast(Domain::integer(), new CastTarget(CastKind::DateTime, '3')));
        self::assertEquals(Domain::string(22, Collation::known('latin1_bin')), $casts->cast(Domain::double(), new CastTarget(CastKind::Char)));
        self::assertEquals(Domain::string(3, Collation::binary()), $casts->cast(Domain::integer(), new CastTarget(CastKind::Binary, '3')));
        self::assertNull($casts->cast(Domain::integer(), new CastTarget(CastKind::Point)));
    }

    public function testDecimalTakesTheWrittenPrecisionAndScaleOrTenAndZero(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertEquals([Domain::decimal(10, 0), Domain::decimal(7, 0), Domain::decimal(7, 3)], [$casts->decimal(new CastTarget(CastKind::Decimal)), $casts->decimal(new CastTarget(CastKind::Decimal, '7')), $casts->decimal(new CastTarget(CastKind::Decimal, '7', '3'))]);
    }

    public function testFloatIsADoubleAboveTwentyFourDigits(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);
        $single = new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED);

        self::assertEquals([$single, $single, Domain::double(22)], [$casts->float(new CastTarget(CastKind::Float)), $casts->float(new CastTarget(CastKind::Float, '24')), $casts->float(new CastTarget(CastKind::Float, '25'))]);
    }

    public function testStringIsAsLongAsWrittenOrAsTheOperandAsText(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertEquals([Domain::string(22, Collation::binary()), Domain::string(5, Collation::binary())], [$casts->string(Domain::double(), new CastTarget(CastKind::Binary), Collation::binary()), $casts->string(Domain::double(), new CastTarget(CastKind::Binary, '5'), Collation::binary())]);
    }

    public function testCollationFollowsTheWrittenCharacterSet(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertSame('latin1_bin', $casts->collation(new CastTarget(CastKind::Char))->name);
        self::assertSame('utf8mb3_general_ci', $casts->collation(new CastTarget(CastKind::NationalChar))->name);
        self::assertSame('ascii_general_ci', $casts->collation(new CastTarget(CastKind::Char, null, null, new CharsetAttribute(CharsetForm::Ascii)))->name);
        self::assertSame('utf8mb4_0900_ai_ci', $casts->collation(new CastTarget(CastKind::Char, null, null, new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'))))->name);
    }

    public function testLengthWritesADoubleInTwentyTwoCharacters(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertSame([22, 4], [$casts->length(Domain::double()), $casts->length(Domain::integer(Field::LongLong, 4))]);
    }
}
