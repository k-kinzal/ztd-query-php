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
    public function testStatementRetainsItsCompleteNonScalarOperand(): void
    {
        $kill = (new Semantics(Dialect::MySql))->analyze('KILL QUERY ROW(missing,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::class, $kill);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Row::class, $kill->process);
        self::assertCount(2, $kill->process->elements);
        self::assertSame('KILL QUERY ROW(missing, 2)', $kill->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand::class, $kill->diagnostics()[1]);
    }

    public function testProgramScalarKillKeepsItsOrdinaryRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL QUERY 0');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::class, $operation->body);
        self::assertSame('CREATE PROCEDURE p() KILL QUERY 0', $operation->toString());
    }
    public function testReadRetainsTheStoredRowRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill::class, $operation->body);
    }
}
