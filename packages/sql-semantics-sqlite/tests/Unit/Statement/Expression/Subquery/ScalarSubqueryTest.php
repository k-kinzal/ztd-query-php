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
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ScalarSubquery::class)]
#[Medium]
final class ScalarSubqueryTest extends TestCase
{
    public function testDeriveScalarYieldsTheTypeOfTheOneColumnAndCanAlwaysBeNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT (SELECT a FROM t), (SELECT 1 UNION SELECT 2)', [$create]);

        self::assertInstanceOf(Known::class, $query->field(0)->type);
        self::assertSame($create->declarations()[0]->columns[0]->type, $query->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertInstanceOf(Known::class, $query->field(1)->type);
        self::assertSame(Storage::Integer, $query->field(1)->type->descriptor);
        self::assertSame(Nullability::Nullable, $query->field(1)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarYieldsARowValueForSeveralColumns(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (SELECT 1, 2) = (1, 2)', []);
        $comparison = $query->field(0)->expression;

        self::assertInstanceOf(Binary::class, $comparison);
        self::assertInstanceOf(ScalarSubquery::class, $comparison->left);
        self::assertInstanceOf(Known::class, $query->facts->scalar($comparison->left)->type);
        self::assertInstanceOf(Vector::class, $query->facts->scalar($comparison->left)->type->descriptor);
        self::assertSame(2, $query->facts->scalar($comparison->left)->type->descriptor->width);
        self::assertSame(Nullability::Nullable, $query->facts->scalar($comparison->left)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarDependsOnTheMissingInputsOfAnOpenShape(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT (SELECT * FROM x)');

        self::assertInstanceOf(Dependent::class, $query->field(0)->type);
        self::assertInstanceOf(UndeclaredRelation::class, $query->field(0)->type->missing[0]);
        self::assertSame('x', $query->field(0)->type->missing[0]->name->name->value);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveScalarDerivesTheQueryInsideTheEnvironmentOfTheExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT (SELECT a + 1) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $subquery = $query->field(0)->expression;

        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        self::assertInstanceOf(Select::class, $subquery->query);
        self::assertInstanceOf(ResultColumn::class, $subquery->query->columns[0]);
        self::assertInstanceOf(Binary::class, $subquery->query->columns[0]->expression);
        $resolution = $query->facts->scalar($subquery->query->columns[0]->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame(1, $resolution->depth);
        self::assertCount(1, $query->facts->query($subquery->query)->shape->slots);
    }

    public function testRenderWritesTheQueryInParentheses(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select (select 1 union select 2), (select a from t where a > 1)');

        self::assertSame('SELECT (SELECT 1 UNION SELECT 2), (SELECT a FROM t WHERE a > 1)', $query->toString());
    }

    public function testRenderWritesANewlyBuiltSubquery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $subquery = new ScalarSubquery(new Select([new ResultColumn(new IntegerLiteral('1'))]));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn($subquery)]));

        self::assertSame('SELECT (SELECT 1)', $operation->toString());
        self::assertInstanceOf(Known::class, $operation->field(0)->type);
        self::assertSame(Storage::Integer, $operation->field(0)->type->descriptor);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
    }
}
