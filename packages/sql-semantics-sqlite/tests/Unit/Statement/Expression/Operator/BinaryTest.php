<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Binary::class)]
#[Medium]
final class BinaryTest extends TestCase
{
    public function testDeriveScalarComparisonsAreIntegerAndNullWhenAnOperandCanBe(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a = b, a < 1, a & 1, a << b, a == 1, 1 = NULL FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(2)->nullability);
        self::assertSame(Nullability::Nullable, $query->field(3)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(4)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(5)->type);
    }

    public function testDeriveScalarIsFamilyIsNeverNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT b IS NULL, b IS NOT NULL, b IS DISTINCT FROM NULL, NULL IS NOT DISTINCT FROM NULL FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $nullability = array_map(static fn (Field $field): Nullability => $field->nullability, $query->fields()->items ?? []);

        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull], $nullability);
        self::assertInstanceOf(Known::class, $query->field(3)->type);
        self::assertSame(Storage::Integer, $query->field(3)->type->descriptor);
    }

    public function testDeriveScalarLogicalOperatorsAreIntegerAndCanBeNullWhenAnOperandCan(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a AND 1, a OR b, 1 AND NULL FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertInstanceOf(Known::class, $query->field(2)->type);
        self::assertSame(Storage::Integer, $query->field(2)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(2)->nullability);
    }

    public function testDeriveScalarArithmeticIsIntegerOrRealUnlessAnOperandIsCertainlyReal(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a + b, a - 1, a * 1, a + 1.5, 9223372036854775808 + 1, NULL + 1 FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Choice::class, $query->field(0)->type);
        self::assertSame([Storage::Integer, Storage::Real], $query->field(0)->type->alternatives);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertInstanceOf(Choice::class, $query->field(1)->type);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertInstanceOf(Choice::class, $query->field(2)->type);
        self::assertInstanceOf(Known::class, $query->field(3)->type);
        self::assertSame(Storage::Real, $query->field(3)->type->descriptor);
        self::assertInstanceOf(Known::class, $query->field(4)->type);
        self::assertSame(Storage::Real, $query->field(4)->type->descriptor);
        self::assertInstanceOf(NullOnly::class, $query->field(5)->type);
        self::assertSame(Nullability::Nullable, $query->field(5)->nullability);
    }

    public function testDeriveScalarDivisionAndRemainderCanAlwaysBeNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a / 2, a % 2, 1.5 / 2, NULL / 2 FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Choice::class, $query->field(0)->type);
        self::assertSame([Storage::Integer, Storage::Real], $query->field(0)->type->alternatives);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertInstanceOf(Choice::class, $query->field(1)->type);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertInstanceOf(Known::class, $query->field(2)->type);
        self::assertSame(Storage::Real, $query->field(2)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(2)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(3)->type);
    }

    public function testDeriveScalarConcatenationIsTextUnlessAnOperandIsNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT a || b, 'a' || 1, 1 || NULL FROM t", [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Text, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(2)->type);
    }

    public function testDeriveScalarJsonExtractionYieldsNullableTextOrAValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT b -> 'x', b ->> 'x', NULL ->> 'x', 'a' -> NULL FROM t", [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Text, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertInstanceOf(Choice::class, $query->field(1)->type);
        self::assertSame([Storage::Integer, Storage::Real, Storage::Text], $query->field(1)->type->alternatives);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertInstanceOf(NullOnly::class, $query->field(2)->type);
        self::assertInstanceOf(NullOnly::class, $query->field(3)->type);
    }

    public function testDeriveScalarRecordsTheFactsOfBothOperands(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a + b FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $sum = $query->field(0)->expression;

        self::assertInstanceOf(Binary::class, $sum);
        self::assertSame(BinaryOperator::Add, $sum->operator);
        self::assertInstanceOf(ResolvedColumn::class, $query->facts->scalar($sum->left)->resolution);
        self::assertSame(Nullability::NotNull, $query->facts->scalar($sum->left)->nullability);
        self::assertSame(Nullability::Nullable, $query->facts->scalar($sum->right)->nullability);
        self::assertNull($query->facts->scalar($sum)->resolution);
    }

    public function testDeriveScalarDependsOnAnUnboundParameter(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT ? + 1');

        self::assertInstanceOf(Choice::class, $query->field(0)->type);
        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
    }

    public function testDeriveScalarAdmitsRowsOfOneWidthInComparisons(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) < (1, 3), (1, 2) IS (1, 2), (SELECT 1, 2) = (1, 2)', []);

        self::assertSame([], $query->facts->diagnostics);
        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
    }

    public function testDeriveScalarReportsARowValueBesideASingleValue(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) = 3', []);

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $query->facts->diagnostics[0]->rule);
        self::assertSame('row value misused', $query->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarReportsRowsOfDifferentWidths(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) = (1, 2, 3)', []);

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $query->facts->diagnostics[0]);
        self::assertSame(ArityRule::RowComparison, $query->facts->diagnostics[0]->rule);
        self::assertSame(2, $query->facts->diagnostics[0]->expected);
        self::assertSame(3, $query->facts->diagnostics[0]->actual);
    }

    public function testDeriveScalarReportsARowValueOperandOfAnyOtherOperator(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $sum = $semantics->analyze('SELECT (1, 2) + 1', []);
        $conjunction = $semantics->analyze('SELECT (1, 2) AND 1', []);

        self::assertCount(1, $sum->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $sum->facts->diagnostics[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $sum->facts->diagnostics[0]->rule);
        self::assertCount(1, $conjunction->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $conjunction->facts->diagnostics[0]);
    }

    public function testRenderWritesWordedOperatorsAsKeywordsAndOthersAsSymbols(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a is not distinct from b AS c1, a<>1 AS c2, a->>\'x\' AS c3, a and b or 1 AS c4 from t');

        self::assertSame('SELECT a IS NOT DISTINCT FROM b AS c1, a <> 1 AS c2, a ->> \'x\' AS c3, a AND b OR 1 AS c4 FROM t', $query->toString());
    }

    public function testRenderKeepsTheWrittenAssociation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $tight = $semantics->analyze('SELECT 1 + 2 * 3');
        $grouped = $semantics->analyze('SELECT (1 + 2) * 3');
        $expression = $tight->field(0)->expression;

        self::assertInstanceOf(Binary::class, $expression);
        self::assertSame(BinaryOperator::Add, $expression->operator);
        self::assertInstanceOf(Binary::class, $expression->right);
        self::assertSame('SELECT 1 + 2 * 3', $tight->toString());
        self::assertSame('SELECT (1 + 2) * 3', $grouped->toString());
    }

    public function testRenderWritesANewlyBuiltOperation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $product = new Binary(BinaryOperator::Multiply, new IntegerLiteral('2'), new IntegerLiteral('3'));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new Binary(BinaryOperator::Add, new IntegerLiteral('1'), $product))]));

        self::assertSame('SELECT 1 + 2 * 3', $operation->toString());
        self::assertInstanceOf(Choice::class, $operation->field(0)->type);
    }

    public function testRefusesALeftOperandThatEndsInAWeakerOperator(): void
    {
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The left operand needs parentheses to keep its place.');

        new Binary(BinaryOperator::Multiply, $sum, new IntegerLiteral('3'));
    }

    public function testRefusesARightOperandThatStartsWithAnOperatorThatIsNotTighter(): void
    {
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The right operand needs parentheses to keep its place.');

        new Binary(BinaryOperator::Add, new IntegerLiteral('3'), $sum);
    }

    public function testRefusesIsFollowedByAPrefixNot(): void
    {
        $this->expectExceptionMessage('IS followed by a prefix NOT is read as IS NOT.');

        new Binary(BinaryOperator::Is, new IntegerLiteral('1'), new Unary(UnaryOperator::Not, new NullLiteral()));
    }
}
