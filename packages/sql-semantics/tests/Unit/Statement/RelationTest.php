<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;

#[CoversClass(Relation::class)]
#[Medium]
final class RelationTest extends TestCase
{
    public function testDeriveRelationGivesEachOccurrenceOfOneTableItsOwnFact(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
        $operation = $semantics->analyze('SELECT x.a FROM t AS x JOIN t AS y ON x.a = y.a', [$table]);
        $join = $operation->inputRelation();

        self::assertInstanceOf(JoinChain::class, $join);
        $first = $operation->facts->relation($join->first);
        $second = $operation->facts->relation($join->steps[0]->relation);
        self::assertNotSame($join->first, $join->steps[0]->relation);
        self::assertInstanceOf(DeclaredTable::class, $first->table);
        self::assertInstanceOf(DeclaredTable::class, $second->table);
        self::assertSame($first->table->table, $second->table->table);
        self::assertCount(4, $operation->facts->relation($join)->shape->slots);
    }
}
