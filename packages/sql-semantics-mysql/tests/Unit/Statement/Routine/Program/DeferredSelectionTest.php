<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;

#[CoversClass(DeferredSelection::class)]
#[Medium]
final class DeferredSelectionTest extends TestCase
{
    public function testDeriveProgramRetainsOperandFactsInTheProgramScope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SELECT (x,2)');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredSelection::class, $operation->statement->body);
        $item = $operation->statement->body->items[0];
        self::assertInstanceOf(DeferredProjection::class, $item);
        self::assertTrue($operation->facts->covers($item->expression));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderUsesSelectForAnOrderingOnlyTemplate(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 ORDER BY ROW(2,3)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery::class, $create->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $create->body->query);
        $query = $create->body->query;
        $this->expectExceptionMessage('A deferred selection retains a projection or predicate requiring invocation-time preparation.');
        new DeferredSelection($query->items, orderBy: $query->orderBy);
    }

    public function testDeferredKeepsPreparationOrderAcrossTheClauses(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t WHERE (1,2) GROUP BY (3,4) HAVING (5,6) ORDER BY (7,8)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        self::assertInstanceOf(DeferredSelection::class, $create->body);
        self::assertCount(4, $create->body->deferred());
        self::assertSame($create->body->where, $create->body->deferred()[0]);
        self::assertSame($create->body->having, $create->body->deferred()[2]);
    }

    public function testRenderRetainsLegacyAnalysisWithTheDeferredWriteSource(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE PROCEDURE p() INSERT INTO t SELECT ROW(1,2) PROCEDURE ANALYSE(3)');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery::class, $operation->statement->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $operation->statement->body->source);
        self::assertNotNull($operation->statement->body->source->selection->procedure);
        self::assertSame('CREATE PROCEDURE p() INSERT INTO t SELECT ROW(1, 2) PROCEDURE ANALYSE(3)', $operation->toString());
    }

    public function testRenderKeepsTheInputAndPredicate(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2, 3)', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2,3)')->toString());
    }

    public function testRenderRetainsNamedWindowsWithDeferredProjectionOperands(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS (ORDER BY 1.5)');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredSelection::class, $operation->statement->body);
        self::assertCount(1, $operation->statement->body->windows);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec::class, $operation->statement->body->windows[0]->specification);
        self::assertTrue($operation->facts->covers($operation->statement->body->windows[0]->specification->order[0]->expression));
        self::assertSame('CREATE PROCEDURE p() SELECT FIRST_VALUE(ROW(1, 2)) OVER w WINDOW w AS (ORDER BY 1.5)', $operation->toString());
    }
}
