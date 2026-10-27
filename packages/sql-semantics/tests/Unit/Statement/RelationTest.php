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
        $first = $join->first->relationInformation();
        $second = $join->steps[0]->relation->relationInformation();
        self::assertNotSame($join->first, $join->steps[0]->relation);
        self::assertInstanceOf(DeclaredTable::class, $first->table);
        self::assertInstanceOf(DeclaredTable::class, $second->table);
        self::assertSame($first->table->table, $second->table->table);
        self::assertCount(4, $join->relationInformation()->shape->slots);
    }

    public function testInitializeRelationInformationPublishesTheOccurrenceMeaning(): void
    {
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $information = new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([]));
        $node->initializeRelationInformation($information);
        self::assertSame($information, $node->relationInformation());
    }

    public function testRelationInformationIsOwnedByTheOccurrence(): void
    {
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $this->expectExceptionMessage('A relation occurrence must be resolved before use.');
        $node->relationInformation();
    }

    public function testHasRelationInformationDistinguishesAnUnresolvedOccurrence(): void
    {
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        self::assertFalse($node->hasRelationInformation());
        $node->initializeRelationInformation(new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([])));
        self::assertTrue($node->hasRelationInformation());
    }
}
