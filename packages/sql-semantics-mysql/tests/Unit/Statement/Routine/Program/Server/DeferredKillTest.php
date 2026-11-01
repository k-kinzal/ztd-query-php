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
    public function testDeriveProgramIsNeverReachedForASystemVariableTheReadingRefuses(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL QUERY ROW(@@missing,2)');
            self::fail('The system variable does not exist.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable::class, $failure->problem);
            self::assertSame('missing', $failure->problem->name);
        }
    }
    public function testRenderKeepsTheProcessScopeAndOperand(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL CONNECTION ROW(1,2)');
        self::assertSame('CREATE PROCEDURE p() KILL CONNECTION ROW(1, 2)', $operation->toString());
    }
}
