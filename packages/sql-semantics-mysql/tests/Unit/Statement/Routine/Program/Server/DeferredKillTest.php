<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill;

#[CoversClass(DeferredKill::class)]
#[Medium]
final class DeferredKillTest extends TestCase
{
    public function testDeriveProgramRetainsDefinitionTimeVariableProblems(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL QUERY ROW(@@missing,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredKill::class, $operation->statement->body);
        self::assertNotEmpty(array_filter($operation->facts->diagnostics, static fn ($problem) => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable));
        self::assertSame('CREATE PROCEDURE p() KILL QUERY ROW(@@missing, 2)', $operation->toString());
        self::assertNotInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation->statement->body);
    }
    public function testRenderKeepsTheProcessScopeAndOperand(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL CONNECTION ROW(1,2)');
        self::assertSame('CREATE PROCEDURE p() KILL CONNECTION ROW(1, 2)', $operation->toString());
    }
}
