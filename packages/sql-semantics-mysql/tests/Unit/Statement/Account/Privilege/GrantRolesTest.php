<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;

#[CoversClass(GrantRoles::class)]
#[Medium]
final class GrantRolesTest extends TestCase
{
    public function testRenderRetainsAnExplicitSelfGrantCondition(): void
    {
        $statement = new GrantRoles([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')))], [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
        self::assertNotNull($statement->problem);
        self::assertSame('GRANT cannot use anonymous accounts or grant an explicit account to itself.', $statement->problem->message());
    }

    public function testDeriveStatementPublishesAConcreteRequest(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('GRANT r TO u');
        self::assertInstanceOf(GrantRoles::class, $statement);
        self::assertNull($statement->problem);
        self::assertNotNull($statement->source);
    }

    public function testRenderWritesTheAdminOption(): void
    {
        self::assertSame('GRANT r TO u WITH ADMIN OPTION', (new Semantics(Dialect::MySql))->analyze('grant r to u with admin option')->toString());
    }
    public function testDeriveProgramKeepsAnUnreachedDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() GRANT r, '' TO u");
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(GrantRoles::class, $operation->statement->body);
    }

    public function testRenderPreservesTheCompleteDefinition(): void
    {
        self::assertSame('CREATE PROCEDURE p() GRANT r, `` TO u@h', (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() GRANT r, '' TO u@h")->toString());
    }

    public function testProblemKeepsItsOriginalRequest(): void
    {
        $roles = [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('')))];
        $request = new GrantRoles($roles, [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()]);
        self::assertNotNull($request->problem);
        self::assertSame($request->users, $request->problem->users);
    }

    public function testRenderAcceptsASessionDependentGrantWithoutAnIntrinsicCondition(): void
    {
        $roles = [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')))];
        $request = new GrantRoles($roles, [new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()]);
        self::assertNull($request->problem);
    }
    public function testToStringReconstructsTheCompleteRequest(): void
    {
        self::assertSame('GRANT r TO u', (new Semantics(Dialect::MySql))->analyze('GRANT r TO u')->toString());
    }

    public function testProfileRetainsTheSelectedLanguage(): void
    {
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('GRANT r TO u')->profile()->grammar);
    }

    public function testDeclarationsDoesNotDeclareAccountsAsTables(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('GRANT r TO u')->declarations());
    }
}
