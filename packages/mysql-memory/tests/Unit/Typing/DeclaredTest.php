<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\Declared;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Declared::class)]
#[Small]
final class DeclaredTest extends TestCase
{
    public function testDomainResolvesADecimalWithItsDefaultPrecision(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        $plain = $declared->domain(new Decimal());
        $unsigned = $declared->domain(new Decimal('8', '3', [NumericModifier::Unsigned]));

        self::assertSame([[Kind::Decimal, Field::NewDecimal, 11, 0, false], [Kind::Decimal, Field::NewDecimal, 9, 3, true]], [[$plain->kind, $plain->field, $plain->length, $plain->decimals, $plain->unsigned], [$unsigned->kind, $unsigned->field, $unsigned->length, $unsigned->decimals, $unsigned->unsigned]]);
    }

    public function testDomainTakesTheCollationOfTheTableForAStringThatNamesNone(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(['utf8mb4_0900_ai_ci', 'latin1_swedish_ci'], [$declared->domain(new Character(CharacterKind::VarChar, '5'))->collation->name, $declared->domain(new Character(CharacterKind::VarChar, '5'), Collation::known('latin1_swedish_ci'))->collation->name]);
    }

    public function testDomainHoldsASpatialTypeAsALongBinaryString(): void
    {
        $domain = (new Declared(Collation::known('utf8mb4_0900_ai_ci')))->domain(new Spatial(SpatialKind::Geometry));

        self::assertSame([Kind::String, Field::Blob, 4294967295, 'binary'], [$domain->kind, $domain->field, $domain->length, $domain->collation->name]);
    }

    public function testIntegralAnswersTheDisplayLengthOfEachType(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([[Field::Tiny, 4], [Field::Short, 6], [Field::Int24, 9], [Field::Long, 11], [Field::LongLong, 20]], [[$declared->integral(new Integral(IntegralKind::TinyInt))->field, $declared->integral(new Integral(IntegralKind::TinyInt))->length], [$declared->integral(new Integral(IntegralKind::SmallInt))->field, $declared->integral(new Integral(IntegralKind::SmallInt))->length], [$declared->integral(new Integral(IntegralKind::MediumInt))->field, $declared->integral(new Integral(IntegralKind::MediumInt))->length], [$declared->integral(new Integral(IntegralKind::Int))->field, $declared->integral(new Integral(IntegralKind::Int))->length], [$declared->integral(new Integral(IntegralKind::BigInt))->field, $declared->integral(new Integral(IntegralKind::BigInt))->length]]);
    }

