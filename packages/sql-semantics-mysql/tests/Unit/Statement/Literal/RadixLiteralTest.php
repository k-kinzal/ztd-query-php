<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RadixLiteral::class)]
#[Medium]
final class RadixLiteralTest extends TestCase
{
    public function testDeriveScalarAnswersVarBinaryWithoutAnIntroducer(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT a FROM t WHERE a = X'1F'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(RadixLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertSame(Radix::Hexadecimal, $literal->radix);
        self::assertSame('1F', $literal->digits);
        self::assertNull($literal->introducer);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Binary::class, $fact->type->descriptor);
        self::assertSame(BinaryKind::VarBinary, $fact->type->descriptor->kind);
        self::assertNull($fact->type->descriptor->length);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarAnswersVarCharOfTheIntroducedCharacterSet(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT _LATIN1 b'1'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(RadixLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertSame(Radix::Bit, $literal->radix);
        self::assertSame('1', $literal->digits);
        self::assertSame('latin1', $literal->introducer?->value);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Character::class, $fact->type->descriptor);
        self::assertSame(CharacterKind::VarChar, $fact->type->descriptor->kind);
        self::assertFalse($fact->type->descriptor->national);
        self::assertSame(CharsetForm::Named, $fact->type->descriptor->charset?->form);
        self::assertSame('latin1', $fact->type->descriptor->charset->charset?->value);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderQuotesAnEvenCountOfHexadecimalDigits(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame("SELECT x'1F' AS v", $semantics->analyze('SELECT 0x1F AS v')->toString());
        self::assertSame("SELECT x'1F' AS v", $semantics->analyze("SELECT X'1F' AS v")->toString());
        self::assertSame("SELECT x'' AS v", $semantics->analyze("SELECT x'' AS v")->toString());
        self::assertSame('SELECT 0x1F', $semantics->analyze('SELECT 0x1F')->toString());
    }

    public function testRenderPrefixesAnOddCountOfHexadecimalDigits(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 0x1');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(RadixLiteral::class, $literal);

        self::assertSame('1', $literal->digits);
        self::assertSame('SELECT 0x1', $operation->toString());
    }

    public function testRenderQuotesBitDigitsOfEitherSpelling(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 0b01 AS v');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(RadixLiteral::class, $literal);

        self::assertSame('01', $literal->digits);
        self::assertSame("SELECT b'01' AS v", $operation->toString());
        self::assertSame("SELECT b'101' AS v", (new Semantics(Dialect::MySql))->analyze("SELECT B'101' AS v")->toString());
    }

    public function testRenderWritesTheIntroducerInLowerCase(): void
    {
        self::assertSame("SELECT _latin1 x'1F' AS v", (new Semantics(Dialect::MySql))->analyze('SELECT _Latin1 0x1F AS v')->toString());
    }

    public function testRejectsDigitsOutsideTheHexadecimalSystem(): void
    {
        $this->expectExceptionMessage('The digits belong to the digit system of the literal.');

        new RadixLiteral(Radix::Hexadecimal, '1G');
    }

    public function testRejectsDigitsOutsideTheBitSystem(): void
    {
        $this->expectExceptionMessage('The digits belong to the digit system of the literal.');

        new RadixLiteral(Radix::Bit, '102');
    }

    public function testRejectsAnUnknownIntroducer(): void
    {
        $this->expectExceptionMessage('An introducer names a character set of the server in lower case.');

        new RadixLiteral(Radix::Hexadecimal, '1F', new Name('nope'));
    }

    public function testRejectsAnUpperCaseIntroducer(): void
    {
        $this->expectExceptionMessage('An introducer names a character set of the server in lower case.');

        new RadixLiteral(Radix::Hexadecimal, '1F', new Name('UTF8MB4'));
    }
}
