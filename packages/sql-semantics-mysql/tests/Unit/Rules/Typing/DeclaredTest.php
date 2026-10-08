<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Declared;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
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
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

#[CoversClass(Declared::class)]
#[Small]
final class DeclaredTest extends TestCase
{
    public function testTableTakesTheCollationOfTheOptionsElseOfTheSchema(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([], true, null, new Settings(Collation::known('utf8mb4_0900_ai_ci'), 4, null, ['shop' => Collation::known('latin1_bin')]));
        $plain = $semantics->analyze('CREATE TABLE shop.t (a INT)')->statement;
        $optioned = $semantics->analyze('CREATE TABLE shop.t (a INT) CHARACTER SET ascii')->statement;
        self::assertInstanceOf(CreateTable::class, $plain);
        self::assertInstanceOf(CreateTable::class, $optioned);

        self::assertSame('latin1_bin', Declared::table($plain, $context)->collation->name);
        self::assertSame('ascii_general_ci', Declared::table($optioned, $context)->collation->name);
    }

    public function testColumnUsesItsCollateClauseAndHasNoDecimalsForAString(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a VARCHAR(5) CHARACTER SET latin1 COLLATE latin1_bin, b CHAR(2))')->statement;
        self::assertInstanceOf(CreateTable::class, $create);
        $columns = array_values(array_filter($create->elements, static fn ($element): bool => $element instanceof ColumnDefinition));
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertEquals(new Domain(Kind::String, Field::VarString, 5, 0, false, Collation::known('latin1_bin')), $declared->column($columns[0]->specification));
        self::assertSame([Field::String, 2, 0], [$declared->column($columns[1]->specification)->field, $declared->column($columns[1]->specification)->length, $declared->column($columns[1]->specification)->decimals]);
    }

    public function testDomainResolvesTheDisplayLengthOfAType(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertEquals(Domain::integer(Field::Long, 11), $declared->domain(new Integral(IntegralKind::Int)));
        self::assertEquals(Domain::integer(Field::Tiny, 3, true), $declared->integral(new Integral(IntegralKind::TinyInt, null, [\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier::Unsigned])));
    }

    public function testTextLengthPicksTheSmallestTextType(): void
    {
        $declared = new Declared(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(255, $declared->textLength(10, Collation::known('utf8mb4_bin')));
        self::assertSame(65535, $declared->textLength(100, Collation::known('utf8mb4_bin')));
    }

    public function testIntegralKeepsAWrittenWidth(): void
    {
        self::assertEquals(new Domain(Kind::Integer, Field::Long, 11, 0, false, null, [], Coercibility::Numeric, 5), (new Declared(Collation::binary()))->integral(new Integral(IntegralKind::Int, '5')));
        self::assertEquals(Domain::integer(Field::Long, 11), (new Declared(Collation::binary()))->integral(new Integral(IntegralKind::Int)));
    }

    public function testFloatingIsSingleUpToTwentyFourDigits(): void
    {
        $declared = new Declared(Collation::binary());

        self::assertSame([Field::Float, 12], [$declared->floating(new Floating(FloatingKind::Float))->field, $declared->floating(new Floating(FloatingKind::Float))->length]);
        self::assertSame([Field::Double, 22], [$declared->floating(new Floating(FloatingKind::Float, '30'))->field, $declared->floating(new Floating(FloatingKind::Float, '30'))->length]);
        self::assertSame([7, 2], [$declared->floating(new Floating(FloatingKind::Double, '7', '2'))->length, $declared->floating(new Floating(FloatingKind::Double, '7', '2'))->decimals]);
    }

    public function testCharacterTakesTheTableCollationUnlessNational(): void
    {
        $declared = new Declared(Collation::known('latin1_bin'));

        self::assertEquals(Domain::string(3, Collation::known('latin1_bin'), Field::String), $declared->character(new Character(CharacterKind::Char, '3'), null));
        self::assertSame('utf8mb3_general_ci', $declared->character(new Character(CharacterKind::VarChar, '3', true), null)->collation->name);
        self::assertSame('ascii_bin', $declared->character(new Character(CharacterKind::VarChar, '3'), Collation::known('ascii_bin'))->collation->name);
    }

    public function testCharsetResolvesEachForm(): void
    {
        $declared = new Declared(Collation::known('latin1_swedish_ci'));

        self::assertSame('latin1_swedish_ci', $declared->charset(null, Collation::known('latin1_swedish_ci'))->name);
        self::assertSame('ascii_general_ci', $declared->charset(new CharsetAttribute(CharsetForm::Ascii), Collation::known('latin1_swedish_ci'))->name);
        self::assertSame('latin1_bin', $declared->charset(new CharsetAttribute(CharsetForm::Binary), Collation::known('latin1_swedish_ci'))->name);
    }

    public function testBinaryHasTheBinaryCollation(): void
    {
        self::assertEquals(Domain::string(4, Collation::binary(), Field::VarString), (new Declared(Collation::binary()))->binary(new Binary(BinaryKind::VarBinary, '4')));
    }

    public function testTemporalCountsTheFraction(): void
    {
        self::assertSame([23, 3], [(new Declared(Collation::binary()))->temporal(new Temporal(TemporalKind::DateTime, '3'))->length, (new Declared(Collation::binary()))->temporal(new Temporal(TemporalKind::DateTime, '3'))->decimals]);
    }

    public function testEnumerationIsAsLongAsTheLongestMember(): void
    {
        $enum = new Enumeration(EnumerationKind::Enum, [new Text('ab'), new Text('c')]);
        $set = new Enumeration(EnumerationKind::Set, [new Text('ab'), new Text('c')]);
        $declared = new Declared(Collation::known('utf8mb4_bin'));

        self::assertSame([Field::Enum, 2, ['ab', 'c']], [$declared->enumeration($enum, null)->field, $declared->enumeration($enum, null)->length, $declared->enumeration($enum, null)->members]);
        self::assertSame(4, $declared->enumeration($set, null)->length);
    }

    public function testElementaryResolvesBooleansJsonAndBits(): void
    {
        $declared = new Declared(Collation::binary());

        self::assertEquals(Domain::integer(Field::Tiny, 1), $declared->elementary(new Elementary(ElementaryKind::Boolean)));
        self::assertSame('binary', $declared->elementary(new Elementary(ElementaryKind::Json))->collation->name);
        self::assertSame(8, $declared->elementary(new Elementary(ElementaryKind::Bit, '8'))->length);
    }

    public function testEnumerationCountsTheCharactersOfTheMembersAsWritten(): void
    {
        $enum = new Enumeration(EnumerationKind::Enum, [new Text('é'), new Text('b')]);
        $set = new Enumeration(EnumerationKind::Set, [new Text('é'), new Text('x')]);
        $declared = new Declared(Collation::known('utf8mb4_bin'));

        self::assertSame([1, 3], [$declared->enumeration($enum, Collation::known('latin1_swedish_ci'))->length, $declared->enumeration($set, Collation::known('latin1_swedish_ci'))->length]);
    }
}
