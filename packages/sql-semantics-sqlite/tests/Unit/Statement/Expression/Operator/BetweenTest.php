<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Between::class)]
#[Medium]
final class BetweenTest extends TestCase
{
    public function testDeriveScalarIsIntegerAndNullWhenADecidingOperandCanBe(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a BETWEEN 1 AND 2, b BETWEEN 1 AND 2, a BETWEEN NULL AND 2, NULL BETWEEN 0 AND 2 FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertInstanceOf(Known::class, $query->field(1)->type);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $query->field(2)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(3)->type);
        self::assertSame(Nullability::Nullable, $query->field(3)->nullability);
    }

    public function testDeriveScalarDependsOnAnUndeclaredColumn(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT c BETWEEN 1 AND 2 FROM x');

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
    }

    public function testDeriveScalarAdmitsRowsOfOneWidth(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) BETWEEN (0, 0) AND (3, 3)', []);

        self::assertSame([], $query->facts->diagnostics);
        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
    }

    public function testDeriveScalarReportsARowValueBesideSingleValues(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operand = $semantics->analyze('SELECT (1, 2) BETWEEN 1 AND 3', []);
        $bound = $semantics->analyze('SELECT 1 BETWEEN (0, 0) AND 3', []);

        self::assertCount(1, $operand->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operand->facts->diagnostics[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $operand->facts->diagnostics[0]->rule);
        self::assertSame('row value misused', $operand->facts->diagnostics[0]->message());
        self::assertCount(1, $bound->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $bound->facts->diagnostics[0]);
    }

    public function testRenderWritesTheNegationAndTheBounds(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a not between 1 and 9 AS c1 from t');
        $test = $query->field(0)->expression;

        self::assertInstanceOf(Between::class, $test);
        self::assertTrue($test->negated);
        self::assertInstanceOf(IntegerLiteral::class, $test->low);
        self::assertSame('1', $test->low->digits);
        self::assertInstanceOf(IntegerLiteral::class, $test->high);
        self::assertSame('9', $test->high->digits);
        self::assertSame('SELECT a NOT BETWEEN 1 AND 9 AS c1 FROM t', $query->toString());
    }

    public function testRenderBindsLikeTheEqualityGroupAndGroupsToTheLeft(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $chained = $semantics->analyze('SELECT 1 BETWEEN 2 AND 3 BETWEEN 4 AND 5');
        $conjoined = $semantics->analyze('SELECT 1 BETWEEN 2 AND 3 AND 4');
        $outer = $chained->field(0)->expression;
        $conjunction = $conjoined->field(0)->expression;

        self::assertInstanceOf(Between::class, $outer);
        self::assertInstanceOf(Between::class, $outer->operand);
        self::assertSame('SELECT 1 BETWEEN 2 AND 3 BETWEEN 4 AND 5', $chained->toString());
        self::assertInstanceOf(Binary::class, $conjunction);
        self::assertSame(BinaryOperator::And, $conjunction->operator);
        self::assertInstanceOf(Between::class, $conjunction->left);
        self::assertSame('SELECT 1 BETWEEN 2 AND 3 AND 4', $conjoined->toString());
    }

    public function testRenderWritesANewlyBuiltTest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $test = new Between(new IntegerLiteral('5'), new IntegerLiteral('1'), new IntegerLiteral('9'), true);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn($test)]));

        self::assertSame('SELECT 5 NOT BETWEEN 1 AND 9', $operation->toString());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRefusesATestedOperandThatEndsInAWeakerOperator(): void
    {
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The tested operand needs parentheses to keep its place.');

        new Between($disjunction, new IntegerLiteral('1'), new IntegerLiteral('9'));
    }

    public function testRefusesALowBoundThatEndsInAConjunction(): void
    {
        $conjunction = new Binary(BinaryOperator::And, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The low bound needs parentheses to keep its place.');

        new Between(new IntegerLiteral('5'), $conjunction, new IntegerLiteral('9'));
    }

    public function testRefusesALowBoundThatStartsWithAnEqualityOperator(): void
    {
        $equality = new Binary(BinaryOperator::Equal, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The low bound needs parentheses to keep its place.');

        new Between(new IntegerLiteral('5'), $equality, new IntegerLiteral('9'));
    }

    public function testRefusesAHighBoundThatStartsWithAnEqualityOperator(): void
    {
        $equality = new Binary(BinaryOperator::Equal, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The high bound needs parentheses to keep its place.');

        new Between(new IntegerLiteral('5'), new IntegerLiteral('1'), $equality);
    }
}
