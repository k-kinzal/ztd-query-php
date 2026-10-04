<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(PatternMatch::class)]
#[Medium]
final class PatternMatchTest extends TestCase
{
    public function testDeriveScalarLikeAndGlobAreIntegerAndNullWhenAnOperandCanBe(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT a LIKE 'x', b GLOB 'x', NULL LIKE 'a', a LIKE 'x' ESCAPE b, 1 LIKE 2 ESCAPE NULL FROM t", [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertInstanceOf(Known::class, $query->field(1)->type);
        self::assertSame(Storage::Integer, $query->field(1)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(2)->type);
        self::assertSame(Nullability::Nullable, $query->field(3)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(4)->type);
    }

    public function testDeriveScalarRegexpAndMatchDependOnTheUndeclaredRoutine(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT a REGEXP 'x', a MATCH 'x' FROM t", [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Dependent::class, $query->field(0)->type);
        self::assertInstanceOf(UndeclaredRoutine::class, $query->field(0)->type->missing[0]);
        self::assertSame('regexp', $query->field(0)->type->missing[0]->name->name->value);
        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
        self::assertInstanceOf(Dependent::class, $query->field(1)->type);
        self::assertSame('the signature of routine match', $query->field(1)->type->missing[0]->describe());
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarRecordsTheFactsOfEveryOperand(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT b LIKE 'x' ESCAPE '!' FROM t", [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $match = $query->field(0)->expression;

        self::assertInstanceOf(PatternMatch::class, $match);
        self::assertNotNull($match->escape);
        self::assertSame(Nullability::Nullable, $query->facts->scalar($match->left)->nullability);
        self::assertSame(Nullability::NotNull, $query->facts->scalar($match->right)->nullability);
        self::assertSame(Nullability::NotNull, $query->facts->scalar($match->escape)->nullability);
    }

    public function testDeriveScalarReportsARowValueOperand(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) LIKE 1', []);

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $query->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesTheNegationAndTheEscape(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("select a not like 'x!%' escape '!', a not glob 'x' from t");
        $match = $query->field(0)->expression;

        self::assertInstanceOf(PatternMatch::class, $match);
        self::assertSame(PatternOperator::Like, $match->operator);
        self::assertTrue($match->negated);
        self::assertInstanceOf(TextLiteral::class, $match->escape);
        self::assertSame('!', $match->escape->value);
        self::assertSame("SELECT a NOT LIKE 'x!%' ESCAPE '!', a NOT GLOB 'x' FROM t", $query->toString());
    }

    public function testRenderGroupsToTheLeft(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 'a' LIKE 'b' LIKE 'c'");
        $outer = $query->field(0)->expression;

        self::assertInstanceOf(PatternMatch::class, $outer);
        self::assertInstanceOf(PatternMatch::class, $outer->left);
        self::assertSame("SELECT 'a' LIKE 'b' LIKE 'c'", $query->toString());
    }

    public function testRenderWritesANewlyBuiltMatch(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $match = new PatternMatch(PatternOperator::Glob, new TextLiteral('abc'), new TextLiteral('a*'));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn($match)]));

        self::assertSame("SELECT 'abc' GLOB 'a*'", $operation->toString());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRefusesAMatchedOperandThatEndsInAWeakerOperator(): void
    {
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The matched operand needs parentheses to keep its place.');

        new PatternMatch(PatternOperator::Like, $disjunction, new TextLiteral('x'));
    }

    public function testRefusesAPatternThatStartsWithAnEqualityOperator(): void
    {
        $equality = new Binary(BinaryOperator::Equal, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The pattern needs parentheses to keep its place.');

        new PatternMatch(PatternOperator::Like, new TextLiteral('x'), $equality);
    }

    public function testRefusesAnEscapeAfterAPatternThatWouldTakeIt(): void
    {
        $inner = new PatternMatch(PatternOperator::Like, new TextLiteral('a'), new TextLiteral('b'));

        $this->expectExceptionMessage('The pattern or the escape operand needs parentheses to keep its place.');

        new PatternMatch(PatternOperator::Like, new TextLiteral('x'), new Unary(UnaryOperator::Not, $inner), false, new TextLiteral('!'));
    }

    public function testRefusesAnEscapeOperandThatStartsWithAnEqualityOperator(): void
    {
        $equality = new Binary(BinaryOperator::Equal, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The pattern or the escape operand needs parentheses to keep its place.');

        new PatternMatch(PatternOperator::Like, new TextLiteral('x'), new TextLiteral('y'), false, $equality);
    }
}
