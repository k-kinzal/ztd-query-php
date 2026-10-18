<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedInsertRows;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;

#[CoversClass(RefusedInsertRows::class)]
#[Medium]
final class RefusedInsertRowsTest extends TestCase
{
    public function testProblemRetainsTheValueConstraintInItsWriteEnvelope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (ROW(1, 2))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        $problem = new RefusedInsertRows($operation->statement->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->problem()->message());
        self::assertSame($operation->statement->body, $problem->write);
    }    public function testMessageDescribesTheRetainedWriteConstraint(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (ROW(1, 2))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        $problem = new RefusedInsertRows($operation->statement->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->message());
        self::assertSame($operation->statement->body, $problem->write);
    }
}
