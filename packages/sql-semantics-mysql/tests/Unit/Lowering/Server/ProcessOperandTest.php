<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\ProcessOperand;

#[CoversClass(ProcessOperand::class)]
#[Medium]
final class ProcessOperandTest extends TestCase
{
    public function testStatementRefusalRetainsItsCompleteOperand(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('KILL ROW(missing,2)');
            self::fail('A standalone process identifier must be scalar.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand::class, $failure->problem);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Row::class, $failure->problem->process);
            self::assertCount(2, $failure->problem->process->elements);
            self::assertFalse($failure->aborts);
        }
    }

    public function testProgramScalarKillKeepsItsOrdinaryRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL QUERY 0');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::class, $operation->statement->body);
        self::assertSame('CREATE PROCEDURE p() KILL QUERY 0', $operation->toString());
    }
    public function testReadRetainsTheStoredRowRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill::class, $operation->statement->body);
    }
}
