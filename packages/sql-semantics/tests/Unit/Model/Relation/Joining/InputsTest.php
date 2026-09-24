<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\Relation\Joining\Inputs;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Inputs::class)]
#[Medium]
final class InputsTest extends TestCase
{
    public function testTablesListsEveryOccurrenceOfAJoinTreeInOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT a.id FROM t a CROSS JOIN t b LEFT JOIN t c ON a.id=c.id');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(Join::class, $query->from);
        $tables = Inputs::tables($query->from);
        self::assertSame(['a', 'b', 'c'], array_column($tables, 'alias'));
        self::assertSame($query->relations, $tables);
        self::assertSame([$tables[2]], Inputs::tables($query->from->right));
    }

    public function testTablesDoesNotEnterAliasedSubqueries(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a FROM (t a JOIN t b ON a.id=b.id) AS q(a,b)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertNotNull($query->from);
        self::assertSame([$query->from], Inputs::tables($query->from));
    }

    public function testTablesOfNoInputIsEmpty(): void
    {
        self::assertSame([], Inputs::tables(null));
    }
}
