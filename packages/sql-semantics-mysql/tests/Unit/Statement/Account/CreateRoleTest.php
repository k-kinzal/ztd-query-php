<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;

#[CoversClass(CreateRole::class)]
#[Medium]
final class CreateRoleTest extends TestCase
{
    public function testRenderPreservesAConditionalAnonymousAccount(): void
    {
        self::assertSame('CREATE ROLE IF NOT EXISTS ``', (new Semantics(Dialect::MySql))->analyze("CREATE ROLE IF NOT EXISTS ''")->toString());
    }

    public function testRenderRetainsAnAnonymousRoleCondition(): void
    {
        $statement = new CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(" \t"))]);
        self::assertNotNull($statement->problem);
        self::assertSame('CREATE ROLE cannot define an anonymous account.', $statement->problem->message());
    }

    public function testDeriveStatementPublishesAConcreteRequest(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE ROLE r');
        self::assertInstanceOf(CreateRole::class, $statement);
        self::assertNull($statement->problem);
        self::assertNotNull($statement->source);
    }

    public function testRenderRetainsDuplicateAccountIdentitiesWithACondition(): void
    {
        $statement = new CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')), new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
        self::assertNotNull($statement->problem);
        self::assertSame('CREATE ROLE cannot unconditionally define the same account twice.', $statement->problem->message());
    }

    public function testRenderKeepsConditionalDuplicates(): void
    {
        self::assertSame('CREATE ROLE IF NOT EXISTS r, r@`%`', (new Semantics(Dialect::MySql))->analyze("CREATE ROLE IF NOT EXISTS r, r@'%' ")->toString());
    }

    public function testRenderWritesTheRoles(): void
    {
        self::assertSame('CREATE ROLE IF NOT EXISTS r1, r2@h', (new Semantics(Dialect::MySql))->analyze('create role if not exists r1, r2@h')->toString());
    }
    public function testDeriveProgramKeepsAnUnreachedDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() CREATE ROLE r, ''");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertSame([], $operation->diagnostics());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(CreateRole::class, $operation->body);
    }

    public function testRenderPreservesTheCompleteDefinition(): void
    {
        self::assertSame('CREATE PROCEDURE p() CREATE ROLE r, ``@h', (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() CREATE ROLE r, ''@h")->toString());
    }

    public function testProblemKeepsItsOriginalRequest(): void
    {
        $request = new CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(''))]);
        self::assertNotNull($request->problem);
        self::assertSame($request->roles, $request->problem->roles);
    }

    public function testRenderRetainsDuplicateAccountsInAStoredDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE ROLE r, r');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(CreateRole::class, $operation->body);
        self::assertCount(2, $operation->body->roles);
        self::assertNotNull($operation->body->problem);
        self::assertSame('CREATE ROLE cannot unconditionally define the same account twice.', $operation->body->problem->message());
        self::assertSame('CREATE PROCEDURE p() CREATE ROLE r, r', $operation->toString());
    }

    public function testRenderAcceptsAConsistentDefinitionWithoutACondition(): void
    {
        $request = new CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
        self::assertNull($request->problem);
    }
    public function testToStringReconstructsTheCompleteRequest(): void
    {
        self::assertSame('CREATE ROLE r', (new Semantics(Dialect::MySql))->analyze('CREATE ROLE r')->toString());
    }

    public function testProfileRetainsTheSelectedLanguage(): void
    {
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('CREATE ROLE r')->profile()->grammar);
    }

    public function testDeclarationsDoesNotDeclareAccountsAsTables(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE ROLE r')->declarations());
    }
}
