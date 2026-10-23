<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel;

#[CoversClass(GrantRequest::class)]
#[Medium]
final class GrantRequestTest extends TestCase
{
    public function testScopeProblemRetainsTheContradictoryTarget(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT ON PROCEDURE d.p TO u')->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        self::assertInstanceOf(DeferredGrantLevel::class, $create->body);
        self::assertSame('SELECT cannot be granted at object d.p (ER_ILLEGAL_GRANT_FOR_TABLE).', $create->body->request->scopeProblem()?->message());
    }

    public function testColumnLevelDistinguishesNonObjectTargets(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT(a) ON d.* TO u')->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        self::assertInstanceOf(DeferredGrantLevel::class, $create->body);
        self::assertTrue($create->body->request->columnLevel());
    }

    public function testRenderRetainsTheProgramOperands(): void
    {
        self::assertSame('CREATE PROCEDURE p() GRANT SELECT (a) ON d.* TO u', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT(a) ON d.* TO u')->toString());
    }
}
