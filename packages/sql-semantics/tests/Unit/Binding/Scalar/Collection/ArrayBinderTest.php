<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Collection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Collection\ArrayBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Conditional\ArrayComparison;
use SqlSemantics\Model\Scalar\Query\ArraySubquery;
use SqlSemantics\Model\Scalar\Value\ArrayConstructor;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ArrayBinder::class)]
#[Medium]
final class ArrayBinderTest extends TestCase
{
    public function testBindReadsConstructorsAndCollectedQueries(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ARRAY[1], ARRAY(SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(ArrayConstructor::class, $query->outputs[0]->expression);
        self::assertInstanceOf(ArraySubquery::class, $query->outputs[1]->expression);
    }

    public function testBindRejectsACollectedQueryWithSeveralColumns(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ScalarQueryWidth->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ARRAY(SELECT 1, 2)');
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT 1');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertNull(ArrayBinder::bind($tree->find('simple_expr')[0], $scope));
        self::assertNull(ArrayBinder::comparison($tree->find('simple_expr')[0], $scope));
    }

    public function testComparisonQuantifiesAnArrayOperand(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 = ANY (ARRAY[1, 2])');
        self::assertInstanceOf(BoundSelect::class, $query);
        $comparison = $query->outputs[0]->expression;
        self::assertInstanceOf(ArrayComparison::class, $comparison);
        self::assertInstanceOf(ArrayConstructor::class, $comparison->array);
        self::assertSame('SELECT (1 = ANY (ARRAY[1, 2]))', $query->toString());
    }

    public function testComparisonRejectsAnArithmeticOperator(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::QuantifiedOperator->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 + ANY (ARRAY[1])');
    }

    public function testElementsDerivesTheCommonElementTypeOfEachLevel(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ARRAY[[1], [2.5]]');
        self::assertInstanceOf(BoundSelect::class, $query);
        $array = $query->outputs[0]->expression;
        self::assertInstanceOf(ArrayConstructor::class, $array);
        self::assertSame('numeric[]', $array->type->name);
        self::assertSame('integer[]', $array->elements[0]->type->name);
    }

    public function testComparisonNormalizesABuiltinAndKeepsANamedOperator(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT 1 OPERATOR(pg_catalog.=) ANY (ARRAY[1]), 'a' ~~* SOME (ARRAY['b']), 1 OPERATOR(geo.<->) ALL (ARRAY[2])");
        self::assertSame("SELECT (1 = ANY (ARRAY[1])), ('a' ILIKE SOME(ARRAY['b'])), (1 OPERATOR(\"geo\".<->) ALL (ARRAY[2]))", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
