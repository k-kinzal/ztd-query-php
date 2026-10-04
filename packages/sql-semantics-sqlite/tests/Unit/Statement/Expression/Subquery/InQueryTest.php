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
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InQuery::class)]
#[Medium]
final class InQueryTest extends TestCase
{
    public function testDeriveScalarIsIntegerAndNotNullWhenNeitherSideCanBeNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a IN (SELECT a FROM t), a NOT IN (SELECT 1 UNION SELECT 2) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame(Storage::Integer, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $query->field(1)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarCanBeNullWhenTheOperandOrAComparedColumnCan(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a IN (SELECT b FROM t), b IN (SELECT a FROM t), 1 IN (SELECT 1 UNION SELECT NULL) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $nullability = array_map(static fn (Field $field): Nullability => $field->nullability, $query->fields()->items ?? []);

        self::assertSame([Nullability::Nullable, Nullability::Nullable, Nullability::Nullable], $nullability);
    }

    public function testDeriveScalarDependsOnTheMissingInputsOfAnOpenShape(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $star = $semantics->analyze('SELECT 1 IN (SELECT * FROM x)');
        $column = $semantics->analyze('SELECT 1 IN (SELECT c FROM x)');

        self::assertInstanceOf(Known::class, $star->field(0)->type);
        self::assertSame(Storage::Integer, $star->field(0)->type->descriptor);
        self::assertSame(Nullability::Dependent, $star->field(0)->nullability);
        self::assertSame([], $star->facts->diagnostics);
        self::assertSame(Nullability::Dependent, $column->field(0)->nullability);
    }

    public function testDeriveScalarReportsAQueryOfAnotherWidth(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 IN (SELECT a, b FROM t)', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $query->facts->diagnostics[0]);
        self::assertSame(ArityRule::ScalarSubquery, $query->facts->diagnostics[0]->rule);
        self::assertSame(1, $query->facts->diagnostics[0]->expected);
        self::assertSame(2, $query->facts->diagnostics[0]->actual);
        self::assertSame('Sub-select returns 2 columns - expected 1.', $query->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarAdmitsARowOperandOfTheWidthOfTheQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT (1, 2) IN (SELECT a, b FROM t)', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertSame([], $query->facts->diagnostics);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
    }

    public function testDeriveScalarRecordsTheFactOfTheQueryInsideTheEnvironmentOfTheExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT b IN (SELECT a + 1) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InQuery::class, $test);
        self::assertCount(1, $query->facts->query($test->query)->shape->slots);
        self::assertSame(Nullability::NotNull, $query->facts->query($test->query)->shape->slots[0]->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testRenderWritesTheNegationAndTheQuery(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a not in (select a from t) from t');
        $test = $query->field(0)->expression;

        self::assertInstanceOf(InQuery::class, $test);
        self::assertTrue($test->negated);
        self::assertSame('SELECT a NOT IN (SELECT a FROM t) FROM t', $query->toString());
    }

    public function testRenderWritesANewlyBuiltTest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $test = new InQuery(new IntegerLiteral('1'), new Select([new ResultColumn(new IntegerLiteral('2'))]));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn($test)]));

        self::assertSame('SELECT 1 IN (SELECT 2)', $operation->toString());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRefusesAnOperandThatEndsInAWeakerOperator(): void
    {
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));

        $this->expectExceptionMessage('The operand needs parentheses to keep its place.');

        new InQuery($disjunction, new Select([new ResultColumn(new IntegerLiteral('1'))]));
    }
}
