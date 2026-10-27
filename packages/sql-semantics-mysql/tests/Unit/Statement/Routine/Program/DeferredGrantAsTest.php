<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs;

#[CoversClass(DeferredGrantAs::class)]
#[Medium]
final class DeferredGrantAsTest extends TestCase
{
    public function testDeriveProgramKeepsTheLevelConditionDeferred(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredGrantAs::class, $operation->body);
        self::assertSame([], $operation->diagnostics());
    }

    public function testProblemDescribesTheRetainedLevel(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        $body = $create->body;
        self::assertInstanceOf(DeferredGrantAs::class, $body);

        self::assertSame('AS clause cannot be granted at database d (ER_UNSUPPORTED_USE_OF_GRANT_AS).', $body->problem()->message());
    }

    public function testRenderPreservesAllOperandsAndRoles(): void
    {
        self::assertSame('CREATE PROCEDURE p() GRANT SELECT (a) ON d.t TO u AS CURRENT_USER WITH ROLE ALL', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT(a) ON d.t TO u AS CURRENT_USER WITH ROLE ALL')->toString());
    }
}
