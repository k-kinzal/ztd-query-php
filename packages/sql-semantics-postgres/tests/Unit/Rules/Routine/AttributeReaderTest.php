<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AttributeReader;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\IntegerArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\DictionaryAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\LengthArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TextArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TypeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AttributeReader::class)]
#[Small]
final class AttributeReaderTest extends TestCase
{
    public function testCommandAnswersTheAttributeSetOfAKind(): void
    {
        self::assertSame([OperatorAttribute::class, DictionaryAttribute::class], [(new AttributeReader())->command(ObjectKind::Operator), (new AttributeReader())->command(ObjectKind::TextSearchDictionary)]);
    }

    public function testCommandRejectsAKindWithoutAttributes(): void
    {
        $this->expectExceptionMessage('Only an aggregate, an operator, a type, a text search object and a collation are defined with attributes.');
        (new AttributeReader())->command(ObjectKind::Table);
    }

    public function testAttributeReadsARecognizedNameAndKeepsAnotherAsWritten(): void
    {
        $reader = new AttributeReader();
        $function = $reader->attribute(OperatorAttribute::class, new Name('function'), new TypeName(new NamedDesignation(new DottedName([new Name('int4eq')]))));
        $other = $reader->attribute(OperatorAttribute::class, new Name('Function'), new StringConstant('x'));
        self::assertSame([OperatorAttribute::Function, true, null, true], [$function->known, $function->value instanceof NameArgument, $other->known, $other->value instanceof StringConstant]);
    }

    public function testReadYieldsTheArgumentOfEachReading(): void
    {
        $reader = new AttributeReader();
        self::assertSame(
            [TypeArgument::class, BooleanArgument::class, IntegerArgument::class, TextArgument::class, ChoiceArgument::class, StringConstant::class],
            [
                get_debug_type($reader->read(Reading::Type, new StringConstant('int4'))),
                get_debug_type($reader->read(Reading::Boolean, null)),
                get_debug_type($reader->read(Reading::Integer, new SignedNumber(false, new IntegerConstant('8')))),
                get_debug_type($reader->read(Reading::Text, new KeywordWord(new Name('none')))),
                get_debug_type($reader->read(Reading::Parallelism, new StringConstant('safe'))),
                get_debug_type($reader->read(Reading::Ignored, new StringConstant('x'))),
            ],
        );
    }

    public function testReadKeepsARejectedValueAsWritten(): void
    {
        $number = new SignedNumber(false, new NumericConstant('1.5'));
        self::assertSame([$number, null], [(new AttributeReader())->read(Reading::Integer, $number), (new AttributeReader())->read(Reading::Text, null)]);
    }

    public function testNameUnwrapsAPlainTypeNameAndRejectsNumbers(): void
    {
        $reader = new AttributeReader();
        $name = $reader->name(ObjectKind::Function, new TypeName(new NamedDesignation(new DottedName([new Name('f')]))));
        self::assertSame([true, true], [$name instanceof NameArgument && $name->name instanceof DottedName, $reader->name(ObjectKind::Function, new SignedNumber(false, new IntegerConstant('1'))) instanceof SignedNumber]);
    }

    public function testChoiceReadsOnlyAMemberText(): void
    {
        $reader = new AttributeReader();
        $choice = $reader->choice(Parallelism::class, new KeywordWord(new Name('none')));
        self::assertSame([true, true], [$choice instanceof KeywordWord, $reader->choice(Parallelism::class, new StringConstant('unsafe')) instanceof ChoiceArgument]);
    }

    public function testLengthReadsANumberOrVariable(): void
    {
        $reader = new AttributeReader();
        self::assertSame([LengthArgument::class, LengthArgument::class, OperatorName::class], [get_debug_type($reader->length(new SignedNumber(false, new IntegerConstant('4')))), get_debug_type($reader->length(new StringConstant('VARIABLE'))), get_debug_type($reader->length(new OperatorName(new Name('+'))))]);
    }

    public function testBooleanReadsTheServerSpellings(): void
    {
        $reader = new AttributeReader();
        self::assertSame([true, true, false, null, null], [$reader->boolean(null), $reader->boolean(new SignedNumber(false, new IntegerConstant('1'))), $reader->boolean(new StringConstant('OFF')), $reader->boolean(new SignedNumber(false, new IntegerConstant('2'))), $reader->boolean(new StringConstant('yes'))]);
    }

    public function testPlainRejectsModifiersArraysAndSetof(): void
    {
        $reader = new AttributeReader();
        self::assertSame([true, false], [$reader->plain(new TypeName(new NamedDesignation(new DottedName([new Name('f')])))), $reader->plain(new TypeName(new NamedDesignation(new DottedName([new Name('f')])), true))]);
    }
}
