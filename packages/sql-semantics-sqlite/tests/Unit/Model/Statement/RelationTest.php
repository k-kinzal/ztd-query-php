<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Relation::class)]
#[Medium]
final class RelationTest extends TestCase
{
    public function testDeriveRelationGivesEachOccurrenceOfOneTableItsOwnFact(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER, b TEXT)');
        $operation = $semantics->inspect('SELECT x.a FROM t AS x JOIN t AS y ON x.a = y.a', [$table]);
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
