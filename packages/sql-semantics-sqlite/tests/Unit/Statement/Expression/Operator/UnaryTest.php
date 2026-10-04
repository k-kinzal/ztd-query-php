<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Unary::class)]
#[Medium]
final class UnaryTest extends TestCase
{
    public function testDeriveScalarMinusFollowsTheArithmeticRule(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT -a, -b, -1.5, -NULL, -? FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Choice::class, $query->field(0)->type);
        self::assertSame([Storage::Integer, Storage::Real], $query->field(0)->type->alternatives);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertInstanceOf(Choice::class, $query->field(1)->type);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertInstanceOf(Known::class, $query->field(2)->type);
        self::assertSame(Storage::Real, $query->field(2)->type->descriptor);
        self::assertInstanceOf(NullOnly::class, $query->field(3)->type);
        self::assertInstanceOf(Choice::class, $query->field(4)->type);
        self::assertSame(Nullability::Dependent, $query->field(4)->nullability);
    }

    public function testDeriveScalarPlusChangesNothing(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze("SELECT +b, +a, +'x' FROM t", [$create]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame($create->declarations()[0]->columns[1]->type, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertInstanceOf(Known::class, $query->field(2)->type);
        self::assertSame(Storage::Text, $query->field(2)->type->descriptor);
    }

    public function testDeriveScalarNotAndBitNotAreIntegerUnlessTheOperandIsNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT NOT b, ~b, NOT a, NOT NULL, ~NULL FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertInstanceOf(Known::class, $query->field(1)->type);
        self::assertSame(Storage::Integer, $query->field(1)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(2)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(3)->type);
        self::assertInstanceOf(NullOnly::class, $query->field(4)->type);
    }

    public function testDeriveScalarReportsARowValueOperand(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT -(1, 2)', []);

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $query->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesNotAsAKeywordAndTheOthersAsSymbols(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select not a, -a, +a, ~a from t');

        self::assertSame('SELECT NOT a, - a, + a, ~ a FROM t', $query->toString());
    }

    public function testRenderCoversTheTighterOperatorsThatFollowThePrefix(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $negation = $semantics->analyze('SELECT NOT a = b FROM t');
        $collated = $semantics->analyze('SELECT -a COLLATE nocase FROM t');
        $operand = $negation->field(0)->expression;

        self::assertInstanceOf(Unary::class, $operand);
        self::assertInstanceOf(Binary::class, $operand->operand);
        self::assertSame('SELECT NOT a = b FROM t', $negation->toString());
        self::assertInstanceOf(Collate::class, $collated->field(0)->expression);
        self::assertInstanceOf(Unary::class, $collated->field(0)->expression->operand);
        self::assertSame('SELECT - a COLLATE nocase FROM t', $collated->toString());
    }

    public function testRenderWritesANewlyBuiltNegationOverASum(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('2'));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new Unary(UnaryOperator::Not, $sum))]));

        self::assertSame('SELECT NOT 1 + 2', $operation->toString());
        self::assertInstanceOf(Known::class, $operation->field(0)->type);
        self::assertSame(Storage::Integer, $operation->field(0)->type->descriptor);
    }

    public function testRefusesAnOperandThatStartsWithAWeakerOperator(): void
    {
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The operand needs parentheses to keep its place.');

        new Unary(UnaryOperator::Minus, $sum);
    }
}
