<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(ItemNaming::class)]
#[Medium]
final class ItemNamingTest extends TestCase
{
    public function testNameAnswersTheAliasTheColumnNameOrTheText(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        self::assertEquals(new Name('x'), $naming->name(new SelectExpression(new NumberLiteral('1'), new Name('x'))));
        self::assertEquals(new Name('A'), $naming->name(new SelectExpression(new ColumnUse(new Name('A'), new QualifiedName(new Name('t'))))));
        self::assertEquals(new Name('a'), $naming->name(new SelectExpression(new Grouped(new ColumnUse(new Name('a'))))));
        self::assertEquals(new Name('1 + 1'), $naming->name(new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('1')))));
        self::assertEquals(new Name('1+1'), $naming->name(new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('1')), null, new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '1')]))));
    }

    public function testNameAnswersTheConversionANonAsciiTextDependsOn(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        self::assertEquals(new SessionState('character_set_client'), $naming->name(new SelectExpression(new StringLiteral(['é']))));
        self::assertEquals(new NameConversion('latin2'), $naming->name(new SelectExpression(new StringLiteral(['é'], introducer: new Name('latin2')))));
    }

    public function testNameRefusesALayoutOfAnItemThatNamesItself(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        $this->expectExceptionMessage('A select item keeps a layout only when MySQL names it after a text other than its canonical rendering.');

        $naming->name(new SelectExpression(new ColumnUse(new Name('a')), null, new Layout([new Spelled('', '`a`')])));
    }

    public function testNameRefusesALayoutThatSpellsTheCanonicalRendering(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        $this->expectExceptionMessage('A select item keeps a layout only when MySQL names it after a text other than its canonical rendering.');

        $naming->name(new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('1')), null, new Layout([new Spelled('', '1'), new Spelled(' ', '+'), new Spelled(' ', '1')])));
    }

    public function testOwnAnswersTheNamesOfTheItemsThatNameThemselves(): void
    {
        $legacy = new ItemNaming((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context()->profile);
        $modern = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        self::assertSame(['a', 'client'], $modern->own(new Grouped(new StringLiteral(['a', 'b']))));
        self::assertSame(['1', 'utf8mb3'], $modern->own(new NumberLiteral('1')));
        self::assertEquals(new Name('1.50'), $modern->own(new NumberLiteral('1.50')));
        self::assertEquals(new Name('TRUE'), $legacy->own(new BooleanLiteral(true)));
        self::assertNull($modern->own(new BooleanLiteral(true)));
        self::assertNull($modern->own(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('1'))));
    }

    public function testConstantSpellsTheNameArgumentOfNameConst(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        self::assertSame('ab', $naming->constant(new StringLiteral(['a', 'b'])));
        self::assertSame('7', $naming->constant(new NumberLiteral('007')));
        self::assertSame('0.5', $naming->constant(new NumberLiteral('.5')));
        self::assertSame('A', $naming->constant(new RadixLiteral(Radix::Hexadecimal, '41')));
        self::assertSame('1', $naming->constant(new BooleanLiteral(true)));
        self::assertSame(['x', 'utf8mb3'], $naming->own(new FunctionCall(new Name('name_const'), [new CallArgument(new StringLiteral(['x'])), new CallArgument(new NumberLiteral('1'))])));
    }

    public function testConstantRefusesAValueItDoesNotSpell(): void
    {
        $this->expectExceptionMessage('the column name NAME_CONST takes from a name argument other than');

        (new ItemNaming((new Semantics(Dialect::MySql))->context()->profile))->constant(new NumberLiteral('1e1'));
    }

    public function testBytesPadsBitsToWholeBytes(): void
    {
        self::assertSame("\x01\x01", (new ItemNaming((new Semantics(Dialect::MySql))->context()->profile))->bytes('100000001'));
    }

    public function testTextAnswersTheLayoutOrTheCanonicalRendering(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);
        $sum = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));

        self::assertSame('1+2', $naming->text(new SelectExpression($sum, null, new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2')]))));
        self::assertSame('1 + 2', $naming->text(new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')))));
    }

    public function testCanonicalAnswersTheRenderedText(): void
    {
        self::assertSame("CONCAT('a', 1)", (new ItemNaming((new Semantics(Dialect::MySql))->context()->profile))->canonical(new FunctionCall(new Name('CONCAT'), [new CallArgument(new StringLiteral(['a'])), new CallArgument(new NumberLiteral('1'))])));
    }

    public function testStoredStripsLeadingControlsAndCapsTheLength(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        self::assertEquals(new Name('lead'), $naming->stored("  \tlead", 'client'));
        self::assertEquals(new Name(str_repeat('a', 256)), $naming->stored(str_repeat('a', 300), 'utf8mb3'));
        self::assertEquals(new Name(str_repeat('a', 255)), $naming->stored(str_repeat('a', 300), 'latin1'));
        self::assertEquals(new SessionState('character_set_client'), $naming->stored(str_repeat('a', 300), 'client'));
        self::assertEquals(new SessionState('character_set_client'), $naming->stored('é', 'client'));
        self::assertEquals(new Name('é'), $naming->stored('é', 'national'));
        self::assertEquals(new Name(''), $naming->stored('é', 'binary'));
        self::assertEquals(new Name('aé'), $naming->stored('aé', 'binary'));
        self::assertEquals(new NameConversion('utf16'), $naming->stored('ab', 'utf16'));
        self::assertEquals(new NameConversion('latin1'), $naming->stored('é', 'latin1'));
        self::assertEquals(new Name('é?'), $naming->stored('é😀', 'utf8mb4'));
    }

    public function testNarrowedKeepsWholeCharactersOfUtf8mb3(): void
    {
        $naming = new ItemNaming((new Semantics(Dialect::MySql))->context()->profile);

        self::assertEquals(new Name(str_repeat('é', 127)), $naming->narrowed(str_repeat('é', 140)));
        self::assertEquals(new Name('a?'), $naming->narrowed('a😀'));
        self::assertEquals(new NameConversion('utf8mb4'), $naming->narrowed("\xC3"));
    }

    public function testLegacyTellsTheReleasesThatNameBooleansInUpperCase(): void
    {
        self::assertTrue((new ItemNaming((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context()->profile))->legacy());
        self::assertFalse((new ItemNaming((new Semantics(Dialect::MySql, 'mysql-8.0.44'))->context()->profile))->legacy());
    }
}
