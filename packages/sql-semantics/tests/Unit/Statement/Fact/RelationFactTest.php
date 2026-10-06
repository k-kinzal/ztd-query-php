<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\RowShape;

#[CoversClass(RelationFact::class)]
#[Medium]
final class RelationFactTest extends TestCase
{
    public function testShapeAndTableResolutionOfANamedInput(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
        $query = $semantics->analyze('SELECT a FROM t', [$table]);

        $fact = $query->facts->relation($query->singleNamedInput());

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table->declarations()[0], $fact->table->table);
        self::assertSame(['a', 'b'], array_map(static fn ($slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertSame($table->declarations()[0]->columns[1], $fact->shape->slots[1]->column);
    }

    public function testTableIsNullForAnOccurrenceWithoutAName(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT x FROM (SELECT 1 AS x)');
        $input = $query->inputRelation();

        self::assertInstanceOf(DerivedQuery::class, $input);
        self::assertNull($query->facts->relation($input)->table);
        self::assertCount(1, $query->facts->relation($input)->shape->slots);
    }

    public function testTableDefaultsToNullOnConstruction(): void
    {
        $fact = new RelationFact(new RowShape([]));

        self::assertNull($fact->table);
        self::assertTrue($fact->shape->complete());
    }
}
