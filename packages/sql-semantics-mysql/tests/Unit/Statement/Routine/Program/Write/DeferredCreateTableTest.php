<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeferredCreateTable::class)]
#[Medium]
final class DeferredCreateTableTest extends TestCase
{
    public function testTemporaryKeepsTheOriginalHeaderRole(): void
    {
        $query = new M\Table\TableQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $definition = new DeferredCreateTable(new QualifiedName(new Name('t')), options: [new M\Table\Option\UnionOption([])], query: $query, temporaryWords: 1);
        self::assertTrue($definition->temporary());
        $this->expectExceptionMessage('MERGE sources with a query belong to a deferred table-creation instruction.');
        new M\Table\CreateTable($definition->name, options: $definition->options, query: $query);
    }

    public function testDeriveProgramRetainsCompleteHeaderAndQueryOperands(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) CREATE TABLE t(a INT, CHECK(a>0)) UNION=() AS SELECT x AS v');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        $definition = $operation->statement->body;
        self::assertInstanceOf(DeferredCreateTable::class, $definition);
        self::assertCount(2, $definition->elements);
        self::assertNotNull($definition->query);
        self::assertTrue($operation->facts->covers($definition->query->query));
    }

    public function testDeriveRelationRetainsTheProjectedColumnShape(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t UNION=() AS SELECT 1 AS v');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredCreateTable::class, $operation->statement->body);
        self::assertEquals(new Name('v'), $operation->facts->relation($operation->statement->body)->shape->slots[0]->name);
    }

    public function testRenderPreservesDeferredRowsAndMergeSources(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TEMPORARY TABLE IF NOT EXISTS t(a INT) ENGINE=InnoDB UNION=(s) AS SELECT ROW(1,2)');
        self::assertSame('CREATE PROCEDURE p() CREATE TEMPORARY TABLE IF NOT EXISTS t (a INT) ENGINE InnoDB UNION (s) AS SELECT ROW(1, 2)', $operation->toString());
    }
}
