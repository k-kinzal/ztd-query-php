<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet;

#[CoversClass(DeferredInsertSet::class)]
#[Medium]
final class DeferredInsertSetTest extends TestCase
{
    public function testDeriveProgramKeepsAllAssignmentDependencies(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SET a=ROW(@@autocommit,2), b=DEFAULT');
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredInsertSet::class, $operation->body);
        self::assertCount(2, $operation->body->assignments);
        self::assertNotInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', $operation->body->problem()->message());
    }

    public function testRenderPreservesSetAndDuplicateKeyRoles(): void
    {
        $sql = 'CREATE PROCEDURE p() INSERT INTO t SET a = 1 ON DUPLICATE KEY UPDATE b = ROW(2, 3)';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertSame($sql, $operation->toString());
    }

    public function testProblemRetainsTheFirstDeferredAssignment(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() REPLACE INTO t SET a=ROW(1,2,3), b=ROW(4,5)');
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredInsertSet::class, $operation->body);
        self::assertSame('Operand should contain 1 column(s), not 3.', $operation->body->problem()->message());
    }
}
