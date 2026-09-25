<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Joining\SharedOutputs;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\Joining\UsingJoin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SharedOutputs::class)]
#[Medium]
final class SharedOutputsTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[TestWith([Dialect::PostgreSql, ['y', 'id', 'x', 'z']])]
    #[TestWith([Dialect::MySql, ['y', 'id', 'x', 'z']])]
    #[TestWith([Dialect::Sqlite, ['x', 'id', 'y', 'z']])]
    public function testUsingRetainsTheEarlierJoinOutputAndTheNewSharedColumn(Dialect $dialect, array $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE a(x INTEGER, id INTEGER)', 'CREATE TABLE b(id INTEGER, y INTEGER)', 'CREATE TABLE c(y INTEGER, z INTEGER)'));
        $query = $binder->bind('SELECT * FROM (a JOIN b USING(id)) JOIN c USING(y)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, array_column($query->outputs, 'name'));
        self::assertInstanceOf(UsingJoin::class, $query->from);
        self::assertSame(['y'], array_column($query->from->columns, 'name'));
        self::assertInstanceOf(UsingJoin::class, $query->from->left);
        self::assertSame(['id'], array_column($query->from->left->columns, 'name'));
        $direct = $binder->bind('SELECT id FROM (a JOIN b USING(id)) JOIN c USING(y)');
        self::assertInstanceOf(BoundSelect::class, $direct);
        self::assertSame('r0', $direct->outputs[0]->expression->columnBinding()?->relationId);
    }

    /**
     * @param list<string> $expected
     */
    #[TestWith([Dialect::PostgreSql, 'JOIN', ['k', 'id', 'x', 'y']])]
    #[TestWith([Dialect::PostgreSql, 'RIGHT JOIN', ['k', 'id', 'x', 'y']])]
    #[TestWith([Dialect::MySql, 'JOIN', ['id', 'k', 'x', 'y']])]
    #[TestWith([Dialect::MySql, 'RIGHT JOIN', ['k', 'id', 'y', 'x']])]
    #[TestWith([Dialect::Sqlite, 'JOIN', ['x', 'id', 'k', 'y']])]
    #[TestWith([Dialect::Sqlite, 'RIGHT JOIN', ['x', 'id', 'k', 'y']])]
    public function testOrderedFollowsEachDialectsSharedColumnOrder(Dialect $dialect, string $kind, array $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE a(x INTEGER, id INTEGER, k INTEGER)', 'CREATE TABLE b(k INTEGER, y INTEGER, id INTEGER)'));
        $query = $binder->bind('SELECT * FROM a ' . $kind . ' b USING(k,id)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, array_column($query->outputs, 'name'));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame($expected, array_column($rebound->outputs, 'name'));
    }

    public function testFindUsesTheDialectsColumnNameEquality(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE a(id INTEGER)', 'CREATE TABLE b(id INTEGER)')))->bind('SELECT * FROM a JOIN b USING(id)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(UsingJoin::class, $query->from);
        $identifiers = new Identifiers(Dialect::MySql);
        self::assertSame($query->from->columns[0], SharedOutputs::find($query->from->columns, 'ID', $identifiers));
        self::assertNull(SharedOutputs::find($query->from->columns, 'other', $identifiers));
        self::assertNull(SharedOutputs::find($query->from->columns, null, $identifiers));
    }

    #[TestWith([Dialect::MySql, '`'])]
    #[TestWith([Dialect::Sqlite, '"'])]
    public function testCommonMatchesNaturalColumnsWithDifferentIdentifierCase(Dialect $dialect, string $quote): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE a(' . $quote . 'ID' . $quote . ' INTEGER)', 'CREATE TABLE b(id INTEGER)'));
        $query = $binder->bind('SELECT * FROM a NATURAL JOIN b');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame(['ID'], array_column($query->outputs, 'name'));
        self::assertSame('r0', $query->outputs[0]->expression->columnBinding()?->relationId);
    }

    public function testCommonPreservesPostgreSqlCaseSensitiveNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a("ID" INTEGER)', 'CREATE TABLE b(id INTEGER)'));
        $query = $binder->bind('SELECT * FROM a NATURAL JOIN b');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame(['ID', 'id'], array_column($query->outputs, 'name'));
    }
}
