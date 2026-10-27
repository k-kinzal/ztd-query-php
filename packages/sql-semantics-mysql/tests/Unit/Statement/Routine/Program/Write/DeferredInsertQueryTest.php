<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery;

#[CoversClass(DeferredInsertQuery::class)]
#[Medium]
final class DeferredInsertQueryTest extends TestCase
{
    public function testDeriveProgramRetainsTheTargetBeforeSourceDependencies(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SELECT q.* FROM missing');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(M\Dml\Insert\InsertQuery::class, $operation->body);
        $deferred = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SELECT *');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $deferred);
        self::assertInstanceOf(DeferredInsertQuery::class, $deferred->body);
        self::assertSame('t', $deferred->body->into->table->name->name->value);
    }

    public function testProblemDescribesTheRetainedReadCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SELECT *');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredInsertQuery::class, $operation->body);
        self::assertSame('No tables used', $operation->body->problem()->message());
    }

    public function testRenderKeepsTheDestinationAndDuplicateKeyAssignments(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SELECT * ON DUPLICATE KEY UPDATE a = 2');
        self::assertSame('CREATE PROCEDURE p() INSERT INTO t SELECT * ON DUPLICATE KEY UPDATE a = 2', $operation->toString());
    }
}
