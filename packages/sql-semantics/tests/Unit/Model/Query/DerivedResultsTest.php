<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\DerivedResults;
use SqlSemantics\Model\Query\SetOperator;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\SetColumn;
use SqlSemantics\Model\Scalar\Value\ValuesColumn;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\Model\Statement\TableStatement;
use SqlSemantics\Model\Statement\ValuesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(DerivedResults::class)]
#[Medium]
final class DerivedResultsTest extends TestCase
{
    public function testRowsCombineEachPositionAcrossSeveralRows(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("VALUES (1, 'a'), (NULL, 'b')");
        self::assertInstanceOf(ValuesStatement::class, $statement);
        $outputs = DerivedResults::rows($statement->origin, $statement->rows);
        self::assertSame(['column1', 'column2'], array_column($outputs, 'name'));
        self::assertSame([0, 1], array_column($outputs, 'ordinal'));
        self::assertInstanceOf(ValuesColumn::class, $outputs[0]->expression);
        self::assertSame(Nullability::MaybeNull, $outputs[0]->expression->nullability);
        self::assertSame('integer', $outputs[0]->expression->type->name);
        self::assertSame(Nullability::NotNull, $outputs[1]->expression->nullability);
    }

    public function testRowsUseTheSingleRowExpressionsDirectly(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("VALUES (1, 'a')");
        self::assertInstanceOf(ValuesStatement::class, $statement);
        $outputs = DerivedResults::rows($statement->origin, $statement->rows);
        self::assertInstanceOf(Literal::class, $outputs[0]->expression);
        self::assertSame($statement->rows[0][0], $outputs[0]->expression);
    }

    public function testTableProjectsEveryDeclaredColumnWithItsFacts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n TEXT)')))->bind('TABLE t');
        self::assertInstanceOf(TableStatement::class, $statement);
        $outputs = DerivedResults::table($statement->origin, $statement->from);
        self::assertSame(['id', 'n'], array_column($outputs, 'name'));
        self::assertInstanceOf(ColumnReference::class, $outputs[0]->expression);
        self::assertSame(Nullability::NotNull, $outputs[0]->expression->nullability);
        self::assertSame('text', $outputs[1]->expression->type->name);
        self::assertSame(Nullability::MaybeNull, $outputs[1]->expression->nullability);
    }

    public function testTableProducesOneWildcardForAnUndeclaredTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('TABLE t', strict: false);
        self::assertInstanceOf(TableStatement::class, $statement);
        $outputs = DerivedResults::table($statement->origin, $statement->from);
        self::assertCount(1, $outputs);
        self::assertNull($outputs[0]->name);
        self::assertInstanceOf(Wildcard::class, $outputs[0]->expression);
        self::assertSame('unknown', $outputs[0]->expression->type->name);
    }

    public function testCompoundCombinesMatchingPositionsUnderTheOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 AS a UNION SELECT NULL');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        $outputs = DerivedResults::compound($statement->origin, $statement->left, $statement->right, SetOperator::Except);
        self::assertCount(1, $outputs);
        self::assertSame('a', $outputs[0]->name);
        self::assertInstanceOf(SetColumn::class, $outputs[0]->expression);
        self::assertSame(SetOperator::Except, $outputs[0]->expression->operator);
        self::assertSame('integer', $outputs[0]->expression->type->name);
        self::assertSame(Nullability::MaybeNull, $outputs[0]->expression->nullability);
    }
}
