<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles;

#[CoversClass(DeferredGrantRoles::class)]
#[Medium]
final class DeferredGrantRolesTest extends TestCase
{
    public function testDeriveProgramKeepsAnUnreachedDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() GRANT r, '' TO u");
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredGrantRoles::class, $operation->statement->body);
    }

    public function testRenderPreservesTheCompleteDefinition(): void
    {
        self::assertSame('CREATE PROCEDURE p() GRANT r, `` TO u@h', (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() GRANT r, '' TO u@h")->toString());
    }

    public function testProblemKeepsItsOriginalRequest(): void
    {
        $roles = [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('')))];
        $request = new DeferredGrantRoles($roles, [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()]);
        self::assertSame($request, $request->problem()->request);
    }

    public function testRenderRequiresADeferredGrant(): void
    {
        $this->expectExceptionMessage('A deferred role grant retains an anonymous operand or explicit self-grant.');
        $roles = [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')))];
        new DeferredGrantRoles($roles, [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()]);
    }
}
