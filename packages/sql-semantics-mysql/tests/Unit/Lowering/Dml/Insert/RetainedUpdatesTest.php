<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\RetainedUpdates;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;

#[CoversClass(RetainedUpdates::class)]
#[Medium]
final class RetainedUpdatesTest extends TestCase
{
    public function testReadKeepsEveryDuplicateKeyOperandInItsWrittenRole(): void
    {
        $sql = 'CREATE PROCEDURE p() INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE a = 2, b = DEFAULT, c = ROW(3, 4)';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        self::assertCount(3, $operation->statement->body->onDuplicate);
        self::assertInstanceOf(Assignment::class, $operation->statement->body->onDuplicate[0]);
        self::assertInstanceOf(Assignment::class, $operation->statement->body->onDuplicate[1]);
        self::assertInstanceOf(DeferredColumnAssignment::class, $operation->statement->body->onDuplicate[2]);
        self::assertSame($sql, $operation->toString());
    }

    public function testAssignmentRetainsTheLegacyTargetAndNestedDependencies(): void
    {
        $sql = 'CREATE PROCEDURE p() INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE a = ROW((SELECT b FROM other), 2)';
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze($sql);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        self::assertInstanceOf(DeferredColumnAssignment::class, $operation->statement->body->onDuplicate[0]);
        self::assertSame('a', $operation->statement->body->onDuplicate[0]->column->name->value);
        self::assertSame($sql, $operation->toString());
    }
}
