<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeferredRevokePrivileges::class)]
#[Medium]
final class DeferredRevokePrivilegesTest extends TestCase
{
    public function testDeriveProgramRetainsEveryRecipientAndOption(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE IF EXISTS ALL ON PROCEDURE * FROM u, CURRENT_USER IGNORE UNKNOWN USER');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredRevokePrivileges::class, $operation->statement->body);
        self::assertTrue($operation->statement->body->ifExists);
        self::assertTrue($operation->statement->body->ignoreUnknownUser);
        self::assertCount(2, $operation->statement->body->users);
    }

    public function testProblemPreservesTheCompleteInstruction(): void
    {
        $request = new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        self::assertSame($request, $request->problem()->request);
    }

    public function testRenderWritesTheStoredTargetCondition(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE IF EXISTS ALL ON FUNCTION d.* FROM u IGNORE UNKNOWN USER');
        self::assertSame('CREATE PROCEDURE p() REVOKE IF EXISTS ALL ON FUNCTION d.* FROM u IGNORE UNKNOWN USER', $operation->toString());
    }

    public function testObjectTargetCannotBeConstructedAsADeferredWildcard(): void
    {
        $this->expectExceptionMessage('A deferred revocation retains a wildcard routine target.');
        new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Function, new M\Account\Privilege\Level\ObjectLevel(new QualifiedName(new Name('f'))), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
    }

    public function testLegacyGlobalAllCannotRetainModernAccountFlags(): void
    {
        $this->expectExceptionMessage('A legacy global routine revocation cannot use modern account flags.');
        new DeferredRevokePrivileges(true, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Function, new M\Account\Privilege\Level\GlobalLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
    }
}
