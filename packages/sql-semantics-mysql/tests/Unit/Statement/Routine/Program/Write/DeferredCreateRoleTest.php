<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateRole;

#[CoversClass(DeferredCreateRole::class)]
#[Medium]
final class DeferredCreateRoleTest extends TestCase
{
    public function testDeriveProgramKeepsAnUnreachedDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() CREATE ROLE r, ''");
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredCreateRole::class, $operation->statement->body);
    }

    public function testRenderPreservesTheCompleteDefinition(): void
    {
        self::assertSame('CREATE PROCEDURE p() CREATE ROLE r, ``@h', (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() CREATE ROLE r, ''@h")->toString());
    }

    public function testProblemKeepsItsOriginalRequest(): void
    {
        $request = new DeferredCreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(''))]);
        self::assertSame($request, $request->problem()->request);
    }

    public function testRenderRetainsDuplicateAccountsInAStoredDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE ROLE r, r');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredCreateRole::class, $operation->statement->body);
        self::assertCount(2, $operation->statement->body->roles);
        self::assertSame('CREATE ROLE cannot unconditionally define the same account twice.', $operation->statement->body->problem()->message());
        self::assertSame('CREATE PROCEDURE p() CREATE ROLE r, r', $operation->toString());
    }

    public function testRenderRequiresADeferredDefinition(): void
    {
        $this->expectExceptionMessage('A deferred role definition has an intrinsic account contradiction.');
        new DeferredCreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
    }
}
