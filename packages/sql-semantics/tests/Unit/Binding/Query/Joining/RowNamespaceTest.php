<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Joining\RowNamespace;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(RowNamespace::class)]
#[Medium]
final class RowNamespaceTest extends TestCase
{
    public function testReadRetainsBaseRelationOrderAndColumnTypes(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER, id TEXT)')))->bind('SELECT * FROM a');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(TableReference::class, $query->from);
        $scope = new Scope(new Identifiers(Dialect::PostgreSql), [$query->from]);
        $columns = RowNamespace::read($scope, $query->source);
        self::assertSame(['x', 'id'], array_column($columns, 'name'));
        self::assertSame('integer', $columns[0]->expression->type->name);
        self::assertSame('text', $columns[1]->expression->type->name);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testResolveDoesNotHideAmbiguityBehindAnEarlierMergedName(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE a(id INTEGER)', 'CREATE TABLE b(id INTEGER)', 'CREATE TABLE c(id INTEGER)'));
        $query = $binder->bind('SELECT id FROM (a JOIN b USING(id)) CROSS JOIN c', strict: false);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(UnresolvedColumnReference::class, $query->outputs[0]->expression);
        self::assertContains('ambiguous-column', array_column($query->diagnostics, 'reason'));
    }

    public function testCombineKeepsBothJoinedRowsInFromOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER, id INTEGER)', 'CREATE TABLE b(id INTEGER, y INTEGER)', 'CREATE TABLE c(z INTEGER, u INTEGER)', 'CREATE TABLE d(v INTEGER, z INTEGER)'));
        $query = $binder->bind('SELECT * FROM (a JOIN b USING(id)) CROSS JOIN (c JOIN d USING(z))');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame(['id', 'x', 'y', 'z', 'u', 'v'], array_column($query->outputs, 'name'));
        self::assertSame([0, 1, 2, 3, 4, 5], array_column($query->outputs, 'ordinal'));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame(['id', 'x', 'y', 'z', 'u', 'v'], array_column($rebound->outputs, 'name'));
    }

    public function testExtendCarriesOuterJoinNullFactsThroughPreviouslyMergedColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(id INTEGER NOT NULL, x INTEGER)', 'CREATE TABLE b(id INTEGER NOT NULL, y INTEGER)', 'CREATE TABLE c(z INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT * FROM (a FULL JOIN b USING(id)) RIGHT JOIN c ON a.id=c.z');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame(['id', 'x', 'y', 'z'], array_column($query->outputs, 'name'));
        self::assertSame(Nullability::MaybeNull, $query->outputs[0]->expression->nullability);
        self::assertSame(['j1'], $query->outputs[0]->expression->nullExtendedBy);
        self::assertSame(Nullability::NotNull, $query->outputs[3]->expression->nullability);
        self::assertSame([], $query->outputs[3]->expression->nullExtendedBy);
    }

    public function testPositionsRenumbersCombinedRowsWithoutMergingRepeatedLabels(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $second = Expression::literal(2, Dialect::PostgreSql);
        $columns = RowNamespace::positions([new OutputColumn(7, 'x', $first), new OutputColumn(3, 'x', $second)]);
        self::assertSame([0, 1], array_column($columns, 'ordinal'));
        self::assertSame(['x', 'x'], array_column($columns, 'name'));
        self::assertSame($first, $columns[0]->expression);
        self::assertSame($second, $columns[1]->expression);
    }
}
