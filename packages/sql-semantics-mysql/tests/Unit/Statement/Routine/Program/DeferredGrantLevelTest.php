<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel;

#[CoversClass(DeferredGrantLevel::class)]
#[Medium]
final class DeferredGrantLevelTest extends TestCase
{
    public function testDeriveProgramKeepsTheLevelConditionDeferred(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredGrantLevel::class, $operation->body);
        self::assertSame([], $operation->diagnostics());
    }

    public function testProblemDescribesTheRetainedLevel(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        $body = $create->body;
        self::assertInstanceOf(DeferredGrantLevel::class, $body);

        self::assertSame('The privilege and object kind do not fit the grant level.', $body->problem()->message());
    }

    public function testRenderPreservesAllOperandsAndRoles(): void
    {
        self::assertSame('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u AS CURRENT_USER WITH ROLE ALL', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u AS CURRENT_USER WITH ROLE ALL')->toString());
    }
}
