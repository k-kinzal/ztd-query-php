<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InList::class)]
#[Medium]
final class InListTest extends TestCase
{
    public function testDeriveScalarIsIntegerAndNotNullWhenNoOperandCanBeNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a IN (1, 2), a NOT IN (1) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarCanBeNullWhenTheOperandOrAValueCan(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a IN (1, NULL), b IN (1), NULL IN (1), 1 NOT IN (NULL) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $nullability = array_map(static fn (Field $field): Nullability => $field->nullability, $query->fields()->items ?? []);

        self::assertSame([Nullability::Nullable, Nullability::Nullable, Nullability::Nullable, Nullability::Nullable], $nullability);
        self::assertInstanceOf(Known::class, $query->field(2)->type);
        self::assertSame(Storage::Integer, $query->field(2)->type->descriptor);
    }

    public function testDeriveScalarIsNeverNullWithAnEmptyList(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT NULL IN (), b NOT IN () FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
    }

    public function testDeriveScalarDependsOnAnUndeclaredColumn(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT c IN (1) FROM x');

        self::assertSame(Nullability::Dependent, $query->field(0)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarAdmitsRowsOfTheWidthOfTheOperand(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1, 2) IN ((1, 2), (3, 4))', []);

        self::assertSame([], $query->facts->diagnostics);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
    }

    public function testDeriveScalarReportsTheFirstValueOfAnotherWidth(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $single = $semantics->analyze('SELECT (1, 2) IN (1)', []);
        $several = $semantics->analyze('SELECT (1, 2) IN ((1, 2), 3, 4)', []);

        self::assertCount(1, $single->facts->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $single->facts->diagnostics[0]);
        self::assertSame(ArityRule::InListElement, $single->facts->diagnostics[0]->rule);
        self::assertSame(2, $single->facts->diagnostics[0]->expected);
        self::assertSame(1, $single->facts->diagnostics[0]->actual);
        self::assertSame('IN(...) element has 1 term - expected 2.', $single->facts->diagnostics[0]->message());
        self::assertCount(1, $several->facts->diagnostics);
    }

    public function testRenderWritesTheNegationAndTheValues(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a not in (1, 2) AS c1 from t');
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InList::class, $test);
        self::assertTrue($test->negated);
        self::assertCount(2, $test->items);
        self::assertSame('SELECT a NOT IN (1, 2) AS c1 FROM t', $query->toString());
    }

    public function testRenderWritesAnEmptyList(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a IN () FROM t');
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InList::class, $test);
        self::assertSame([], $test->items);
        self::assertSame('SELECT a IN () FROM t', $query->toString());
    }

    public function testRenderWritesANewlyBuiltTest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $test = new InList(new IntegerLiteral('1'), [new IntegerLiteral('1'), new IntegerLiteral('2')]);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn($test)]));

        self::assertSame('SELECT 1 IN (1, 2)', $operation->toString());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRefusesAnOperandThatEndsInAWeakerOperator(): void
    {
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The operand needs parentheses to keep its place.');

        new InList($disjunction, []);
    }
}
