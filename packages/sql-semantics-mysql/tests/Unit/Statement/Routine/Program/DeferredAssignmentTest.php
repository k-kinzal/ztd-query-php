<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment::class)]
#[Medium]
final class DeferredAssignmentTest extends TestCase
{
    public function testRenderRetainsNamedSystemAndUserTargets(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        self::assertSame('CREATE PROCEDURE p(x INT) SET x = (1, 2)', $semantics->analyze('CREATE PROCEDURE p(x INT) SET x=(1,2)')->toString());
        self::assertSame('CREATE PROCEDURE p() SET @@sql_mode = (1, 2)', $semantics->analyze('CREATE PROCEDURE p() SET @@sql_mode=(1,2)')->toString());
        self::assertSame('CREATE PROCEDURE p() SET @x = (1, 2)', $semantics->analyze('CREATE PROCEDURE p() SET @x=(1,2)')->toString());
    }

    public function testDeriveProgramResolvesTheRetainedParameter(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SET @x=(x,2)');
        self::assertSame([], $operation->diagnostics());
    }
    public function testDeriveProgramKeepsAScopedLocalTargetBoundToItsDeclaration(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SET GLOBAL x=(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        $warnings = array_map(static fn ($notice): string => $notice->warning->message(), $statement->statementInformation()->notices);
        self::assertNotContains("Unknown system variable 'x'", $warnings);
        self::assertContains('A local variable assignment cannot carry a system variable scope.', $warnings);
    }

}
