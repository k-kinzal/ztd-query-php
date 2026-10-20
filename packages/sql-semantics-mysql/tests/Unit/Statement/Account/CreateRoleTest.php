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

    public function testRenderRequiresANamedRole(): void
    {
        $this->expectExceptionMessage('CREATE ROLE cannot define an anonymous account.');
        new CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(" \t"))]);
    }

    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE ROLE r')->facts->diagnostics);
    }

    public function testRenderRejectsDuplicateAccountIdentitiesBeforeOperationConstruction(): void
    {
        $this->expectExceptionMessage('CREATE ROLE cannot unconditionally define the same account twice.');
        new CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')), new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
    }

    public function testRenderKeepsConditionalDuplicates(): void
    {
        self::assertSame('CREATE ROLE IF NOT EXISTS r, r@`%`', (new Semantics(Dialect::MySql))->analyze("CREATE ROLE IF NOT EXISTS r, r@'%' ")->toString());
    }

    public function testRenderWritesTheRoles(): void
    {
        self::assertSame('CREATE ROLE IF NOT EXISTS r1, r2@h', (new Semantics(Dialect::MySql))->analyze('create role if not exists r1, r2@h')->toString());
    }
}
