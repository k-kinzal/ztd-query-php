<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StringLiteral::class)]
#[Medium]
final class StringLiteralTest extends TestCase
{
    public function testValueConcatenatesTheAdjacentSegments(): void
    {
        $operation = (new Inspection())->inspect("SELECT a FROM t WHERE a = 'x' \"y\" 'z'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(StringLiteral::class, $literal);

        self::assertSame(['x', 'y', 'z'], $literal->segments);
        self::assertSame('xyz', $literal->value());
        self::assertSame("SELECT a FROM t WHERE a = 'x' 'y' 'z'", $operation->toString());
    }

    public function testDeriveScalarAnswersVarCharOfNoCharacterSetThatIsNeverNull(): void
    {
        $operation = (new Inspection())->inspect("SELECT 'a'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(StringLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertSame(EscapeRule::Backslash, $literal->escapes);
        self::assertNull($literal->introducer);
        self::assertFalse($literal->national);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Character::class, $fact->type->descriptor);
        self::assertSame(CharacterKind::VarChar, $fact->type->descriptor->kind);
        self::assertNull($fact->type->descriptor->length);
        self::assertFalse($fact->type->descriptor->national);
        self::assertNull($fact->type->descriptor->charset);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarAnswersTheIntroducedCharacterSet(): void
    {
        $operation = (new Inspection())->inspect("SELECT _UTF8MB4'x'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(StringLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertSame('utf8mb4', $literal->introducer?->value);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Character::class, $fact->type->descriptor);
        self::assertSame(CharsetForm::Named, $fact->type->descriptor->charset?->form);
        self::assertSame('utf8mb4', $fact->type->descriptor->charset->charset?->value);
        self::assertSame("SELECT _utf8mb4 'x'", $operation->toString());
    }

    public function testDeriveScalarAnswersTheNationalCharacterSet(): void
    {
        $operation = (new Inspection())->inspect("SELECT N'x' 'y'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(StringLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertTrue($literal->national);
        self::assertSame('xy', $literal->value());
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Character::class, $fact->type->descriptor);
        self::assertTrue($fact->type->descriptor->national);
        self::assertNull($fact->type->descriptor->charset);
        self::assertSame("SELECT N'x' 'y'", $operation->toString());
    }

    public function testDeriveScalarRejectsALiteralSpelledUnderTheOtherEscapeRule(): void
    {
        $context = (new Inspection())->context();
        $statement = new Select([], [new SelectExpression(new StringLiteral(['x'], EscapeRule::Verbatim))]);

        $this->expectExceptionMessage('A string literal must be spelled under the escape rule of the language profile.');

        new InspectedStatement($context, $statement);
    }

    public function testRenderDecodesAndSpellsEscapesUnderTheBackslashRule(): void
    {
        $semantics = new Inspection();
        $operation = $semantics->inspect("SELECT 'a\\nb'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(StringLiteral::class, $literal);

        self::assertSame("a\nb", $literal->value());
        self::assertSame("SELECT 'a\\nb'", $operation->toString());
        self::assertSame("SELECT 'it''s'", $semantics->inspect("SELECT 'it\\'s'")->toString());
        self::assertSame("SELECT 'a\\\\b'", $semantics->inspect("SELECT 'a\\\\b'")->toString());
    }

    public function testRenderKeepsBackslashesUnderTheVerbatimRule(): void
    {
        $operation = (new Inspection(null, Mode::fromString('NO_BACKSLASH_ESCAPES')))->inspect("SELECT 'a\\nb'");
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(StringLiteral::class, $literal);

        self::assertSame(EscapeRule::Verbatim, $literal->escapes);
        self::assertSame('a\\nb', $literal->value());
        self::assertSame("SELECT 'a\\nb'", $operation->toString());
    }

    public function testRenderWritesADoubleQuotedStringInSingleQuotes(): void
    {
        self::assertSame("SELECT 'q'", (new Inspection())->inspect('SELECT "q"')->toString());
    }

    public function testRejectsAnEmptySegmentList(): void
    {
        $this->expectExceptionMessage('A string literal holds at least one segment.');

        new StringLiteral([]);
    }

    public function testRejectsAKeyedSegmentList(): void
    {
        $this->expectExceptionMessage('A string literal holds at least one segment.');

        new StringLiteral(['first' => 'a']);
    }

    public function testRejectsASegmentThatIsNoString(): void
    {
        $this->expectExceptionMessage('A string literal holds decoded strings.');

        new StringLiteral([1]);
    }

    public function testRejectsAnUnknownIntroducer(): void
    {
        $this->expectExceptionMessage('An introducer names a character set of the server in lower case and excludes the national form.');

        new StringLiteral(['a'], EscapeRule::Backslash, new Name('nope'));
    }

    public function testRejectsAnIntroducerOnANationalString(): void
    {
        $this->expectExceptionMessage('An introducer names a character set of the server in lower case and excludes the national form.');

        new StringLiteral(['a'], EscapeRule::Backslash, new Name('utf8mb4'), true);
    }
}