    public function testIntegralDropsTheSignOfUnsignedAndZerofillTypes(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        $unsigned = $declared->integral(new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned]));
        $zerofill = $declared->integral(new Integral(IntegralKind::BigInt, '5', [NumericModifier::Zerofill]));

        self::assertSame([[10, true, false], [5, true, false]], [[$unsigned->length, $unsigned->unsigned, $unsigned->nullable], [$zerofill->length, $zerofill->unsigned, $zerofill->nullable]]);
    }

    public function testFloatingHoldsAFloatOfMoreThan24BitsAsADouble(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        $single = $declared->floating(new Floating(FloatingKind::Float));
        $wide = $declared->floating(new Floating(FloatingKind::Float, '30'));
        $real = $declared->floating(new Floating(FloatingKind::Real));

        self::assertSame([[Field::Float, 12, 31], [Field::Double, 22, 31], [Field::Double, 22, 31]], [[$single->field, $single->length, $single->decimals], [$wide->field, $wide->length, $wide->decimals], [$real->field, $real->length, $real->decimals]]);
    }

    public function testFloatingKeepsAPrecisionAndScale(): void
    {
        $domain = (new Declared(Collation::known('utf8mb4_0900_ai_ci')))->floating(new Floating(FloatingKind::Double, '7', '2', [NumericModifier::Unsigned]));

        self::assertSame([Kind::Double, Field::Double, 7, 2, true], [$domain->kind, $domain->field, $domain->length, $domain->decimals, $domain->unsigned]);
    }

    public function testCharacterAnswersTheFieldAndLengthOfEachType(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([[Field::String, 1], [Field::VarString, 20], [Field::Blob, 255], [Field::Blob, 65535], [Field::Blob, 16777215], [Field::Blob, 4294967295]], [[$declared->character(new Character(CharacterKind::Char), null)->field, $declared->character(new Character(CharacterKind::Char), null)->length], [$declared->character(new Character(CharacterKind::VarChar, '20'), null)->field, $declared->character(new Character(CharacterKind::VarChar, '20'), null)->length], [$declared->character(new Character(CharacterKind::TinyText), null)->field, $declared->character(new Character(CharacterKind::TinyText), null)->length], [$declared->character(new Character(CharacterKind::Text, '100'), null)->field, $declared->character(new Character(CharacterKind::Text, '100'), null)->length], [$declared->character(new Character(CharacterKind::MediumText), null)->field, $declared->character(new Character(CharacterKind::MediumText), null)->length], [$declared->character(new Character(CharacterKind::LongText), null)->field, $declared->character(new Character(CharacterKind::LongText), null)->length]]);
    }

    public function testCharacterResolvesNationalAndBinaryForms(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(['utf8mb3_general_ci', 'utf8mb4_bin', 'latin1_swedish_ci'], [$declared->character(new Character(CharacterKind::VarChar, '5', true), null)->collation->name, $declared->character(new Character(CharacterKind::Char, '5', false, new CharsetAttribute(CharsetForm::Binary)), null)->collation->name, $declared->character(new Character(CharacterKind::VarChar, '5', false, new CharsetAttribute(CharsetForm::Named, new Name('latin1'))), null)->collation->name]);
    }

    public function testTextLengthAnswersTheSmallestTextTypeThatHoldsTheCharacters(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));
        $utf8 = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame([255, 65535, 16777215, 4294967295], [$declared->textLength(100, Collation::known('latin1_swedish_ci')), $declared->textLength(100, $utf8), $declared->textLength(20000, $utf8), $declared->textLength(5000000, $utf8)]);
    }

    public function testCharsetResolvesEachFormOfTheAttribute(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));
        $latin = Collation::known('latin1_swedish_ci');

        self::assertSame(['latin1_swedish_ci', 'ascii_general_ci', 'utf8mb4_0900_ai_ci', 'binary', 'latin1_bin', 'utf8mb4_0900_ai_ci'], [$declared->charset(null, $latin)->name, $declared->charset(new CharsetAttribute(CharsetForm::Ascii), $latin)->name, $declared->charset(new CharsetAttribute(CharsetForm::Unicode), $latin)->name, $declared->charset(new CharsetAttribute(CharsetForm::Byte), $latin)->name, $declared->charset(new CharsetAttribute(CharsetForm::Binary), $latin)->name, $declared->charset(new CharsetAttribute(CharsetForm::CharacterSet, new Name('utf8mb4')), $latin)->name]);
    }

    public function testBinaryAnswersTheFieldAndLengthOfEachType(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([[Field::String, 1, 'binary'], [Field::VarString, 8, 'binary'], [Field::Blob, 65535, 'binary'], [Field::Blob, 4294967295, 'binary']], [[$declared->binary(new Binary(BinaryKind::Binary))->field, $declared->binary(new Binary(BinaryKind::Binary))->length, $declared->binary(new Binary(BinaryKind::Binary))->collation->name], [$declared->binary(new Binary(BinaryKind::VarBinary, '8'))->field, $declared->binary(new Binary(BinaryKind::VarBinary, '8'))->length, $declared->binary(new Binary(BinaryKind::VarBinary, '8'))->collation->name], [$declared->binary(new Binary(BinaryKind::Blob, '300'))->field, $declared->binary(new Binary(BinaryKind::Blob, '300'))->length, $declared->binary(new Binary(BinaryKind::Blob, '300'))->collation->name], [$declared->binary(new Binary(BinaryKind::LongBlob))->field, $declared->binary(new Binary(BinaryKind::LongBlob))->length, $declared->binary(new Binary(BinaryKind::LongBlob))->collation->name]]);
    }

    public function testTemporalCountsFractionalDigitsInTheLength(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([[Kind::Date, 10, 0], [Kind::Time, 14, 3], [Kind::DateTime, 19, 0], [Kind::DateTime, 26, 6], [Kind::Year, 4, 0]], [[$declared->temporal(new Temporal(TemporalKind::Date))->kind, $declared->temporal(new Temporal(TemporalKind::Date))->length, $declared->temporal(new Temporal(TemporalKind::Date))->decimals], [$declared->temporal(new Temporal(TemporalKind::Time, '3'))->kind, $declared->temporal(new Temporal(TemporalKind::Time, '3'))->length, $declared->temporal(new Temporal(TemporalKind::Time, '3'))->decimals], [$declared->temporal(new Temporal(TemporalKind::DateTime))->kind, $declared->temporal(new Temporal(TemporalKind::DateTime))->length, $declared->temporal(new Temporal(TemporalKind::DateTime))->decimals], [$declared->temporal(new Temporal(TemporalKind::Timestamp, '6'))->kind, $declared->temporal(new Temporal(TemporalKind::Timestamp, '6'))->length, $declared->temporal(new Temporal(TemporalKind::Timestamp, '6'))->decimals], [$declared->temporal(new Temporal(TemporalKind::Year))->kind, $declared->temporal(new Temporal(TemporalKind::Year))->length, $declared->temporal(new Temporal(TemporalKind::Year))->decimals]]);
    }

    public function testTemporalReportsTheFieldOfATimestamp(): void
    {
        $domain = (new Declared(Collation::known('utf8mb4_0900_ai_ci')))->temporal(new Temporal(TemporalKind::Timestamp));

        self::assertSame(Field::Timestamp, $domain->field);
    }

    public function testEnumerationMeasuresTheLongestMemberOrTheWholeSet(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        $enum = $declared->enumeration(new Enumeration(EnumerationKind::Enum, [new Text('a'), new Text('bcd')]), null);
        $set = $declared->enumeration(new Enumeration(EnumerationKind::Set, [new Text('a'), new Text('bcd')]), null);

        self::assertSame([[Field::Enum, 3, ['a', 'bcd']], [Field::Set, 5, ['a', 'bcd']]], [[$enum->field, $enum->length, $enum->members], [$set->field, $set->length, $set->members]]);
    }

    public function testElementaryResolvesBoolSerialJsonBitAndVector(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        $bool = $declared->elementary(new Elementary(ElementaryKind::Boolean));
        $serial = $declared->elementary(new Elementary(ElementaryKind::Serial));
        $json = $declared->elementary(new Elementary(ElementaryKind::Json));
        $bit = $declared->elementary(new Elementary(ElementaryKind::Bit, '9'));
        $vector = $declared->elementary(new Elementary(ElementaryKind::Vector, '3'));

        self::assertSame([[Field::Tiny, 1, false], [Field::LongLong, 20, true], [Field::Json, 4294967295, false], [Field::Bit, 9, true], [Field::Vector, 12, false]], [[$bool->field, $bool->length, $bool->unsigned], [$serial->field, $serial->length, $serial->unsigned], [$json->field, $json->length, $json->unsigned], [$bit->field, $bit->length, $bit->unsigned], [$vector->field, $vector->length, $vector->unsigned]]);
    }
}
