<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments::class)]
#[Medium]
final class DeferredAssignmentsTest extends TestCase
{
    public function testRenderPreservesEveryAssignmentAndScope(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() SET GLOBAL sql_mode=DEFAULT, @x=(1,2), autocommit=ON');
        self::assertSame('CREATE PROCEDURE p() SET GLOBAL sql_mode = DEFAULT, @x = (1, 2), autocommit = ON', $operation->toString());
    }

    public function testDeriveProgramKeepsTheAssignmentDependencies(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SET @a=x,@b=(x,2)');
        self::assertSame([], $operation->diagnostics());
    }
}
