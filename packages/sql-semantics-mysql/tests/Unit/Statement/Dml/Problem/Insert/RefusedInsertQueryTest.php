<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertQuery;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery;

#[CoversClass(RefusedInsertQuery::class)]
#[Medium]
final class RefusedInsertQueryTest extends TestCase
{
    public function testProblemRetainsTheValueConstraintInItsWriteEnvelope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO d.t SELECT ROW(1, 2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertQuery::class, $operation->statement->body);
        $problem = new RefusedInsertQuery($operation->statement->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->problem()->message());
        self::assertSame($operation->statement->body, $problem->write);
    }    public function testMessageDescribesTheRetainedWriteConstraint(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO d.t SELECT ROW(1, 2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertQuery::class, $operation->statement->body);
        $problem = new RefusedInsertQuery($operation->statement->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->message());
        self::assertSame($operation->statement->body, $problem->write);
    }
}
