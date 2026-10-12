<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Casts;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
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
    public function testCastRetainsTheUtf8BoundOfLegacyJsonConversions(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_swedish_ci')), GrammarRelease::MySql5744);
        $result = $casts->cast(Domain::string(3, Collation::binary()), new CastTarget(CastKind::Json));

        self::assertSame([Field::Json, 16777216, 0, 'utf8mb4_bin'], [$result->field, $result->length, $result->decimals, $result->collation->name]);
    }

    public function testIntegerRetainsLegacyStringPrecisionBeyondTheReportedIntegerWidth(): void
    {
        $casts = new Casts(new Settings(Collation::binary()), GrammarRelease::MySql5651);
        $signed = $casts->cast(Domain::string(1024, Collation::binary()), new CastTarget(CastKind::Signed));
        $unsigned = $casts->cast(Domain::string(1024, Collation::binary()), new CastTarget(CastKind::Unsigned));

        self::assertSame([66, 21, 65, 21], [$signed->length, $signed->display, $unsigned->length, $unsigned->display]);
        self::assertSame([false, true], [$signed->unsigned, $unsigned->unsigned]);
    }

    public function testCastResolvesEachTarget(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertEquals(Domain::integer(Field::LongLong, 21, true), $casts->cast(Domain::integer(), new CastTarget(CastKind::Unsigned)));
        self::assertEquals(Domain::decimal(5, 2), $casts->cast(Domain::integer(), new CastTarget(CastKind::Decimal, '5', '2')));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3), $casts->cast(Domain::integer(), new CastTarget(CastKind::DateTime, '3')));
        self::assertEquals(Domain::string(22, Collation::known('latin1_bin')), $casts->cast(Domain::double(), new CastTarget(CastKind::Char)));
        self::assertEquals(Domain::string(3, Collation::binary()), $casts->cast(Domain::integer(), new CastTarget(CastKind::Binary, '3')));
        self::assertEquals(new Domain(Kind::String, Field::Geometry, 4294967295, 0, false, Collation::binary(), [], Coercibility::Coercible), $casts->cast(Domain::integer(), new CastTarget(CastKind::Point)));
    }

    public function testDecimalTakesTheWrittenPrecisionAndScaleOrTenAndZero(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertEquals([Domain::decimal(10, 0), Domain::decimal(7, 0), Domain::decimal(7, 3)], [$casts->decimal(new CastTarget(CastKind::Decimal)), $casts->decimal(new CastTarget(CastKind::Decimal, '7')), $casts->decimal(new CastTarget(CastKind::Decimal, '7', '3'))]);
    }

    public function testFloatIsADoubleAboveTwentyFourDigits(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);
        $single = new Domain(Kind::Double, Field::Float, 23, Domain::NOT_FIXED);

        self::assertEquals([$single, $single, Domain::double(23)], [$casts->float(new CastTarget(CastKind::Float)), $casts->float(new CastTarget(CastKind::Float, '24')), $casts->float(new CastTarget(CastKind::Float, '25'))]);
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

        self::assertSame([22, 4, 83], [$casts->length(Domain::double()), $casts->length(Domain::integer(Field::LongLong, 4)), $casts->length(new Domain(Kind::Decimal, Field::NewDecimal, 67, 30, display: 83))]);
    }

    /**
     * @return iterable<string, array{GrammarRelease, CastKind, int}>
     */
    public static function providerReportedCastWidths(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([CastKind::Char, CastKind::Binary] as $kind) {
                $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
                yield $release->value . ' ' . $kind->value => [$release, $kind, $legacy || $kind === CastKind::Binary ? 3 : 22];
            }
        }
    }

    #[DataProvider('providerReportedCastWidths')]
    public function testReportedStringRetainsFloatingMetadataSeparatelyFromTheTextBound(GrammarRelease $release, CastKind $kind, int $display): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), $release);
        $collation = $kind === CastKind::Binary ? Collation::binary() : Collation::known('latin1_bin');
        $result = $casts->reportedString(Domain::double(3), new CastTarget($kind), Domain::string(22, $collation));
        $explicit = $casts->reportedString(Domain::double(3), new CastTarget($kind, '7'), Domain::string(7, $collation));

        self::assertSame([22, $display, 7, 7], [$result->length, $result->metadataLength(), $explicit->length, $explicit->metadataLength()]);
    }

    public function testStringPreservesZeroIntegerMetadataWithoutDiscardingItsValueBound(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql5651);
        $operand = new Domain(Kind::Integer, Field::LongLong, 1, display: 0);
        $text = $casts->cast($operand, new CastTarget(CastKind::Char));
        $binary = $casts->cast($operand, new CastTarget(CastKind::Binary));

        self::assertSame([1, 0, 1, 0], [$text->length, $text->metadataLength(), $binary->length, $binary->metadataLength()]);
    }

    public function testCastMakesAnIntegerAsLongAsTheOperandUpToTwentyOneIn57(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_swedish_ci')), GrammarRelease::MySql5744);

        self::assertEquals(Domain::integer(Field::LongLong, 4), $casts->cast(Domain::decimal(2, 1), new CastTarget(CastKind::Signed)));
        self::assertEquals(Domain::integer(Field::LongLong, 21, true), $casts->cast(Domain::double(), new CastTarget(CastKind::Unsigned)));
    }

    public function testReturningResolvesTheTypesOfJsonValue(): void
    {
        $casts = new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);

        self::assertEquals(Domain::string(512, Collation::known('utf8mb4_0900_bin'), Field::VarString, Coercibility::Coercible), $casts->returning(null));
        self::assertEquals(Domain::string(3, Collation::known('utf8mb4_0900_bin'), Field::VarString, Coercibility::Coercible), $casts->returning(new CastTarget(CastKind::Char, '3')));
        self::assertEquals(Domain::string(4294967295, Collation::binary(), Field::LongBlob, Coercibility::Coercible), $casts->returning(new CastTarget(CastKind::Binary)));
        self::assertEquals(new Domain(Kind::Date, Field::Date, 10, 0, false, Collation::known('utf8mb4_0900_bin')), $casts->returning(new CastTarget(CastKind::Date)));
        self::assertEquals(Domain::integer(Field::LongLong, 21), $casts->returning(new CastTarget(CastKind::Signed)));
    }

    public function testStringIsALongTextForAJsonValue(): void
    {
        $json = new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'));

        self::assertEquals(Domain::string(4294967295, Collation::binary(), Field::LongBlob), (new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847))->string($json, new CastTarget(CastKind::Binary), Collation::binary()));
        self::assertEquals(Domain::string(4294967295, Collation::binary()), (new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql5744))->string($json, new CastTarget(CastKind::Binary), Collation::binary()));
    }

    public function testCastMakesAYearOfFiveDigitsIn80(): void
    {
        self::assertSame([5, 4], [(new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql8044))->cast(Domain::integer(), new CastTarget(CastKind::Year))->length, (new Casts(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847))->cast(Domain::integer(), new CastTarget(CastKind::Year))->length]);
    }

    public function testStringIsAMediumBlobBeyond65535Bytes(): void
    {
        $casts = new Casts(new Settings(Collation::known('utf8mb4_0900_ai_ci')), GrammarRelease::MySql847);
        $text = Domain::string(65535, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob);

        self::assertEquals([Domain::string(262140, Collation::known('utf8mb4_0900_ai_ci'), Field::MediumBlob), Domain::string(262140, Collation::binary(), Field::MediumBlob)], [$casts->string($text, new CastTarget(CastKind::Char), Collation::known('utf8mb4_0900_ai_ci')), $casts->string($text, new CastTarget(CastKind::Binary), Collation::binary())]);
    }
}
